-- New appointments may have a date without a time; existing schedules remain intact.
alter table public.appointments alter column start_time drop not null;
alter table public.appointments alter column end_time drop not null;
alter table public.appointments add constraint appointments_optional_time_pair_check
    check ((start_time is null and end_time is null) or (start_time is not null and end_time is not null and end_time > start_time));

create or replace function public.save_appointment(
    p_appointment_id bigint,
    p_customer_id bigint,
    p_offering_id bigint,
    p_primary_employee_id bigint,
    p_support_employee_id bigint,
    p_service_resource_id bigint,
    p_appointment_date date,
    p_start_time time,
    p_end_time time,
    p_payment_type text,
    p_customer_package_id bigint,
    p_source text,
    p_notes text,
    p_override_reason text default null
)
returns public.appointments
language plpgsql
security definer
set search_path = ''
as $$
declare
    appointment_row public.appointments%rowtype;
    offering_row public.offerings%rowtype;
    package_row public.customer_packages%rowtype;
    before_row jsonb;
    has_conflict boolean;
    appointment_code text;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_customer_id is null or p_offering_id is null or p_appointment_date is null
        or (p_start_time is null) <> (p_end_time is null)
        or (p_start_time is not null and p_end_time <= p_start_time) then
        raise exception using errcode = 'P0001', message = 'Thông tin khách hàng, dịch vụ hoặc khung giờ chưa hợp lệ.';
    end if;
    if p_support_employee_id is not null and p_support_employee_id = p_primary_employee_id then
        raise exception using errcode = 'P0001', message = 'Nhân viên hỗ trợ phải khác kỹ thuật viên chính.';
    end if;
    if p_payment_type is null or p_payment_type not in ('service', 'package')
        or p_source is null or p_source not in ('booked', 'walkin') then
        raise exception using errcode = 'P0001', message = 'Hình thức thanh toán hoặc nguồn lịch hẹn không hợp lệ.';
    end if;
    if p_notes is not null and pg_catalog.char_length(p_notes) > 1000 then
        raise exception using errcode = 'P0001', message = 'Ghi chú không được vượt quá 1.000 ký tự.';
    end if;
    if p_override_reason is not null and pg_catalog.char_length(p_override_reason) > 500 then
        raise exception using errcode = 'P0001', message = 'Lý do ghi đè không được vượt quá 500 ký tự.';
    end if;

    -- Serialize schedule writes for one calendar day so simultaneous requests cannot bypass conflict checks.
    perform pg_catalog.pg_advisory_xact_lock(pg_catalog.hashtextextended(p_appointment_date::text, 0));

    if not exists (
        select 1 from public.customers
        where id = p_customer_id and deleted_at is null
    ) then
        raise exception using errcode = 'P0001', message = 'Khách hàng không tồn tại hoặc đã được lưu trữ.';
    end if;

    select * into offering_row
    from public.offerings
    where id = p_offering_id and deleted_at is null;
    if not found then
        raise exception using errcode = 'P0001', message = 'Dịch vụ hoặc gói liệu trình không tồn tại.';
    end if;
    if p_payment_type = 'service' and offering_row.type <> 'service' then
        raise exception using errcode = 'P0001', message = 'Lịch thanh toán tại quầy chỉ áp dụng cho dịch vụ lẻ.';
    end if;

    if p_primary_employee_id is not null and not exists (
        select 1 from public.employees where id = p_primary_employee_id and deleted_at is null
    ) then
        raise exception using errcode = 'P0001', message = 'Kỹ thuật viên chính không tồn tại.';
    end if;
    if p_support_employee_id is not null and not exists (
        select 1 from public.employees where id = p_support_employee_id and deleted_at is null
    ) then
        raise exception using errcode = 'P0001', message = 'Nhân viên hỗ trợ không tồn tại.';
    end if;
    if p_service_resource_id is not null and not exists (
        select 1 from public.service_resources where id = p_service_resource_id and deleted_at is null
    ) then
        raise exception using errcode = 'P0001', message = 'Phòng hoặc giường không tồn tại.';
    end if;

    if p_payment_type = 'package' then
        if p_customer_package_id is null then
            select id into p_customer_package_id
            from public.customer_packages
            where customer_id = p_customer_id and offering_id = p_offering_id
                and status = 'active' and sessions_used < sessions_total
                and purchased_on <= p_appointment_date
                and (starts_on is null or starts_on <= p_appointment_date)
                and (expires_on is null or expires_on >= greatest(p_appointment_date, (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date))
            order by expires_on asc nulls last, purchased_on asc, id asc
            limit 1;
        end if;
        select * into package_row
        from public.customer_packages
        where id = p_customer_package_id;
        if not found
            or package_row.customer_id <> p_customer_id
            or package_row.offering_id <> p_offering_id
            or package_row.status <> 'active'
            or package_row.sessions_used >= package_row.sessions_total
            or package_row.purchased_on > p_appointment_date
            or (package_row.starts_on is not null and package_row.starts_on > p_appointment_date)
            or (package_row.expires_on is not null and package_row.expires_on < greatest(p_appointment_date, (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date)) then
            raise exception using errcode = 'P0001', message = 'Không có thẻ liệu trình phù hợp còn hạn và còn buổi cho ngày hẹn.';
        end if;
    elsif p_customer_package_id is not null then
        raise exception using errcode = 'P0001', message = 'Lịch thanh toán dịch vụ không được gắn thẻ liệu trình.';
    end if;

    if p_appointment_id is not null then
        select * into appointment_row
        from public.appointments
        where id = p_appointment_id
        for update;
        if not found then
            raise exception using errcode = 'P0001', message = 'Không tìm thấy lịch hẹn cần chỉnh sửa.';
        end if;
        if appointment_row.status not in ('scheduled', 'arrived') then
            raise exception using errcode = 'P0001', message = 'Chỉ có thể sửa lịch đã đặt hoặc đã đến.';
        end if;
        before_row := pg_catalog.to_jsonb(appointment_row);
        p_start_time := coalesce(p_start_time, appointment_row.start_time);
        p_end_time := coalesce(p_end_time, appointment_row.end_time);
    end if;

    select exists (
        select 1
        from public.appointments existing
        where existing.appointment_date = p_appointment_date
            and existing.status not in ('cancelled', 'no_show', 'paid', 'refunded', 'package_used')
            and existing.start_time < p_end_time
            and existing.end_time > p_start_time
            and (p_appointment_id is null or existing.id <> p_appointment_id)
            and (
                (p_primary_employee_id is not null and (existing.primary_employee_id = p_primary_employee_id or existing.support_employee_id = p_primary_employee_id))
                or (p_support_employee_id is not null and (existing.primary_employee_id = p_support_employee_id or existing.support_employee_id = p_support_employee_id))
                or (p_service_resource_id is not null and existing.service_resource_id = p_service_resource_id)
            )
    ) into has_conflict;

    if has_conflict and nullif(pg_catalog.btrim(p_override_reason), '') is null then
        raise exception using errcode = 'P0001', message = 'Lịch hẹn đang trùng kỹ thuật viên hoặc phòng/giường. Hãy nhập lý do nếu cần ghi đè.';
    end if;

    if p_appointment_id is null then
        appointment_code := 'LH-'
            || pg_catalog.to_char(pg_catalog.clock_timestamp(), 'YYYYMMDDHH24MISS')
            || '-'
            || pg_catalog.upper(pg_catalog.substr(pg_catalog.replace(pg_catalog.gen_random_uuid()::text, '-', ''), 1, 4));

        insert into public.appointments (
            customer_id, offering_id, primary_employee_id, support_employee_id, service_resource_id,
            customer_package_id, created_by, code, appointment_date, start_time, end_time,
            status, source, payment_type, override_conflict_reason, notes
        ) values (
            p_customer_id, p_offering_id, p_primary_employee_id, p_support_employee_id, p_service_resource_id,
            case when p_payment_type = 'package' then p_customer_package_id else null end,
            auth.uid(), appointment_code, p_appointment_date, p_start_time, p_end_time,
            'scheduled', p_source, p_payment_type, nullif(pg_catalog.btrim(p_override_reason), ''),
            nullif(pg_catalog.btrim(p_notes), '')
        ) returning * into appointment_row;
    else
        update public.appointments
        set customer_id = p_customer_id,
            offering_id = p_offering_id,
            primary_employee_id = p_primary_employee_id,
            support_employee_id = p_support_employee_id,
            service_resource_id = p_service_resource_id,
            customer_package_id = case when p_payment_type = 'package' then p_customer_package_id else null end,
            appointment_date = p_appointment_date,
            start_time = p_start_time,
            end_time = p_end_time,
            source = p_source,
            payment_type = p_payment_type,
            override_conflict_reason = nullif(pg_catalog.btrim(p_override_reason), ''),
            notes = nullif(pg_catalog.btrim(p_notes), ''),
            updated_at = pg_catalog.now()
        where id = p_appointment_id
        returning * into appointment_row;
    end if;

    perform private.write_audit_log(
        'appointment.saved', 'appointment', appointment_row.id::text,
        before_row, pg_catalog.to_jsonb(appointment_row), p_override_reason
    );

    return appointment_row;
end;
$$;
