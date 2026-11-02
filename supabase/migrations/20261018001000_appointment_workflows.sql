-- Keep appointment state changes and package usage inside atomic database functions.
-- The desktop client may read these records but cannot mutate the underlying workflow tables.

create or replace function private.write_audit_log(
    p_action text,
    p_subject_type text,
    p_subject_id text,
    p_before jsonb default null,
    p_after jsonb default null,
    p_reason text default null
)
returns bigint
language plpgsql
security definer
set search_path = ''
as $$
declare
    audit_id bigint;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    insert into public.audit_logs (user_id, action, subject_type, subject_id, before, after, reason, created_at)
    values (
        auth.uid(),
        left(p_action, 64),
        p_subject_type,
        p_subject_id,
        p_before,
        p_after,
        nullif(pg_catalog.btrim(p_reason), ''),
        pg_catalog.now()
    )
    returning id into audit_id;

    return audit_id;
end;
$$;
revoke all on function private.write_audit_log(text, text, text, jsonb, jsonb, text) from public, anon, authenticated;

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
        or p_start_time is null or p_end_time is null or p_end_time <= p_start_time then
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
            raise exception using errcode = 'P0001', message = 'Vui lòng chọn thẻ liệu trình.';
        end if;
        select * into package_row
        from public.customer_packages
        where id = p_customer_package_id;
        if not found
            or package_row.customer_id <> p_customer_id
            or package_row.offering_id <> p_offering_id
            or package_row.status <> 'active'
            or package_row.sessions_used >= package_row.sessions_total
            or (package_row.expires_on is not null and package_row.expires_on < (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date) then
            raise exception using errcode = 'P0001', message = 'Thẻ liệu trình không thuộc khách hoặc đã hết buổi.';
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
revoke all on function public.save_appointment(bigint, bigint, bigint, bigint, bigint, bigint, date, time without time zone, time without time zone, text, bigint, text, text, text) from public, anon;
grant execute on function public.save_appointment(bigint, bigint, bigint, bigint, bigint, bigint, date, time without time zone, time without time zone, text, bigint, text, text, text) to authenticated;

create or replace function public.transition_appointment(
    p_appointment_id bigint,
    p_status text,
    p_reason text default null
)
returns public.appointments
language plpgsql
security definer
set search_path = ''
as $$
declare
    appointment_row public.appointments%rowtype;
    before_row jsonb;
    resource_row public.service_resources%rowtype;
    invoice_row public.invoices%rowtype;
    invoice_before jsonb;
    invoice_reason text;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    select * into appointment_row
    from public.appointments
    where id = p_appointment_id
    for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy lịch hẹn.';
    end if;

    if not (
        (appointment_row.status = 'scheduled' and p_status in ('arrived', 'cancelled', 'no_show'))
        or (appointment_row.status = 'arrived' and p_status in ('in_service', 'cancelled', 'no_show'))
        or (appointment_row.status in ('in_service', 'completed_pending_payment') and p_status = 'cancelled')
    ) then
        raise exception using errcode = 'P0001', message = 'Không thể chuyển lịch hẹn sang trạng thái này.';
    end if;

    before_row := pg_catalog.to_jsonb(appointment_row);

    if p_status = 'in_service' and appointment_row.service_resource_id is not null then
        select * into resource_row
        from public.service_resources
        where id = appointment_row.service_resource_id
        for update;
        if found and resource_row.status <> 'ready' then
            raise exception using errcode = 'P0001', message = 'Phòng hoặc giường chưa sẵn sàng để bắt đầu phục vụ.';
        end if;
        if found then
            update public.service_resources
            set status = 'busy', updated_at = pg_catalog.now()
            where id = resource_row.id;
        end if;
    end if;

    if p_status = 'cancelled' and appointment_row.service_resource_id is not null then
        update public.service_resources
        set status = 'ready', updated_at = pg_catalog.now()
        where id = appointment_row.service_resource_id;
    end if;

    update public.appointments
    set status = p_status,
        checked_in_at = case when p_status = 'arrived' then pg_catalog.now() else checked_in_at end,
        started_at = case when p_status = 'in_service' then pg_catalog.now() else started_at end,
        cancelled_at = case when p_status = 'cancelled' then pg_catalog.now() else cancelled_at end,
        cancel_reason = case when p_status = 'cancelled' then p_reason else cancel_reason end,
        updated_at = pg_catalog.now()
    where id = p_appointment_id
    returning * into appointment_row;

    if p_status = 'cancelled' then
        select * into invoice_row
        from public.invoices
        where appointment_id = p_appointment_id and status = 'pending'
        for update;
        if found then
            invoice_before := pg_catalog.to_jsonb(invoice_row);
            invoice_reason := 'Tự động hủy theo lịch hẹn' || case when nullif(pg_catalog.btrim(p_reason), '') is null then '.' else ': ' || pg_catalog.btrim(p_reason) end;
            update public.invoices
            set status = 'void', voided_at = pg_catalog.now(), notes = invoice_reason, updated_at = pg_catalog.now()
            where id = invoice_row.id
            returning * into invoice_row;
            perform private.write_audit_log('invoice.voided', 'invoice', invoice_row.id::text, invoice_before, pg_catalog.to_jsonb(invoice_row), invoice_reason);
        end if;
    end if;

    perform private.write_audit_log('appointment.status_changed', 'appointment', appointment_row.id::text, before_row, pg_catalog.to_jsonb(appointment_row), p_reason);
    return appointment_row;
end;
$$;
revoke all on function public.transition_appointment(bigint, text, text) from public, anon;
grant execute on function public.transition_appointment(bigint, text, text) to authenticated;

create or replace function public.use_package_session(
    p_appointment_id bigint,
    p_note text default null
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    appointment_row public.appointments%rowtype;
    package_row public.customer_packages%rowtype;
    usage_row public.package_usages%rowtype;
    appointment_before jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    select * into appointment_row
    from public.appointments
    where id = p_appointment_id
    for update;
    if not found
        or appointment_row.status <> 'completed_pending_payment'
        or appointment_row.payment_type <> 'package'
        or appointment_row.customer_package_id is null then
        raise exception using errcode = 'P0001', message = 'Lịch hẹn chưa sẵn sàng để trừ thẻ.';
    end if;

    select * into package_row
    from public.customer_packages
    where id = appointment_row.customer_package_id
    for update;
    if not found
        or package_row.customer_id <> appointment_row.customer_id
        or package_row.offering_id <> appointment_row.offering_id then
        raise exception using errcode = 'P0001', message = 'Lịch hẹn không hợp lệ để trừ thẻ liệu trình.';
    end if;
    if exists (
        select 1 from public.package_usages
        where appointment_id = appointment_row.id and direction = 'use'
    ) then
        raise exception using errcode = 'P0001', message = 'Lịch hẹn này đã được trừ buổi trước đó.';
    end if;
    if package_row.status <> 'active'
        or package_row.sessions_used >= package_row.sessions_total
        or (package_row.expires_on is not null and package_row.expires_on < (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date) then
        raise exception using errcode = 'P0001', message = 'Thẻ liệu trình không còn buổi có thể sử dụng.';
    end if;

    insert into public.package_usages (
        customer_package_id, appointment_id, performed_by, recorded_by,
        session_number, direction, verification_method, verification_note, used_at
    ) values (
        package_row.id, appointment_row.id, appointment_row.primary_employee_id, auth.uid(),
        package_row.sessions_used + 1, 'use', 'admin_confirmation',
        nullif(pg_catalog.btrim(p_note), ''), pg_catalog.now()
    ) returning * into usage_row;

    update public.customer_packages
    set sessions_used = sessions_used + 1,
        status = case when sessions_used + 1 >= sessions_total then 'completed' else status end,
        updated_at = pg_catalog.now()
    where id = package_row.id
    returning * into package_row;

    appointment_before := pg_catalog.to_jsonb(appointment_row);
    update public.appointments
    set status = 'package_used', updated_at = pg_catalog.now()
    where id = appointment_row.id
    returning * into appointment_row;

    perform private.write_audit_log('package.session_used', 'package_usage', usage_row.id::text, null, pg_catalog.to_jsonb(usage_row), p_note);
    perform private.write_audit_log('appointment.status_changed', 'appointment', appointment_row.id::text, appointment_before, pg_catalog.to_jsonb(appointment_row), p_note);

    return pg_catalog.jsonb_build_object(
        'appointment', pg_catalog.to_jsonb(appointment_row),
        'package', pg_catalog.to_jsonb(package_row),
        'usage', pg_catalog.to_jsonb(usage_row)
    );
end;
$$;
revoke all on function public.use_package_session(bigint, text) from public, anon;
grant execute on function public.use_package_session(bigint, text) to authenticated;

create or replace function public.complete_appointment(p_appointment_id bigint)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    appointment_row public.appointments%rowtype;
    appointment_before jsonb;
    offering_row public.offerings%rowtype;
    invoice_row public.invoices%rowtype;
    invoice_item_row public.invoice_items%rowtype;
    invoice_code text;
    invoice_amount bigint;
    package_result jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    select * into appointment_row
    from public.appointments
    where id = p_appointment_id
    for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy lịch hẹn.';
    end if;
    if appointment_row.status <> 'in_service' then
        raise exception using errcode = 'P0001', message = 'Chỉ dịch vụ đang thực hiện mới được hoàn thành.';
    end if;
    if appointment_row.payment_type not in ('service', 'package') then
        raise exception using errcode = 'P0001', message = 'Hình thức thanh toán của lịch hẹn không hợp lệ.';
    end if;

    appointment_before := pg_catalog.to_jsonb(appointment_row);
    update public.appointments
    set status = 'completed_pending_payment', completed_at = pg_catalog.now(), updated_at = pg_catalog.now()
    where id = appointment_row.id
    returning * into appointment_row;
    if appointment_row.service_resource_id is not null then
        update public.service_resources
        set status = 'cleaning', updated_at = pg_catalog.now()
        where id = appointment_row.service_resource_id;
    end if;
    perform private.write_audit_log('appointment.status_changed', 'appointment', appointment_row.id::text, appointment_before, pg_catalog.to_jsonb(appointment_row), null);

    if appointment_row.payment_type = 'package' then
        package_result := public.use_package_session(appointment_row.id, 'Xác nhận trừ buổi khi hoàn thành dịch vụ.');
        return package_result || pg_catalog.jsonb_build_object('invoice', null);
    end if;

    select * into offering_row
    from public.offerings
    where id = appointment_row.offering_id and deleted_at is null;
    if not found or offering_row.type <> 'service' then
        raise exception using errcode = 'P0001', message = 'Chỉ lịch dịch vụ lẻ mới được tạo phiếu thu.';
    end if;

    select * into invoice_row
    from public.invoices
    where appointment_id = appointment_row.id
    for update;
    if not found then
        invoice_amount := coalesce(offering_row.price, 0);
        invoice_code := 'HD-'
            || pg_catalog.to_char(pg_catalog.clock_timestamp(), 'YYYYMMDDHH24MISS')
            || '-'
            || pg_catalog.upper(pg_catalog.substr(pg_catalog.replace(pg_catalog.gen_random_uuid()::text, '-', ''), 1, 4));

        insert into public.invoices (
            customer_id, appointment_id, created_by, code, type, status,
            subtotal, total_received, issued_at
        ) values (
            appointment_row.customer_id, appointment_row.id, auth.uid(), invoice_code,
            'service', 'pending', invoice_amount, 0, pg_catalog.now()
        ) returning * into invoice_row;

        insert into public.invoice_items (
            invoice_id, offering_id, employee_id, description, quantity,
            unit_price, line_total, package_sessions
        ) values (
            invoice_row.id, offering_row.id, appointment_row.primary_employee_id, offering_row.name, 1,
            invoice_amount, invoice_amount, 0
        ) returning * into invoice_item_row;

        perform private.write_audit_log('invoice.created', 'invoice', invoice_row.id::text, null, pg_catalog.to_jsonb(invoice_row), null);
    end if;

    return pg_catalog.jsonb_build_object(
        'appointment', pg_catalog.to_jsonb(appointment_row),
        'invoice', pg_catalog.to_jsonb(invoice_row),
        'invoice_item', case when invoice_item_row.id is null then null else pg_catalog.to_jsonb(invoice_item_row) end
    );
end;
$$;
revoke all on function public.complete_appointment(bigint) from public, anon;
grant execute on function public.complete_appointment(bigint) to authenticated;

-- Financial and lifecycle rows are writable only through audited transaction functions.
revoke insert, update, delete on table
    public.appointments,
    public.customer_packages,
    public.package_usages,
    public.invoices,
    public.invoice_items,
    public.payments,
    public.invoice_refunds,
    public.commission_entries,
    public.payroll_periods,
    public.payroll_lines,
    public.payroll_adjustments
from anon, authenticated;
