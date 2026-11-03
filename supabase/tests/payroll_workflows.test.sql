begin;
set local timezone = 'UTC';
insert into auth.users (id) values ('a0000000-0000-4000-8000-000000000008');
insert into public.app_access (user_id) values ('a0000000-0000-4000-8000-000000000008');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000008';

do $$
#variable_conflict use_variable
declare
    employee_id bigint;
    former_employee_id bigint;
    period_row public.payroll_periods%rowtype;
    adjustment_row public.payroll_adjustments%rowtype;
    line_id bigint;
    commission_id bigint;
begin
    insert into public.employees (code, name, job_title, base_salary)
    values ('PAY-TEST', 'Nhân viên kiểm thử lương', 'Kỹ thuật viên', 1000000) returning id into employee_id;
    insert into public.employees (code, name, job_title, status, base_salary)
    values ('PAY-FORMER', 'Nhân viên đã nghỉ', 'Kỹ thuật viên', 'inactive', 2000000) returning id into former_employee_id;
    insert into public.attendance_records (employee_id, attendance_date, status) values
        (employee_id, '2090-11-01', 'present'), (employee_id, '2090-11-02', 'late'),
        (employee_id, '2090-11-03', 'absent'), (employee_id, '2090-10-31', 'present');
    insert into public.commission_entries (employee_id, basis, calculation_type, rate, amount, occurred_at, status) values
        (employee_id, 'completion', 'fixed', 100, 100, '2090-11-01 00:00:00+07', 'pending'),
        (employee_id, 'completion', 'fixed', 200, 200, '2090-11-30 23:59:59.999999+07', 'pending'),
        (employee_id, 'completion', 'fixed', 400, 400, '2090-12-01 00:00:00+07', 'pending'),
        (employee_id, 'completion', 'fixed', 800, 800, '2090-10-31 23:59:59.999999+07', 'pending'),
        (employee_id, 'completion', 'fixed', 1600, 1600, '2090-11-15 12:00:00+07', 'reversed'),
        (former_employee_id, 'completion', 'fixed', 50, 50, '2090-11-15 12:00:00+07', 'pending');
    period_row := public.generate_payroll('2090-11-01', '2090-11-30');
    assert period_row.total_commission = 350, 'Hoa hồng theo ngày Việt Nam dùng cận trên exclusive, bỏ reversed và gồm người đã nghỉ';
    assert period_row.total_base_salary = 1000000, 'Không cấp lương cứng mới cho người đã nghỉ';
    assert (select work_days = 2 and commission_amount = 300 from public.payroll_lines where payroll_period_id = period_row.id and public.payroll_lines.employee_id = employee_id), 'Ngày công chỉ present/late, không đếm absent hay ngày ngoài kỳ';
    select id into line_id from public.payroll_lines where payroll_period_id = period_row.id and public.payroll_lines.employee_id = employee_id;
    perform public.generate_payroll('2090-11-01', '2090-11-30');
    assert (select id = line_id from public.payroll_lines where payroll_period_id = period_row.id and public.payroll_lines.employee_id = employee_id), 'Tính lại upsert giữ nguyên ID dòng lương';
    begin
        perform public.generate_payroll('2090-11-30', '2090-12-20');
        raise exception 'Không được tạo kỳ trùng';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Khoảng ngày bị trùng%';
    end;
    begin
        perform public.generate_payroll('2090-12-02', '2090-12-01');
        raise exception 'Không được tạo khoảng đảo';
    exception when sqlstate 'P0001' then
        assert sqlerrm = 'Khoảng thời gian kỳ lương không hợp lệ.';
    end;
    begin
        perform public.create_payroll_adjustment(period_row.id, employee_id, null, 100, 'Thưởng');
        raise exception 'Không được nhận loại null';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Phiếu điều chỉnh cần loại%';
    end;
    begin
        perform public.create_payroll_adjustment(period_row.id, employee_id, 'bonus', null, 'Thưởng');
        raise exception 'Không được nhận tiền null';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Phiếu điều chỉnh cần loại%';
    end;
    adjustment_row := public.create_payroll_adjustment(period_row.id, employee_id, 'bonus', 20000, 'Thưởng ca');
    begin
        perform public.lock_payroll(period_row.id);
        raise exception 'Không được chốt khi còn phiếu chờ';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Cần duyệt hoặc từ chối%';
    end;
    begin
        perform public.review_payroll_adjustment(adjustment_row.id, null);
        raise exception 'Không được duyệt null';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Vui lòng chọn duyệt%';
    end;
    perform public.review_payroll_adjustment(adjustment_row.id, true);
    assert (select tips = 20000 from public.payroll_lines where id = line_id), 'Duyệt thưởng cập nhật lương ngay';
    insert into public.commission_entries (employee_id, basis, calculation_type, rate, amount, occurred_at)
    values (employee_id, 'completion', 'fixed', 1000, 1000, '2090-11-20 12:00:00+07') returning id into commission_id;
    period_row := public.lock_payroll(period_row.id);
    assert period_row.total_commission = 1350, 'Chốt tính lại hoa hồng mới phát sinh sau bản nháp';
    assert period_row.total_net_pay = 1021350, 'Thực lĩnh gồm lương cứng, hoa hồng và thưởng';
    assert (select status = 'locked' from public.commission_entries where id = commission_id), 'Hoa hồng trong snapshot được khóa';
    assert (select status = 'pending' from public.commission_entries where amount = 400 and public.commission_entries.employee_id = employee_id), 'Cận trên ngày tiếp theo không bị khóa';
    assert (public.lock_payroll(period_row.id)).id = period_row.id, 'Chốt lại trả đúng kỳ, không ghi đúp';
    assert (select count(*) = 1 from public.audit_logs where action = 'payroll.locked' and subject_id = period_row.id::text), 'Chốt lại không ghi trùng audit';
    begin
        perform public.generate_payroll('2090-11-01', '2090-11-30');
        raise exception 'Không được tính kỳ khóa';
    exception when sqlstate 'P0001' then
        assert sqlerrm = 'Kỳ lương đã khóa, không thể tính lại.';
    end;
    perform pg_catalog.set_config('request.jwt.claim.sub', '', true);
    begin
        perform public.generate_payroll('2091-01-01', '2091-01-31');
        raise exception 'Không được gọi khi chưa đăng nhập';
    exception when sqlstate '42501' then
        assert sqlerrm = 'Tài khoản không có quyền quản lý.';
    end;
    assert not has_function_privilege('anon', 'public.generate_payroll(date,date)', 'execute'), 'Ẩn danh không được execute';
    assert has_function_privilege('authenticated', 'public.generate_payroll(date,date)', 'execute'), 'RPC mở cho authenticated, kiểm tra manager trong hàm';
end;
$$;
rollback;
