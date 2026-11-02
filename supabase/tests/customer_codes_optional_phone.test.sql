begin;
insert into auth.users (id) values ('a0000000-0000-4000-8000-000000000010');
insert into public.app_access (user_id) values ('a0000000-0000-4000-8000-000000000010');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000010';

do $$
declare
    customer_row public.customers%rowtype;
    first_code text;
begin
    perform public.save_customer(null, 'KH-999', 'Khách mã cũ', '0900000000', 'old@example.com', 'Thành viên', null, 'Địa chỉ cũ', null, null, null, null, null, null, false);
    select * into customer_row from public.save_customer(null, '', 'Khách tự động', null, null, 'Thành viên', null, null, null, null, null, null, null, null, false);
    first_code := customer_row.code;
    assert first_code ~ '^KH[0-9]+$' and substring(first_code, 3)::bigint >= 1000 and customer_row.phone is null, 'Cấp mã tiếp nối cả KH-999 và lưu số điện thoại null';
    perform public.save_customer(customer_row.id, 'KH-KHAC', 'Khách đã sửa', '0900000001', 'keep@example.com', 'Vàng', null, 'Địa chỉ cần giữ', null, null, null, null, null, null, false);
    select * into customer_row from public.save_customer(customer_row.id, '', 'Khách xóa số điện thoại', '   ', null, 'Vàng', null, null, null, null, null, null, null, null, false);
    assert customer_row.code = first_code and customer_row.phone is null and customer_row.email = 'keep@example.com' and customer_row.address = 'Địa chỉ cần giữ', 'Sửa giữ mã, email, địa chỉ cũ và cho phép xóa số điện thoại về null';
    assert (select count(*) = 3 from public.audit_logs where action = 'customer.saved' and subject_id = customer_row.id::text), 'Thêm sửa vẫn ghi audit';
    update public.customers set deleted_at = now() where id = customer_row.id;
    select * into customer_row from public.save_customer(null, null, 'Khách tiếp theo', '', null, 'Thành viên', null, null, null, null, null, null, null, null, false);
    assert substring(customer_row.code, 3)::bigint = substring(first_code, 3)::bigint + 1, 'Không dùng lại mã hồ sơ đã lưu trữ';
    begin
        perform public.save_customer(null, '', '', null, null, 'Thành viên', null, null, null, null, null, null, null, null, false);
        raise exception 'Phải từ chối thiếu tên';
    exception when sqlstate 'P0001' then
        assert sqlerrm = 'Mã, tên, số điện thoại hoặc hạng thành viên chưa hợp lệ.';
    end;
    begin
        perform public.save_customer(null, '', 'Số quá dài', repeat('1',33), null, 'Thành viên', null, null, null, null, null, null, null, null, false);
        raise exception 'Phải từ chối số điện thoại dài quá giới hạn';
    exception when sqlstate 'P0001' then
        assert sqlerrm = 'Mã, tên, số điện thoại hoặc hạng thành viên chưa hợp lệ.';
    end;
    assert not has_sequence_privilege('authenticated', 'private.customer_code_seq', 'usage'), 'Client không được tự lấy số';
    perform set_config('request.jwt.claim.sub', 'a0000000-0000-4000-8000-000000000011', true);
    begin
        perform public.save_customer(null, '', 'Không có quyền', null, null, 'Thành viên', null, null, null, null, null, null, null, null, false);
        raise exception 'Phải từ chối người không có quyền';
    exception when insufficient_privilege then
        assert sqlerrm = 'Tài khoản không có quyền quản lý.';
    end;
end;
$$;
rollback;
