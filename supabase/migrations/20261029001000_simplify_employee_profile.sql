-- Missing salary means not configured. Preserve historical phone and shift on profile edits.
alter table public.employees alter column base_salary drop not null;
alter table public.employees alter column base_salary drop default;

create or replace function public.save_employee(
    p_employee_id bigint,
    p_code text,
    p_name text,
    p_phone text,
    p_job_title text,
    p_shift_name text,
    p_base_salary bigint
)
returns bigint
language plpgsql
security definer
set search_path = ''
as $$
declare
    employee_row public.employees%rowtype;
    employee_before jsonb;
    employee_id bigint;
    employee_code text := nullif(pg_catalog.btrim(p_code), '');
    employee_number bigint;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if (p_employee_id is not null and employee_code is null)
        or pg_catalog.char_length(employee_code) > 32
        or p_name is null or pg_catalog.char_length(pg_catalog.btrim(p_name)) not between 1 and 120
        or pg_catalog.char_length(coalesce(p_phone, '')) > 32
        or p_job_title is null or pg_catalog.char_length(pg_catalog.btrim(p_job_title)) not between 1 and 120
        or pg_catalog.char_length(coalesce(p_shift_name, '')) > 60
        or p_base_salary < 0 then
        raise exception using errcode = 'P0001', message = 'Thông tin nhân viên chưa hợp lệ. Hãy kiểm tra mã, họ tên, vị trí và lương cứng.';
    end if;
    if p_employee_id is null and employee_code is null then
        lock table public.employees in share row exclusive mode;
        select greatest(
            pg_catalog.nextval('private.employee_code_seq'::regclass)::numeric,
            coalesce(max(pg_catalog.substring(code, '^NV([0-9]+)$')::numeric), 0) + 1
        )::bigint into employee_number
        from public.employees where code ~ '^NV[0-9]+$';
        perform pg_catalog.setval('private.employee_code_seq'::regclass, employee_number, true);
        employee_code := 'NV' || pg_catalog.lpad(employee_number::text, greatest(3, pg_catalog.char_length(employee_number::text)), '0');
    end if;

    if exists (
        select 1 from public.employees
        where code = employee_code
            and (p_employee_id is null or id <> p_employee_id)
    ) then
        raise exception using errcode = 'P0001', message = 'Mã nhân viên đã được sử dụng.';
    end if;

    if p_employee_id is null then
        insert into public.employees (code, name, phone, job_title, shift_name, base_salary, status)
        values (employee_code, pg_catalog.btrim(p_name), nullif(pg_catalog.btrim(p_phone), ''),
            pg_catalog.btrim(p_job_title), nullif(pg_catalog.btrim(p_shift_name), ''), p_base_salary, 'active')
        returning * into employee_row;
        employee_id := employee_row.id;
        perform private.write_audit_log('employee.created', 'employee', employee_row.id::text, null, pg_catalog.to_jsonb(employee_row), null);
    else
        select * into employee_row from public.employees where id = p_employee_id and deleted_at is null for update;
        if not found then
            raise exception using errcode = 'P0001', message = 'Không tìm thấy nhân viên cần cập nhật.';
        end if;
        employee_before := pg_catalog.to_jsonb(employee_row);
        update public.employees
        set code = employee_code,
            name = pg_catalog.btrim(p_name),
            phone = case when p_phone is null then phone else nullif(pg_catalog.btrim(p_phone), '') end,
            job_title = pg_catalog.btrim(p_job_title),
            shift_name = case when p_shift_name is null then shift_name else nullif(pg_catalog.btrim(p_shift_name), '') end,
            base_salary = p_base_salary,
            updated_at = pg_catalog.now()
        where id = p_employee_id
        returning * into employee_row;
        employee_id := employee_row.id;
        perform private.write_audit_log('employee.updated', 'employee', employee_row.id::text, employee_before, pg_catalog.to_jsonb(employee_row), null);
    end if;

    return employee_id;
exception
    when unique_violation then
        raise exception using errcode = 'P0001', message = 'Mã nhân viên đã được sử dụng.';
end;
$$;
