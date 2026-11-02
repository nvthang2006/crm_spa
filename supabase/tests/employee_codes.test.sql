begin;

insert into auth.users (id) values ('a0000000-0000-4000-8000-000000000003');
insert into public.app_access (user_id) values ('a0000000-0000-4000-8000-000000000003');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000003';

do $$
declare
    employee_id bigint;
    first_code text;
    next_code text;
    expected_number bigint;
begin
    select greatest(
        (select last_value + case when is_called then 1 else 0 end from private.employee_code_seq),
        coalesce(max(pg_catalog.substring(code, '^NV([0-9]+)$')::numeric), 0) + 1
    )::bigint into expected_number from public.employees where code ~ '^NV[0-9]+$';
    employee_id := public.save_employee(null, '', 'Nhân viên tự động', null, 'Kỹ thuật viên', 'Ca sáng', 0);
    select code into first_code from public.employees where id = employee_id;
    assert first_code = 'NV' || lpad(expected_number::text, greatest(3, length(expected_number::text)), '0'), 'Mã đầu tiên phải tiếp nối dãy hiện có';

    perform public.save_employee(employee_id, first_code, 'Tên đã cập nhật', null, 'Kỹ thuật viên', 'Ca sáng', 100);
    assert (select code = first_code and name = 'Tên đã cập nhật' from public.employees where id = employee_id), 'Sửa hồ sơ phải giữ mã';
    assert (select count(*) = 2 from public.audit_logs where subject_type = 'employee' and subject_id = employee_id::text), 'Thêm và sửa phải ghi audit';

    update public.employees set deleted_at = now() where id = employee_id;
    employee_id := public.save_employee(null, null, 'Nhân viên tiếp theo', null, 'Kỹ thuật viên', 'Ca sáng', 0);
    select code into next_code from public.employees where id = employee_id;
    assert substring(next_code, 3)::bigint = expected_number + 1, 'Không dùng lại mã hồ sơ đã xóa mềm';

    perform public.save_employee(null, 'LEGACY-TEST', 'Nhân viên mã cũ', null, 'Kỹ thuật viên', 'Ca sáng', 0);
    assert exists(select 1 from public.employees where code = 'LEGACY-TEST'), 'Tương thích client cũ nhập mã';

    perform public.save_employee(null, 'NV999', 'Nhân viên mã lớn', null, 'Kỹ thuật viên', 'Ca sáng', 0);
    employee_id := public.save_employee(null, '   ', 'Nhân viên vượt 999', null, 'Kỹ thuật viên', 'Ca sáng', 0);
    assert (select substring(code, 3)::bigint >= 1000 and length(code) >= 6 from public.employees where id = employee_id), 'Không cắt cụt mã sau NV999';

    begin
        perform public.save_employee(employee_id, '', 'Thiếu mã', null, 'Kỹ thuật viên', 'Ca sáng', 0);
        raise exception 'Không được chấp nhận mã trống khi sửa';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Thông tin nhân viên chưa hợp lệ.%';
    end;
    begin
        perform public.save_employee(null, '', '', null, 'Kỹ thuật viên', 'Ca sáng', 0);
        raise exception 'Không được bỏ kiểm tra họ tên';
    exception when sqlstate 'P0001' then
        assert sqlerrm like 'Thông tin nhân viên chưa hợp lệ.%';
    end;

    assert not has_sequence_privilege('authenticated', 'private.employee_code_seq', 'usage'), 'Client không được tự cấp số';
    assert not has_function_privilege('anon', 'public.save_employee(bigint,text,text,text,text,text,bigint)', 'execute'), 'Ẩn danh không được lưu';
    perform set_config('request.jwt.claim.sub', 'a0000000-0000-4000-8000-000000000004', true);
    begin
        perform public.save_employee(null, '', 'Không có quyền', null, 'Kỹ thuật viên', 'Ca sáng', 0);
        raise exception 'Phải từ chối tài khoản không có quyền';
    exception when insufficient_privilege then
        assert sqlerrm = 'Tài khoản không có quyền quản lý.';
    end;
end;
$$;

rollback;
