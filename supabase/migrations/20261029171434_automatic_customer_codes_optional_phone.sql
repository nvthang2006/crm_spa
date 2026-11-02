-- Customer codes are allocated on the server; contact fields may be omitted.
alter table public.customers alter column phone drop not null;
create sequence private.customer_code_seq as bigint;
revoke all on sequence private.customer_code_seq from public, anon, authenticated;

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
    customer_code text := nullif(pg_catalog.upper(pg_catalog.btrim(p_code)), '');
    customer_number bigint;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if (p_customer_id is null and pg_catalog.char_length(customer_code) > 32)
        or nullif(pg_catalog.btrim(p_name), '') is null or pg_catalog.char_length(p_name) > 120
        or pg_catalog.char_length(p_phone) > 32
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
        if customer_code is null then
            lock table public.customers in share row exclusive mode;
            select greatest(
                pg_catalog.nextval('private.customer_code_seq'::regclass)::numeric,
                coalesce(max(pg_catalog.substring(code, '^KH-?([0-9]+)$')::numeric), 0) + 1
            )::bigint into customer_number
            from public.customers where code ~ '^KH-?[0-9]+$';
            perform pg_catalog.setval('private.customer_code_seq'::regclass, customer_number, true);
            customer_code := 'KH' || pg_catalog.lpad(customer_number::text, greatest(3, pg_catalog.char_length(customer_number::text)), '0');
        end if;
        insert into public.customers (
            code, name, phone, email, membership_tier, birthday, address, notes, status
        ) values (
            customer_code, pg_catalog.btrim(p_name), nullif(pg_catalog.btrim(p_phone), ''),
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
        set code = customer_row.code,
            name = pg_catalog.btrim(p_name),
            phone = nullif(pg_catalog.btrim(p_phone), ''),
            email = case when p_email is null then email else nullif(pg_catalog.btrim(p_email), '') end,
            membership_tier = pg_catalog.btrim(p_membership_tier),
            birthday = p_birthday,
            address = case when p_address is null then address else nullif(pg_catalog.btrim(p_address), '') end,
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
