-- Business configuration and team writes stay behind manager-only RPCs.

create table public.business_settings (
    id smallint primary key default 1 check (id = 1),
    business_name varchar(120) not null,
    address varchar(240) not null default '',
    phone varchar(32),
    time_zone varchar(64) not null default 'Asia/Ho_Chi_Minh',
    updated_at timestamptz not null default now(),
    updated_by uuid references auth.users(id) on delete set null
);

alter table public.business_settings enable row level security;
create policy manager_read_business_settings on public.business_settings
    for select to authenticated using (private.has_manager_access());
revoke all on public.business_settings from public, anon, authenticated;
grant select on public.business_settings to authenticated;

insert into public.business_settings (id, business_name, address, phone, time_zone)
values (1, 'Sunny Beauty', '103 Phố Trung Liệt', null, 'Asia/Ho_Chi_Minh')
on conflict (id) do nothing;

revoke all on public.employees, public.commission_rules from public, anon, authenticated;
grant select on public.employees, public.commission_rules to authenticated;

create or replace function public.save_business_settings(
    p_business_name text,
    p_address text,
    p_phone text
)
returns void
language plpgsql
security definer
set search_path = ''
as $$
declare
    settings_before jsonb;
    settings_after jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_business_name is null or pg_catalog.char_length(pg_catalog.btrim(p_business_name)) not between 1 and 120
        or p_address is null or pg_catalog.char_length(pg_catalog.btrim(p_address)) > 240
        or pg_catalog.char_length(coalesce(p_phone, '')) > 32 then
        raise exception using errcode = 'P0001', message = 'Tên cơ sở, địa chỉ hoặc số điện thoại chưa hợp lệ.';
    end if;

    select pg_catalog.to_jsonb(settings) into settings_before
    from public.business_settings settings where id = 1 for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Chưa có cấu hình cơ sở để cập nhật.';
    end if;

    update public.business_settings
    set business_name = pg_catalog.btrim(p_business_name),
        address = pg_catalog.btrim(p_address),
        phone = nullif(pg_catalog.btrim(p_phone), ''),
        updated_at = pg_catalog.now(),
        updated_by = auth.uid()
    where id = 1;

    select pg_catalog.to_jsonb(settings) into settings_after
    from public.business_settings settings where id = 1;
    perform private.write_audit_log('business_settings.updated', 'business_settings', '1', settings_before, settings_after, null);
end;
$$;
revoke all on function public.save_business_settings(text, text, text) from public, anon;
grant execute on function public.save_business_settings(text, text, text) to authenticated;

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
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_code is null or pg_catalog.char_length(pg_catalog.btrim(p_code)) not between 1 and 32
        or p_name is null or pg_catalog.char_length(pg_catalog.btrim(p_name)) not between 1 and 120
        or pg_catalog.char_length(coalesce(p_phone, '')) > 32
        or p_job_title is null or pg_catalog.char_length(pg_catalog.btrim(p_job_title)) not between 1 and 120
        or p_shift_name is null or pg_catalog.char_length(pg_catalog.btrim(p_shift_name)) not between 1 and 60
        or p_base_salary is null or p_base_salary < 0 then
        raise exception using errcode = 'P0001', message = 'Thông tin nhân viên chưa hợp lệ. Hãy kiểm tra mã, họ tên, chức vụ, ca làm và lương cứng.';
    end if;
    if exists (
        select 1 from public.employees
        where code = pg_catalog.btrim(p_code)
            and (p_employee_id is null or id <> p_employee_id)
    ) then
        raise exception using errcode = 'P0001', message = 'Mã nhân viên đã được sử dụng.';
    end if;

    if p_employee_id is null then
        insert into public.employees (code, name, phone, job_title, shift_name, base_salary, status)
        values (pg_catalog.btrim(p_code), pg_catalog.btrim(p_name), nullif(pg_catalog.btrim(p_phone), ''),
            pg_catalog.btrim(p_job_title), pg_catalog.btrim(p_shift_name), p_base_salary, 'active')
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
        set code = pg_catalog.btrim(p_code),
            name = pg_catalog.btrim(p_name),
            phone = nullif(pg_catalog.btrim(p_phone), ''),
            job_title = pg_catalog.btrim(p_job_title),
            shift_name = pg_catalog.btrim(p_shift_name),
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
revoke all on function public.save_employee(bigint, text, text, text, text, text, bigint) from public, anon;
grant execute on function public.save_employee(bigint, text, text, text, text, text, bigint) to authenticated;

create or replace function public.set_employee_status(p_employee_id bigint)
returns text
language plpgsql
security definer
set search_path = ''
as $$
declare
    employee_row public.employees%rowtype;
    employee_before jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    select * into employee_row from public.employees where id = p_employee_id and deleted_at is null for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy nhân viên cần cập nhật.';
    end if;
    employee_before := pg_catalog.to_jsonb(employee_row);
    update public.employees
    set status = case when employee_row.status = 'active' then 'inactive' else 'active' end,
        updated_at = pg_catalog.now()
    where id = p_employee_id
    returning * into employee_row;
    perform private.write_audit_log('employee.status_changed', 'employee', employee_row.id::text, employee_before, pg_catalog.to_jsonb(employee_row), null);
    return employee_row.status;
end;
$$;
revoke all on function public.set_employee_status(bigint) from public, anon;
grant execute on function public.set_employee_status(bigint) to authenticated;

create or replace function public.save_commission_rule(
    p_rule_id bigint,
    p_offering_id bigint,
    p_beneficiary_type text,
    p_beneficiary_employee_id bigint,
    p_basis text,
    p_calculation_type text,
    p_value numeric,
    p_starts_on date,
    p_ends_on date,
    p_is_active boolean,
    p_notes text
)
returns bigint
language plpgsql
security definer
set search_path = ''
as $$
declare
    rule_row public.commission_rules%rowtype;
    rule_before jsonb;
    rule_id bigint;
    actual_employee_id bigint;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_beneficiary_type is null or p_beneficiary_type not in ('primary_technician', 'support_technician', 'specific_employee', 'performer_employee')
        or p_basis is null or p_basis not in ('payment', 'completion')
        or p_calculation_type is null or p_calculation_type not in ('percentage', 'fixed')
        or p_value is null or p_value < 0 or (p_calculation_type = 'percentage' and p_value > 100)
        or p_starts_on is null or (p_ends_on is not null and p_ends_on < p_starts_on)
        or p_is_active is null or pg_catalog.char_length(coalesce(p_notes, '')) > 1000 then
        raise exception using errcode = 'P0001', message = 'Thông tin quy tắc hoa hồng chưa hợp lệ.';
    end if;
    if p_beneficiary_type in ('specific_employee', 'performer_employee') and p_beneficiary_employee_id is null then
        raise exception using errcode = 'P0001', message = 'Hãy chọn nhân sự nhận hoa hồng.';
    end if;

    if p_rule_id is not null then
        select * into rule_row from public.commission_rules where id = p_rule_id for update;
        if not found then
            raise exception using errcode = 'P0001', message = 'Không tìm thấy quy tắc hoa hồng cần cập nhật.';
        end if;
        rule_before := pg_catalog.to_jsonb(rule_row);
    end if;

    perform 1 from public.offerings where id = p_offering_id for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy dịch vụ hoặc gói liệu trình.';
    end if;
    if p_beneficiary_type in ('specific_employee', 'performer_employee') then
        select id into actual_employee_id
        from public.employees
        where id = p_beneficiary_employee_id
            and (p_beneficiary_type <> 'performer_employee' or (status = 'active' and deleted_at is null));
        if not found then
            raise exception using errcode = 'P0001', message = 'Nhân sự nhận hoa hồng không còn hoạt động.';
        end if;
    end if;
    if p_beneficiary_type = 'performer_employee' and p_is_active and exists (
        select 1 from public.commission_rules rule
        where rule.offering_id = p_offering_id
            and rule.beneficiary_type = 'performer_employee'
            and rule.beneficiary_employee_id = p_beneficiary_employee_id
            and rule.is_active
            and (p_rule_id is null or rule.id <> p_rule_id)
            and rule.starts_on <= coalesce(p_ends_on, 'infinity'::date)
            and coalesce(rule.ends_on, 'infinity'::date) >= p_starts_on
    ) then
        raise exception using errcode = 'P0001', message = 'Nhân viên này đã có mức hoa hồng riêng cho dịch vụ trong khoảng ngày đã chọn. Hãy điều chỉnh ngày hiệu lực hoặc tắt quy tắc cũ.';
    end if;

    if p_rule_id is null then
        insert into public.commission_rules (
            offering_id, beneficiary_employee_id, beneficiary_type, basis, calculation_type,
            value, starts_on, ends_on, is_active, notes
        ) values (
            p_offering_id,
            case when p_beneficiary_type in ('specific_employee', 'performer_employee') then p_beneficiary_employee_id else null end,
            p_beneficiary_type, p_basis, p_calculation_type, p_value, p_starts_on, p_ends_on, p_is_active,
            nullif(pg_catalog.btrim(p_notes), '')
        ) returning * into rule_row;
        rule_id := rule_row.id;
        perform private.write_audit_log('commission_rule.created', 'commission_rule', rule_row.id::text, null, pg_catalog.to_jsonb(rule_row), null);
    else
        update public.commission_rules
        set offering_id = p_offering_id,
            beneficiary_employee_id = case when p_beneficiary_type in ('specific_employee', 'performer_employee') then p_beneficiary_employee_id else null end,
            beneficiary_type = p_beneficiary_type,
            basis = p_basis,
            calculation_type = p_calculation_type,
            value = p_value,
            starts_on = p_starts_on,
            ends_on = p_ends_on,
            is_active = p_is_active,
            notes = nullif(pg_catalog.btrim(p_notes), ''),
            updated_at = pg_catalog.now()
        where id = p_rule_id
        returning * into rule_row;
        rule_id := rule_row.id;
        perform private.write_audit_log('commission_rule.updated', 'commission_rule', rule_row.id::text, rule_before, pg_catalog.to_jsonb(rule_row), null);
    end if;
    return rule_id;
end;
$$;
revoke all on function public.save_commission_rule(bigint, bigint, text, bigint, text, text, numeric, date, date, boolean, text) from public, anon;
grant execute on function public.save_commission_rule(bigint, bigint, text, bigint, text, text, numeric, date, date, boolean, text) to authenticated;

create or replace function public.set_commission_rule_active(p_rule_id bigint)
returns boolean
language plpgsql
security definer
set search_path = ''
as $$
declare
    rule_row public.commission_rules%rowtype;
    rule_before jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    select * into rule_row from public.commission_rules where id = p_rule_id for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy quy tắc hoa hồng cần cập nhật.';
    end if;
    perform 1 from public.offerings where id = rule_row.offering_id for update;
    rule_before := pg_catalog.to_jsonb(rule_row);

    if not rule_row.is_active and rule_row.beneficiary_type = 'performer_employee' and exists (
        select 1 from public.commission_rules other_rule
        where other_rule.offering_id = rule_row.offering_id
            and other_rule.beneficiary_type = 'performer_employee'
            and other_rule.beneficiary_employee_id = rule_row.beneficiary_employee_id
            and other_rule.is_active
            and other_rule.id <> rule_row.id
            and other_rule.starts_on <= coalesce(rule_row.ends_on, 'infinity'::date)
            and coalesce(other_rule.ends_on, 'infinity'::date) >= rule_row.starts_on
    ) then
        raise exception using errcode = 'P0001', message = 'Nhân viên này đã có mức hoa hồng riêng cho dịch vụ trong khoảng ngày đã chọn. Hãy điều chỉnh ngày hiệu lực hoặc tắt quy tắc cũ.';
    end if;

    update public.commission_rules
    set is_active = not rule_row.is_active, updated_at = pg_catalog.now()
    where id = p_rule_id
    returning * into rule_row;
    perform private.write_audit_log('commission_rule.status_changed', 'commission_rule', rule_row.id::text, rule_before, pg_catalog.to_jsonb(rule_row), null);
    return rule_row.is_active;
end;
$$;
revoke all on function public.set_commission_rule_active(bigint) from public, anon;
grant execute on function public.set_commission_rule_active(bigint) to authenticated;
