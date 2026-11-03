-- Kỳ lương dùng cùng khóa giao dịch để không chốt/tính trùng khoảng ngày.
create or replace function public.recalculate_payroll(p_period_id bigint)
returns public.payroll_periods
language plpgsql security definer set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    employee_row record;
    line_base bigint;
    line_days integer;
    line_commission bigint;
    line_tips bigint;
    line_deductions bigint;
    period_from timestamptz;
    period_until timestamptz;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    perform pg_catalog.pg_advisory_xact_lock(20261102, 1);
    select * into period_row from public.payroll_periods where id = p_period_id for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy kỳ lương.';
    end if;
    if period_row.status = 'locked' then
        raise exception using errcode = 'P0001', message = 'Kỳ lương đã khóa, không thể tính lại.';
    end if;
    if exists (
        select 1 from public.payroll_periods other_period
        where other_period.id <> period_row.id
          and other_period.period_start <= period_row.period_end
          and other_period.period_end >= period_row.period_start
    ) then
        raise exception using errcode = 'P0001', message = 'Khoảng ngày bị trùng với kỳ lương đã có. Vui lòng chọn kỳ hiện có hoặc khoảng ngày khác.';
    end if;
    period_from := period_row.period_start::timestamp at time zone 'Asia/Ho_Chi_Minh';
    period_until := (period_row.period_end + 1)::timestamp at time zone 'Asia/Ho_Chi_Minh';

    -- Giữ dòng cũ và quyền lợi đã ghi nhận, kể cả nhân viên đã ngừng làm việc.
    for employee_row in
        select employee.id, employee.status, coalesce(employee.base_salary, 0) as base_salary,
               employee.deleted_at, existing_line.base_salary as existing_base
        from public.employees employee
        left join public.payroll_lines existing_line
          on existing_line.employee_id = employee.id and existing_line.payroll_period_id = period_row.id
        where (employee.status = 'active' and employee.deleted_at is null)
           or existing_line.id is not null
           or exists (
               select 1 from public.commission_entries commission
               where commission.employee_id = employee.id and commission.status = 'pending'
                 and commission.occurred_at >= period_from and commission.occurred_at < period_until
           )
           or exists (
               select 1 from public.payroll_adjustments adjustment
               where adjustment.payroll_period_id = period_row.id and adjustment.employee_id = employee.id
                 and adjustment.status = 'approved'
           )
        order by employee.id
    loop
        line_base := case when employee_row.status = 'active' and employee_row.deleted_at is null
            then employee_row.base_salary else coalesce(employee_row.existing_base, 0) end;
        select count(*)::integer into line_days from public.attendance_records
        where employee_id = employee_row.id
          and attendance_date between period_row.period_start and period_row.period_end
          and status in ('present', 'late');
        select coalesce(sum(amount), 0)::bigint into line_commission from public.commission_entries
        where employee_id = employee_row.id and status = 'pending'
          and occurred_at >= period_from and occurred_at < period_until;
        select coalesce(sum(amount) filter (where type in ('bonus', 'tip')), 0)::bigint,
               coalesce(sum(amount) filter (where type = 'deduction'), 0)::bigint
        into line_tips, line_deductions from public.payroll_adjustments
        where payroll_period_id = period_row.id and employee_id = employee_row.id and status = 'approved';
        insert into public.payroll_lines (
            payroll_period_id, employee_id, base_salary, work_days, commission_amount,
            tips, deductions, net_pay, status
        ) values (
            period_row.id, employee_row.id, line_base, line_days, line_commission,
            line_tips, line_deductions,
            greatest(0, line_base + line_commission + line_tips - line_deductions), 'draft'
        )
        on conflict (payroll_period_id, employee_id) do update
        set base_salary = excluded.base_salary, work_days = excluded.work_days,
            commission_amount = excluded.commission_amount, tips = excluded.tips,
            deductions = excluded.deductions, net_pay = excluded.net_pay,
            status = 'draft', updated_at = pg_catalog.now();
    end loop;
    update public.payroll_periods period
    set total_base_salary = totals.base_salary, total_commission = totals.commission,
        total_net_pay = totals.net_pay, updated_at = pg_catalog.now()
    from (
        select coalesce(sum(base_salary), 0)::bigint as base_salary,
               coalesce(sum(commission_amount), 0)::bigint as commission,
               coalesce(sum(net_pay), 0)::bigint as net_pay
        from public.payroll_lines where payroll_period_id = period_row.id
    ) totals
    where period.id = period_row.id returning period.* into period_row;
    return period_row;
end;
$$;

create or replace function public.generate_payroll(p_period_start date, p_period_end date)
returns public.payroll_periods
language plpgsql security definer set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    period_code text;
    before_row jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    perform pg_catalog.pg_advisory_xact_lock(20261102, 1);
    if p_period_start is null or p_period_end is null or p_period_end < p_period_start then
        raise exception using errcode = 'P0001', message = 'Khoảng thời gian kỳ lương không hợp lệ.';
    end if;
    select * into period_row from public.payroll_periods
    where period_start = p_period_start and period_end = p_period_end for update;
    if period_row.status = 'locked' then
        raise exception using errcode = 'P0001', message = 'Kỳ lương đã khóa, không thể tính lại.';
    end if;
    if exists (
        select 1 from public.payroll_periods other_period
        where other_period.id is distinct from period_row.id
          and other_period.period_start <= p_period_end and other_period.period_end >= p_period_start
    ) then
        raise exception using errcode = 'P0001', message = 'Khoảng ngày bị trùng với kỳ lương đã có. Vui lòng chọn kỳ hiện có hoặc khoảng ngày khác.';
    end if;
    before_row := case when period_row.id is null then null else pg_catalog.to_jsonb(period_row) end;
    if period_row.id is null then
        period_code := 'BL-' || pg_catalog.replace(p_period_start::text, '-', '');
        insert into public.payroll_periods (code, period_start, period_end, status)
        values (period_code, p_period_start, p_period_end, 'draft') returning * into period_row;
    end if;
    period_row := public.recalculate_payroll(period_row.id);
    perform private.write_audit_log('payroll.generated', 'payroll_period', period_row.id::text,
        before_row, pg_catalog.to_jsonb(period_row), 'Tính bảng lương kỳ ' || period_row.code);
    return period_row;
end;
$$;

create or replace function public.lock_payroll(p_period_id bigint)
returns public.payroll_periods
language plpgsql security definer set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    before_row jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    perform pg_catalog.pg_advisory_xact_lock(20261102, 1);
    -- Chặn thay đổi hoa hồng trong lúc lấy snapshot và khóa kỳ.
    lock table public.commission_entries in share row exclusive mode;
    select * into period_row from public.payroll_periods where id = p_period_id for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy kỳ lương.';
    end if;
    if period_row.status = 'locked' then return period_row; end if;
    if exists (select 1 from public.payroll_adjustments where payroll_period_id = p_period_id and status = 'pending') then
        raise exception using errcode = 'P0001', message = 'Cần duyệt hoặc từ chối toàn bộ phiếu điều chỉnh trước khi chốt.';
    end if;
    before_row := pg_catalog.to_jsonb(period_row);
    period_row := public.recalculate_payroll(period_row.id);
    if not exists (select 1 from public.payroll_lines where payroll_period_id = p_period_id) then
        raise exception using errcode = 'P0001', message = 'Chưa có dòng lương để chốt.';
    end if;
    update public.commission_entries set status = 'locked', updated_at = pg_catalog.now()
    where employee_id in (select employee_id from public.payroll_lines where payroll_period_id = p_period_id)
      and status = 'pending'
      and occurred_at >= period_row.period_start::timestamp at time zone 'Asia/Ho_Chi_Minh'
      and occurred_at < (period_row.period_end + 1)::timestamp at time zone 'Asia/Ho_Chi_Minh';
    update public.payroll_lines set status = 'locked', updated_at = pg_catalog.now() where payroll_period_id = p_period_id;
    update public.payroll_periods set status = 'locked', locked_by = auth.uid(),
        locked_at = pg_catalog.now(), updated_at = pg_catalog.now()
    where id = p_period_id returning * into period_row;
    perform private.write_audit_log('payroll.locked', 'payroll_period', period_row.id::text,
        before_row, pg_catalog.to_jsonb(period_row), 'Khóa kỳ lương ' || period_row.code);
    return period_row;
end;
$$;

create or replace function public.create_payroll_adjustment(
    p_period_id bigint, p_employee_id bigint, p_type text, p_amount bigint, p_reason text
)
returns public.payroll_adjustments
language plpgsql security definer set search_path = ''
as $$
declare
    period_row public.payroll_periods%rowtype;
    adjustment_row public.payroll_adjustments%rowtype;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    perform pg_catalog.pg_advisory_xact_lock(20261102, 1);
    select * into period_row from public.payroll_periods where id = p_period_id for update;
    if not found then raise exception using errcode = 'P0001', message = 'Không tìm thấy kỳ lương.'; end if;
    if period_row.status = 'locked' then
        raise exception using errcode = 'P0001', message = 'Kỳ lương đã khóa, không thể tạo phiếu điều chỉnh.';
    end if;
    if p_type is null or p_type not in ('bonus', 'deduction') or p_amount is null or p_amount <= 0
       or pg_catalog.btrim(coalesce(p_reason, '')) = '' then
        raise exception using errcode = 'P0001', message = 'Phiếu điều chỉnh cần loại (thưởng/khấu trừ), số tiền dương và lý do.';
    end if;
    if not exists (select 1 from public.payroll_lines where payroll_period_id = p_period_id and employee_id = p_employee_id) then
        raise exception using errcode = 'P0001', message = 'Nhân viên chưa có trong kỳ lương. Vui lòng tính lại bảng lương trước khi điều chỉnh.';
    end if;
    insert into public.payroll_adjustments (payroll_period_id, employee_id, created_by, code, type, amount, status, reason)
    values (p_period_id, p_employee_id, auth.uid(),
        'DC-' || pg_catalog.to_char(pg_catalog.now(), 'YYYYMMDDHH24MISS') || '-' || pg_catalog.upper(pg_catalog.substr(pg_catalog.md5(pg_catalog.random()::text), 1, 4)),
        p_type, p_amount, 'pending', pg_catalog.btrim(p_reason)) returning * into adjustment_row;
    perform private.write_audit_log('payroll.adjustment_created', 'payroll_adjustment', adjustment_row.id::text,
        null, pg_catalog.to_jsonb(adjustment_row), p_reason);
    return adjustment_row;
end;
$$;

create or replace function public.review_payroll_adjustment(p_adjustment_id bigint, p_approve boolean, p_reason text default null)
returns public.payroll_adjustments
language plpgsql security definer set search_path = ''
as $$
declare
    adjustment_row public.payroll_adjustments%rowtype;
    period_row public.payroll_periods%rowtype;
    before_row jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    perform pg_catalog.pg_advisory_xact_lock(20261102, 1);
    if p_approve is null then raise exception using errcode = 'P0001', message = 'Vui lòng chọn duyệt hoặc từ chối phiếu điều chỉnh.'; end if;
    select * into adjustment_row from public.payroll_adjustments where id = p_adjustment_id;
    if not found then raise exception using errcode = 'P0001', message = 'Không tìm thấy phiếu điều chỉnh.'; end if;
    select * into period_row from public.payroll_periods where id = adjustment_row.payroll_period_id for update;
    select * into adjustment_row from public.payroll_adjustments where id = p_adjustment_id for update;
    if period_row.status = 'locked' or adjustment_row.status <> 'pending' then
        raise exception using errcode = 'P0001', message = 'Phiếu điều chỉnh không còn ở trạng thái chờ duyệt hoặc kỳ lương đã bị khóa.';
    end if;
    before_row := pg_catalog.to_jsonb(adjustment_row);
    update public.payroll_adjustments
    set status = case when p_approve then 'approved' else 'rejected' end,
        reviewed_by = auth.uid(), reviewed_at = pg_catalog.now(), updated_at = pg_catalog.now()
    where id = p_adjustment_id returning * into adjustment_row;
    perform public.recalculate_payroll(period_row.id);
    perform private.write_audit_log(case when p_approve then 'payroll.adjustment_approved' else 'payroll.adjustment_rejected' end,
        'payroll_adjustment', adjustment_row.id::text, before_row, pg_catalog.to_jsonb(adjustment_row), p_reason);
    return adjustment_row;
end;
$$;

revoke all on function public.generate_payroll(date,date), public.recalculate_payroll(bigint),
    public.lock_payroll(bigint), public.create_payroll_adjustment(bigint,bigint,text,bigint,text),
    public.review_payroll_adjustment(bigint,boolean,text) from public, anon;
grant execute on function public.generate_payroll(date,date), public.recalculate_payroll(bigint),
    public.lock_payroll(bigint), public.create_payroll_adjustment(bigint,bigint,text,bigint,text),
    public.review_payroll_adjustment(bigint,boolean,text) to authenticated;
notify pgrst, 'reload schema';
