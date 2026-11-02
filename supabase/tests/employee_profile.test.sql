begin;

insert into auth.users (id) values ('a0000000-0000-4000-8000-000000000006');
insert into public.app_access (user_id) values ('a0000000-0000-4000-8000-000000000006');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000006';

do $$
declare
    employee_id bigint;
    employee_code text;
begin
    employee_id := public.save_employee(null, '', 'Hồ sơ tối giản', null, 'Kỹ thuật viên', null, null);
    select code into employee_code from public.employees where id = employee_id;
    assert (select base_salary is null and phone is null and shift_name is null and code ~ '^NV[0-9]+$' from public.employees where id = employee_id), 'Tạo mới cấp mã tự động, lưu đúng null cho thông tin tùy chọn';

    perform public.save_employee(employee_id, employee_code, 'Hồ sơ cũ', '0900000000', 'Quản lý', 'Ca sáng', 5000000);
    perform public.save_employee(employee_id, employee_code, 'Hồ sơ đã cập nhật', null, 'Kỹ thuật viên chính', null, null);
    assert (select phone = '0900000000' and shift_name = 'Ca sáng' and base_salary is null and job_title = 'Kỹ thuật viên chính' from public.employees where id = employee_id), 'Form mới giữ số điện thoại, ca cũ và có thể xóa lương về null';

    perform public.save_employee(employee_id, employee_code, 'Lương bằng không', null, 'Kỹ thuật viên', null, 0);
    assert (select base_salary = 0 from public.employees where id = employee_id), 'Phân biệt lương 0 với chưa thiết lập';

    begin
        perform public.save_employee(null, '', 'Lương âm', null, 'Kỹ thuật viên', null, -1);
        raise exception 'Không được chấp nhận lương âm';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Thông tin nhân viên chưa hợp lệ.%';
    end;
    begin
        perform public.save_employee(null, '', 'Thiếu vị trí', null, '', null, null);
        raise exception 'Vị trí vẫn bắt buộc';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Thông tin nhân viên chưa hợp lệ.%';
    end;

    assert (select is_nullable = 'YES' and column_default is null from information_schema.columns where table_schema = 'public' and table_name = 'employees' and column_name = 'base_salary'), 'Cột lương cho phép null và không mặc định thành 0';
end;
$$;

rollback;
