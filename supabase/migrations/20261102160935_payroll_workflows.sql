-- Payroll workflow RPCs for generating, locking payroll, and creating/reviewing adjustments
-- All operations are performed by manager accounts and write audit logs.

create or replace function public.generate_payroll(
    p_period_start date,
    p_period_end date
)
returns public.payroll_periods
language plpgsql
security definer
set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    period_code text;
    emp_record record;
    v_base_salary bigint;
    v_work_days integer;
    v_commission bigint;
    v_net_pay bigint;
    v_total_base bigint := 0;
    v_total_commission bigint := 0;
    v_total_net bigint := 0;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    if p_period_start is null or p_period_end is null or p_period_end < p_period_start then
        raise exception using errcode = 'P0001', message = 'Khoảng thời gian kỳ lương không hợp lệ.';
    end if;

    period_code := 'BL-' || pg_catalog.replace(p_period_start::text, '-', '');

    -- Check if period exists for these dates
    select * into period_row
    from public.payroll_periods
    where period_start = p_period_start and period_end = p_period_end
    for update;

    if period_row.id is null then
        if exists (
            select 1 from public.payroll_periods
            where code = period_code and (period_start <> p_period_start or period_end <> p_period_end)
        ) then
            raise exception using errcode = 'P0001', message = 'Mã kỳ lương đã được dùng cho khoảng ngày khác.';
        end if;

        insert into public.payroll_periods (code, period_start, period_end, status)
        values (period_code, p_period_start, p_period_end, 'draft')
        on conflict (code) do update set updated_at = pg_catalog.now()
        returning * into period_row;
    end if;

    if period_row.status = 'locked' then
        raise exception using errcode = 'P0001', message = 'Kỳ lương đã khóa, không thể tính lại.';
    end if;

    -- Delete old draft lines
    delete from public.payroll_lines where payroll_period_id = period_row.id;

    -- Loop through active employees
    for emp_record in
        select id, coalesce(base_salary, 0) as base_salary
        from public.employees
        where status = 'active' and deleted_at is null
        order by id
    loop
        v_base_salary := emp_record.base_salary;

        -- Count work days in attendance
        select count(*) into v_work_days
        from public.attendance_records
        where employee_id = emp_record.id
          and attendance_date between p_period_start and p_period_end
          and status in ('present', 'late');

        -- Sum pending commissions in period
        select coalesce(sum(amount), 0) into v_commission
        from public.commission_entries
        where employee_id = emp_record.id
          and status = 'pending'
          and occurred_at between (p_period_start::text || ' 00:00:00')::timestamptz
                             and (p_period_end::text || ' 23:59:59')::timestamptz;

        v_net_pay := v_base_salary + v_commission;

        insert into public.payroll_lines (
            payroll_period_id, employee_id, base_salary, work_days,
            commission_amount, tips, deductions, net_pay, status
        ) values (
            period_row.id, emp_record.id, v_base_salary, v_work_days,
            v_commission, 0, 0, v_net_pay, 'draft'
        );

        v_total_base := v_total_base + v_base_salary;
        v_total_commission := v_total_commission + v_commission;
        v_total_net := v_total_net + v_net_pay;
    end loop;

    -- Apply approved adjustments if any exist
    update public.payroll_periods
    set total_base_salary = v_total_base,
        total_commission = v_total_commission,
        total_net_pay = v_total_net,
        updated_at = pg_catalog.now()
    where id = period_row.id
    returning * into period_row;

    -- Recalculate with any existing approved adjustments
    perform public.recalculate_payroll(period_row.id);

    select * into period_row from public.payroll_periods where id = period_row.id;

    perform private.write_audit_log(
        'payroll.generated', 'payroll_period', period_row.id::text,
        null, pg_catalog.to_jsonb(period_row), 'Tính bảng lương kỳ ' || period_code
    );

    return period_row;
end;
$$;
revoke all on function public.generate_payroll(date, date) from public, anon;
grant execute on function public.generate_payroll(date, date) to authenticated;


create or replace function public.recalculate_payroll(
    p_period_id bigint
)
returns public.payroll_periods
language plpgsql
security definer
set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    line_row public.payroll_lines%rowtype;
    v_tips bigint;
    v_deductions bigint;
    v_net bigint;
    v_total_net bigint := 0;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    select * into period_row
    from public.payroll_periods
    where id = p_period_id
    for update;

    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy kỳ lương.';
    end if;

    if period_row.status = 'locked' then
        raise exception using errcode = 'P0001', message = 'Kỳ lương đã khóa, không thể tính lại.';
    end if;

    for line_row in
        select * from public.payroll_lines where payroll_period_id = period_row.id for update
    loop
        -- Sum approved bonuses/tips for this employee
        select coalesce(sum(amount), 0) into v_tips
        from public.payroll_adjustments
        where payroll_period_id = period_row.id
          and employee_id = line_row.employee_id
          and status = 'approved'
          and type in ('bonus', 'tip');

        -- Sum approved deductions for this employee
        select coalesce(sum(amount), 0) into v_deductions
        from public.payroll_adjustments
        where payroll_period_id = period_row.id
          and employee_id = line_row.employee_id
          and status = 'approved'
          and type = 'deduction';

        v_net := greatest(0, line_row.base_salary + line_row.commission_amount + v_tips - v_deductions);

        update public.payroll_lines
        set tips = v_tips,
            deductions = v_deductions,
            net_pay = v_net,
            updated_at = pg_catalog.now()
        where id = line_row.id;

        v_total_net := v_total_net + v_net;
    end loop;

    update public.payroll_periods
    set total_net_pay = v_total_net,
        updated_at = pg_catalog.now()
    where id = period_row.id
    returning * into period_row;

    return period_row;
end;
$$;
revoke all on function public.recalculate_payroll(bigint) from public, anon;
grant execute on function public.recalculate_payroll(bigint) to authenticated;


create or replace function public.lock_payroll(
    p_period_id bigint
)
returns public.payroll_periods
language plpgsql
security definer
set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    before_row jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    select * into period_row
    from public.payroll_periods
    where id = p_period_id
    for update;

    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy kỳ lương.';
    end if;

    if period_row.status = 'locked' then
        return period_row;
    end if;

    if not exists (select 1 from public.payroll_lines where payroll_period_id = period_row.id) then
        raise exception using errcode = 'P0001', message = 'Chưa có dòng lương để chốt.';
    end if;

    if exists (
        select 1 from public.payroll_adjustments
        where payroll_period_id = period_row.id and status = 'pending'
    ) then
        raise exception using errcode = 'P0001', message = 'Cần duyệt hoặc từ chối toàn bộ phiếu điều chỉnh trước khi chốt.';
    end if;

    before_row := pg_catalog.to_jsonb(period_row);

    update public.payroll_periods
    set status = 'locked',
        locked_by = auth.uid(),
        locked_at = pg_catalog.now(),
        updated_at = pg_catalog.now()
    where id = period_row.id
    returning * into period_row;

    update public.payroll_lines
    set status = 'locked',
        updated_at = pg_catalog.now()
    where payroll_period_id = period_row.id;

    update public.commission_entries
    set status = 'locked',
        updated_at = pg_catalog.now()
    where employee_id in (select employee_id from public.payroll_lines where payroll_period_id = period_row.id)
      and status = 'pending'
      and occurred_at between (period_row.period_start::text || ' 00:00:00')::timestamptz
                         and (period_row.period_end::text || ' 23:59:59')::timestamptz;

    perform private.write_audit_log(
        'payroll.locked', 'payroll_period', period_row.id::text,
        before_row, pg_catalog.to_jsonb(period_row), 'Khóa kỳ lương ' || period_row.code
    );

    return period_row;
end;
$$;
revoke all on function public.lock_payroll(bigint) from public, anon;
grant execute on function public.lock_payroll(bigint) to authenticated;


create or replace function public.create_payroll_adjustment(
    p_period_id bigint,
    p_employee_id bigint,
    p_type text,
    p_amount bigint,
    p_reason text
)
returns public.payroll_adjustments
language plpgsql
security definer
set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    adjustment_row public.payroll_adjustments%rowtype;
    adj_code text;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    select * into period_row
    from public.payroll_periods
    where id = p_period_id;

    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy kỳ lương.';
    end if;

    if period_row.status = 'locked' then
        raise exception using errcode = 'P0001', message = 'Kỳ lương đã khóa, không thể tạo phiếu điều chỉnh.';
    end if;

    if p_type not in ('bonus', 'deduction') or p_amount <= 0 or pg_catalog.btrim(coalesce(p_reason, '')) = '' then
        raise exception using errcode = 'P0001', message = 'Phiếu điều chỉnh cần loại (thưởng/khấu trừ), số tiền dương và lý do.';
    end if;

    if not exists (select 1 from public.employees where id = p_employee_id and deleted_at is null) then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy nhân viên.';
    end if;

    adj_code := 'DC-' || pg_catalog.to_char(pg_catalog.now(), 'YYYYMMDDHH24MISS') || '-' || pg_catalog.upper(pg_catalog.substr(pg_catalog.md5(pg_catalog.random()::text), 1, 4));

    insert into public.payroll_adjustments (
        payroll_period_id, employee_id, created_by, code, type, amount, status, reason
    ) values (
        p_period_id, p_employee_id, auth.uid(), adj_code, p_type, p_amount, 'pending', pg_catalog.btrim(p_reason)
    )
    returning * into adjustment_row;

    perform private.write_audit_log(
        'payroll.adjustment_created', 'payroll_adjustment', adjustment_row.id::text,
        null, pg_catalog.to_jsonb(adjustment_row), p_reason
    );

    return adjustment_row;
end;
$$;
revoke all on function public.create_payroll_adjustment(bigint, bigint, text, bigint, text) from public, anon;
grant execute on function public.create_payroll_adjustment(bigint, bigint, text, bigint, text) to authenticated;


create or replace function public.review_payroll_adjustment(
    p_adjustment_id bigint,
    p_approve boolean,
    p_reason text default null
)
returns public.payroll_adjustments
language plpgsql
security definer
set search_path = ''
as $$
declare
    adjustment_row public.payroll_adjustments%rowtype;
    period_row public.payroll_periods%rowtype;
    before_row jsonb;
    v_action text;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;

    select * into adjustment_row
    from public.payroll_adjustments
    where id = p_adjustment_id
    for update;

    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy phiếu điều chỉnh.';
    end if;

    select * into period_row
    from public.payroll_periods
    where id = adjustment_row.payroll_period_id;

    if period_row.status = 'locked' or adjustment_row.status <> 'pending' then
        raise exception using errcode = 'P0001', message = 'Phiếu điều chỉnh không còn ở trạng thái chờ duyệt hoặc kỳ lương đã bị khóa.';
    end if;

    before_row := pg_catalog.to_jsonb(adjustment_row);

    update public.payroll_adjustments
    set status = case when p_approve then 'approved' else 'rejected' end,
        reviewed_by = auth.uid(),
        reviewed_at = pg_catalog.now(),
        updated_at = pg_catalog.now()
    where id = adjustment_row.id
    returning * into adjustment_row;

    if p_approve then
        perform public.recalculate_payroll(adjustment_row.payroll_period_id);
    end if;

    v_action := case when p_approve then 'payroll.adjustment_approved' else 'payroll.adjustment_rejected' end;

    perform private.write_audit_log(
        v_action, 'payroll_adjustment', adjustment_row.id::text,
        before_row, pg_catalog.to_jsonb(adjustment_row), p_reason
    );

    return adjustment_row;
end;
$$;
revoke all on function public.review_payroll_adjustment(bigint, boolean, text) from public, anon;
grant execute on function public.review_payroll_adjustment(bigint, boolean, text) to authenticated;
