-- Keep customer writes, health data access, and package history behind audited RPCs.

create extension if not exists pgcrypto with schema extensions;
create schema if not exists vault;
create extension if not exists supabase_vault with schema vault;

create or replace function private.health_encryption_key()
returns text
language plpgsql
security definer
set search_path = ''
as $$
declare
    encryption_key text;
begin
    perform pg_catalog.pg_advisory_xact_lock(
        pg_catalog.hashtextextended('sunny_beauty.health_profile.encryption_key', 0)
    );

    select decrypted_secret into encryption_key
    from vault.decrypted_secrets
    where name = 'sunny_beauty_health_profile_key';

    if encryption_key is null then
        perform vault.create_secret(
            pg_catalog.encode(extensions.gen_random_bytes(32), 'hex'),
            'sunny_beauty_health_profile_key',
            'Mã hóa trường sức khỏe của khách hàng Sunny Beauty'
        );

        select decrypted_secret into encryption_key
        from vault.decrypted_secrets
        where name = 'sunny_beauty_health_profile_key';
    end if;

    if encryption_key is null then
        raise exception using errcode = 'P0001', message = 'Chưa khởi tạo được khóa bảo vệ hồ sơ sức khỏe.';
    end if;

    return encryption_key;
end;
$$;
revoke all on function private.health_encryption_key() from public, anon, authenticated;

-- Preserve any existing values while encrypting them before changing the column type.
select private.health_encryption_key();
alter table public.customer_health_profiles
    alter column allergy_notes type bytea using case when allergy_notes is null then null else extensions.pgp_sym_encrypt(allergy_notes, private.health_encryption_key(), 'cipher-algo=aes256') end,
    alter column current_condition type bytea using case when current_condition is null then null else extensions.pgp_sym_encrypt(current_condition, private.health_encryption_key(), 'cipher-algo=aes256') end,
    alter column contraindications type bytea using case when contraindications is null then null else extensions.pgp_sym_encrypt(contraindications, private.health_encryption_key(), 'cipher-algo=aes256') end,
    alter column care_notes type bytea using case when care_notes is null then null else extensions.pgp_sym_encrypt(care_notes, private.health_encryption_key(), 'cipher-algo=aes256') end;

revoke select, insert, update, delete on table public.customer_health_profiles from anon, authenticated;
revoke insert, update, delete on table public.customers from anon, authenticated;

create or replace function public.save_customer(
    p_customer_id bigint,
    p_code text,
    p_name text,
    p_phone text,
    p_email text,
    p_membership_tier text,
    p_birthday date,
    p_address text,
    p_notes text,
    p_preferred_employee_id bigint,
    p_allergy_notes text,
    p_current_condition text,
    p_contraindications text,
    p_care_notes text,
    p_update_health boolean
)
returns public.customers
language plpgsql
security definer
set search_path = ''
as $$
declare
    customer_row public.customers%rowtype;
    before_row jsonb;
    encryption_key text;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if nullif(pg_catalog.btrim(p_code), '') is null or pg_catalog.char_length(p_code) > 32
        or nullif(pg_catalog.btrim(p_name), '') is null or pg_catalog.char_length(p_name) > 120
        or nullif(pg_catalog.btrim(p_phone), '') is null or pg_catalog.char_length(p_phone) > 32
        or nullif(pg_catalog.btrim(p_membership_tier), '') is null or pg_catalog.char_length(p_membership_tier) > 24 then
        raise exception using errcode = 'P0001', message = 'Mã, tên, số điện thoại hoặc hạng thành viên chưa hợp lệ.';
    end if;
    if p_email is not null and (pg_catalog.char_length(p_email) > 255 or p_email !~ '^[^[:space:]@]+@[^[:space:]@]+\.[^[:space:]@]+$') then
        raise exception using errcode = 'P0001', message = 'Địa chỉ email chưa hợp lệ.';
    end if;
    if p_address is not null and pg_catalog.char_length(p_address) > 255 then
        raise exception using errcode = 'P0001', message = 'Địa chỉ không được vượt quá 255 ký tự.';
    end if;
    if p_notes is not null and pg_catalog.char_length(p_notes) > 2000 then
        raise exception using errcode = 'P0001', message = 'Ghi chú không được vượt quá 2.000 ký tự.';
    end if;
    if coalesce(pg_catalog.char_length(p_allergy_notes), 0) > 5000
        or coalesce(pg_catalog.char_length(p_current_condition), 0) > 5000
        or coalesce(pg_catalog.char_length(p_contraindications), 0) > 5000
        or coalesce(pg_catalog.char_length(p_care_notes), 0) > 5000 then
        raise exception using errcode = 'P0001', message = 'Mỗi trường thông tin sức khỏe không được vượt quá 5.000 ký tự.';
    end if;
    if p_preferred_employee_id is not null and not exists (
        select 1 from public.employees where id = p_preferred_employee_id
    ) then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy nhân sự được ưu tiên.';
    end if;

    if coalesce(p_update_health, false) then
        encryption_key := private.health_encryption_key();
    end if;

    if p_customer_id is null then
        insert into public.customers (
            code, name, phone, email, membership_tier, birthday, address, notes, status
        ) values (
            pg_catalog.upper(pg_catalog.btrim(p_code)), pg_catalog.btrim(p_name), pg_catalog.btrim(p_phone),
            nullif(pg_catalog.btrim(p_email), ''), pg_catalog.btrim(p_membership_tier), p_birthday,
            nullif(pg_catalog.btrim(p_address), ''), nullif(pg_catalog.btrim(p_notes), ''), 'active'
        ) returning * into customer_row;
    else
        select * into customer_row
        from public.customers
        where id = p_customer_id and deleted_at is null
        for update;
        if not found then
            raise exception using errcode = 'P0001', message = 'Không tìm thấy hồ sơ khách hàng đang hoạt động.';
        end if;

        before_row := pg_catalog.jsonb_build_object('id', customer_row.id, 'code', customer_row.code);
        update public.customers
        set code = pg_catalog.upper(pg_catalog.btrim(p_code)),
            name = pg_catalog.btrim(p_name),
            phone = pg_catalog.btrim(p_phone),
            email = nullif(pg_catalog.btrim(p_email), ''),
            membership_tier = pg_catalog.btrim(p_membership_tier),
            birthday = p_birthday,
            address = nullif(pg_catalog.btrim(p_address), ''),
            notes = nullif(pg_catalog.btrim(p_notes), ''),
            updated_at = pg_catalog.now()
        where id = p_customer_id
        returning * into customer_row;
    end if;

    if coalesce(p_update_health, false) then
        insert into public.customer_health_profiles (
            customer_id, preferred_employee_id, last_updated_by,
            allergy_notes, current_condition, contraindications, care_notes
        ) values (
            customer_row.id, p_preferred_employee_id, auth.uid(),
            case when nullif(pg_catalog.btrim(p_allergy_notes), '') is null then null else extensions.pgp_sym_encrypt(pg_catalog.btrim(p_allergy_notes), encryption_key, 'cipher-algo=aes256') end,
            case when nullif(pg_catalog.btrim(p_current_condition), '') is null then null else extensions.pgp_sym_encrypt(pg_catalog.btrim(p_current_condition), encryption_key, 'cipher-algo=aes256') end,
            case when nullif(pg_catalog.btrim(p_contraindications), '') is null then null else extensions.pgp_sym_encrypt(pg_catalog.btrim(p_contraindications), encryption_key, 'cipher-algo=aes256') end,
            case when nullif(pg_catalog.btrim(p_care_notes), '') is null then null else extensions.pgp_sym_encrypt(pg_catalog.btrim(p_care_notes), encryption_key, 'cipher-algo=aes256') end
        )
        on conflict (customer_id) do update
        set preferred_employee_id = excluded.preferred_employee_id,
            last_updated_by = excluded.last_updated_by,
            allergy_notes = excluded.allergy_notes,
            current_condition = excluded.current_condition,
            contraindications = excluded.contraindications,
            care_notes = excluded.care_notes,
            updated_at = pg_catalog.now();
    end if;

    perform private.write_audit_log(
        'customer.saved', 'customer', customer_row.id::text,
        before_row,
        pg_catalog.jsonb_build_object('id', customer_row.id, 'code', customer_row.code),
        case when coalesce(p_update_health, false) then 'Đã cập nhật hồ sơ và mã hóa thông tin sức khỏe' else 'Đã cập nhật thông tin cơ bản' end
    );

    return customer_row;
end;
$$;
revoke all on function public.save_customer(bigint, text, text, text, text, text, date, text, text, bigint, text, text, text, text, boolean) from public, anon;
grant execute on function public.save_customer(bigint, text, text, text, text, text, date, text, text, bigint, text, text, text, text, boolean) to authenticated;

create or replace function public.get_customer_health_profile(p_customer_id bigint)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    profile_row public.customer_health_profiles%rowtype;
    encryption_key text;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if not exists (select 1 from public.customers where id = p_customer_id and deleted_at is null) then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy hồ sơ khách hàng đang hoạt động.';
    end if;

    encryption_key := private.health_encryption_key();
    select * into profile_row
    from public.customer_health_profiles
    where customer_id = p_customer_id;

    perform private.write_audit_log(
        'customer.health_viewed', 'customer', p_customer_id::text,
        null, pg_catalog.jsonb_build_object('customer_id', p_customer_id), null
    );

    return pg_catalog.jsonb_build_object(
        'customer_id', p_customer_id,
        'preferred_employee_id', profile_row.preferred_employee_id,
        'allergy_notes', case when profile_row.allergy_notes is null then null else extensions.pgp_sym_decrypt(profile_row.allergy_notes, encryption_key) end,
        'current_condition', case when profile_row.current_condition is null then null else extensions.pgp_sym_decrypt(profile_row.current_condition, encryption_key) end,
        'contraindications', case when profile_row.contraindications is null then null else extensions.pgp_sym_decrypt(profile_row.contraindications, encryption_key) end,
        'care_notes', case when profile_row.care_notes is null then null else extensions.pgp_sym_decrypt(profile_row.care_notes, encryption_key) end
    );
end;
$$;
revoke all on function public.get_customer_health_profile(bigint) from public, anon;
grant execute on function public.get_customer_health_profile(bigint) to authenticated;

create or replace function public.consume_customer_package_session(
    p_customer_package_id bigint,
    p_performed_by bigint,
    p_note text default null
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    package_row public.customer_packages%rowtype;
    usage_row public.package_usages%rowtype;
    next_session_number integer;
    performer_id bigint;
    today_local date := (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_note is not null and pg_catalog.char_length(p_note) > 500 then
        raise exception using errcode = 'P0001', message = 'Ghi chú không được vượt quá 500 ký tự.';
    end if;

    select * into package_row
    from public.customer_packages
    where id = p_customer_package_id
    for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy thẻ liệu trình.';
    end if;
    if package_row.status <> 'active' or package_row.sessions_used >= package_row.sessions_total
        or (package_row.expires_on is not null and package_row.expires_on < today_local) then
        raise exception using errcode = 'P0001', message = 'Thẻ liệu trình không còn buổi có thể sử dụng.';
    end if;

    select coalesce(pg_catalog.max(usage.session_number), 0) + 1 into next_session_number
    from public.package_usages usage
    where usage.customer_package_id = package_row.id and usage.direction = 'use';
    select coalesce(p_performed_by, preferred_employee_id) into performer_id
    from public.customer_health_profiles
    where customer_id = package_row.customer_id;

    insert into public.package_usages (
        customer_package_id, performed_by, recorded_by, session_number, direction,
        verification_method, verification_note, used_at
    ) values (
        package_row.id, performer_id, auth.uid(), next_session_number, 'use',
        'admin_confirmation', nullif(pg_catalog.btrim(p_note), ''), pg_catalog.now()
    ) returning * into usage_row;

    update public.customer_packages
    set sessions_used = sessions_used + 1,
        status = case when sessions_used + 1 >= sessions_total then 'completed' else status end,
        updated_at = pg_catalog.now()
    where id = package_row.id
    returning * into package_row;

    perform private.write_audit_log('package.session_used', 'package_usage', usage_row.id::text, null, pg_catalog.to_jsonb(usage_row), p_note);

    return pg_catalog.jsonb_build_object('package', pg_catalog.to_jsonb(package_row), 'usage', pg_catalog.to_jsonb(usage_row));
end;
$$;
revoke all on function public.consume_customer_package_session(bigint, bigint, text) from public, anon;
grant execute on function public.consume_customer_package_session(bigint, bigint, text) to authenticated;

create or replace function public.restore_customer_package_session(
    p_customer_package_id bigint,
    p_session_number integer,
    p_reason text
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    package_row public.customer_packages%rowtype;
    usage_row public.package_usages%rowtype;
    restore_row public.package_usages%rowtype;
    today_local date := (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if nullif(pg_catalog.btrim(p_reason), '') is null
        or pg_catalog.char_length(p_reason) < 5 or pg_catalog.char_length(p_reason) > 500 then
        raise exception using errcode = 'P0001', message = 'Lý do hoàn buổi phải có từ 5 đến 500 ký tự.';
    end if;

    select * into package_row
    from public.customer_packages
    where id = p_customer_package_id
    for update;
    if not found or package_row.status = 'refunded' then
        raise exception using errcode = 'P0001', message = 'Thẻ đã hoàn tiền hoặc không tồn tại.';
    end if;
    select * into usage_row
    from public.package_usages
    where customer_package_id = package_row.id and session_number = p_session_number and direction = 'use'
    for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy buổi đã sử dụng cần hoàn.';
    end if;
    if exists (
        select 1 from public.package_usages
        where customer_package_id = package_row.id and session_number = p_session_number and direction = 'restore'
    ) then
        raise exception using errcode = 'P0001', message = 'Buổi này đã được hoàn lại trước đó.';
    end if;
    if package_row.sessions_used < 1 then
        raise exception using errcode = 'P0001', message = 'Thẻ không có buổi đã sử dụng để hoàn lại.';
    end if;

    insert into public.package_usages (
        customer_package_id, session_number, direction, verification_method,
        verification_note, recorded_by, used_at
    ) values (
        package_row.id, p_session_number, 'restore', 'admin_confirmation',
        pg_catalog.btrim(p_reason), auth.uid(), pg_catalog.now()
    ) returning * into restore_row;

    update public.customer_packages
    set sessions_used = sessions_used - 1,
        status = case when expires_on is not null and expires_on < today_local then 'expired' else 'active' end,
        updated_at = pg_catalog.now()
    where id = package_row.id
    returning * into package_row;

    perform private.write_audit_log('package.session_restored', 'package_usage', restore_row.id::text, null, pg_catalog.to_jsonb(restore_row), p_reason);

    return pg_catalog.jsonb_build_object('package', pg_catalog.to_jsonb(package_row), 'usage', pg_catalog.to_jsonb(restore_row));
end;
$$;
revoke all on function public.restore_customer_package_session(bigint, integer, text) from public, anon;
grant execute on function public.restore_customer_package_session(bigint, integer, text) to authenticated;

-- Use monotonic usage event numbers so restoring a session never collides with the audit history.
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
    next_session_number integer;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    select * into appointment_row from public.appointments where id = p_appointment_id for update;
    if not found or appointment_row.status <> 'completed_pending_payment'
        or appointment_row.payment_type <> 'package' or appointment_row.customer_package_id is null then
        raise exception using errcode = 'P0001', message = 'Lịch hẹn chưa sẵn sàng để trừ thẻ.';
    end if;
    select * into package_row from public.customer_packages where id = appointment_row.customer_package_id for update;
    if not found or package_row.customer_id <> appointment_row.customer_id or package_row.offering_id <> appointment_row.offering_id then
        raise exception using errcode = 'P0001', message = 'Lịch hẹn không hợp lệ để trừ thẻ liệu trình.';
    end if;
    if exists (select 1 from public.package_usages where appointment_id = appointment_row.id and direction = 'use') then
        raise exception using errcode = 'P0001', message = 'Lịch hẹn này đã được trừ buổi trước đó.';
    end if;
    if package_row.status <> 'active' or package_row.sessions_used >= package_row.sessions_total
        or (package_row.expires_on is not null and package_row.expires_on < (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date) then
        raise exception using errcode = 'P0001', message = 'Thẻ liệu trình không còn buổi có thể sử dụng.';
    end if;
    if p_note is not null and pg_catalog.char_length(p_note) > 500 then
        raise exception using errcode = 'P0001', message = 'Ghi chú không được vượt quá 500 ký tự.';
    end if;

    select coalesce(pg_catalog.max(usage.session_number), 0) + 1 into next_session_number
    from public.package_usages usage
    where usage.customer_package_id = package_row.id and usage.direction = 'use';
    insert into public.package_usages (
        customer_package_id, appointment_id, performed_by, recorded_by,
        session_number, direction, verification_method, verification_note, used_at
    ) values (
        package_row.id, appointment_row.id, appointment_row.primary_employee_id, auth.uid(),
        next_session_number, 'use', 'admin_confirmation', nullif(pg_catalog.btrim(p_note), ''), pg_catalog.now()
    ) returning * into usage_row;
    update public.customer_packages
    set sessions_used = sessions_used + 1,
        status = case when sessions_used + 1 >= sessions_total then 'completed' else status end,
        updated_at = pg_catalog.now()
    where id = package_row.id returning * into package_row;

    appointment_before := pg_catalog.to_jsonb(appointment_row);
    update public.appointments set status = 'package_used', updated_at = pg_catalog.now()
    where id = appointment_row.id returning * into appointment_row;
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
