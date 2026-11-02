-- Keep the desktop client read-only for service catalog tables and audit catalog writes.

revoke all on public.service_categories, public.offerings from public, anon, authenticated;
grant select on public.service_categories, public.offerings to authenticated;
revoke all on sequence public.service_categories_id_seq, public.offerings_id_seq from public, anon, authenticated;

create or replace function public.save_offering(
    p_offering_id bigint,
    p_code text,
    p_name text,
    p_type text,
    p_duration_minutes integer,
    p_price bigint,
    p_sessions_total integer,
    p_status text,
    p_featured boolean,
    p_description text
)
returns bigint
language plpgsql
security definer
set search_path = ''
as $$
declare
    offering_row public.offerings%rowtype;
    offering_before jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_code is null or pg_catalog.char_length(pg_catalog.btrim(p_code)) not between 1 and 32
        or p_name is null or pg_catalog.char_length(pg_catalog.btrim(p_name)) not between 1 and 160
        or p_type is null or p_type not in ('service', 'package')
        or p_duration_minutes is null or p_duration_minutes < 1 or p_duration_minutes > 1440
        or (p_price is not null and p_price < 0)
        or p_sessions_total is null or p_sessions_total < 1
        or p_status is null or p_status not in ('active', 'inactive')
        or p_featured is null
        or (p_description is not null and pg_catalog.char_length(p_description) > 1000) then
        raise exception using errcode = 'P0001', message = 'Thông tin dịch vụ hoặc gói liệu trình chưa hợp lệ.';
    end if;

    if p_offering_id is null then
        insert into public.offerings (
            code, name, type, duration_minutes, price, sessions_total, status, featured, description
        ) values (
            pg_catalog.upper(pg_catalog.btrim(p_code)), pg_catalog.btrim(p_name), p_type,
            p_duration_minutes, p_price, p_sessions_total, p_status, p_featured,
            nullif(pg_catalog.btrim(p_description), '')
        ) returning * into offering_row;
        perform private.write_audit_log(
            'offering.created', 'offering', offering_row.id::text, null, pg_catalog.to_jsonb(offering_row), null
        );
    else
        select * into offering_row
        from public.offerings
        where id = p_offering_id and deleted_at is null
        for update;
        if not found then
            raise exception using errcode = 'P0001', message = 'Không tìm thấy dịch vụ hoặc gói liệu trình cần cập nhật.';
        end if;
        offering_before := pg_catalog.to_jsonb(offering_row);

        update public.offerings
        set code = pg_catalog.upper(pg_catalog.btrim(p_code)),
            name = pg_catalog.btrim(p_name),
            type = p_type,
            duration_minutes = p_duration_minutes,
            price = p_price,
            sessions_total = p_sessions_total,
            status = p_status,
            featured = p_featured,
            description = nullif(pg_catalog.btrim(p_description), ''),
            updated_at = pg_catalog.now()
        where id = p_offering_id
        returning * into offering_row;
        perform private.write_audit_log(
            'offering.updated', 'offering', offering_row.id::text,
            offering_before, pg_catalog.to_jsonb(offering_row), null
        );
    end if;

    return offering_row.id;
exception
    when unique_violation then
        raise exception using errcode = 'P0001', message = 'Mã dịch vụ đã được sử dụng.';
end;
$$;
revoke all on function public.save_offering(bigint, text, text, text, integer, bigint, integer, text, boolean, text) from public, anon;
grant execute on function public.save_offering(bigint, text, text, text, integer, bigint, integer, text, boolean, text) to authenticated;
