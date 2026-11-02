begin;
insert into auth.users (id) values ('a0000000-0000-4000-8000-000000000007');
insert into public.app_access (user_id) values ('a0000000-0000-4000-8000-000000000007');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000007';

do $$
declare
    offering_id bigint;
    first_code text;
    next_code text;
begin
    perform public.save_offering(null, 'DV-999', 'Mã cũ có dấu gạch', 'service', 60, 10000, 1, 'active', false, null);
    offering_id := public.save_offering(null, '', 'Dịch vụ tự động', 'service', 60, null, 1, 'active', false, null);
    select code into first_code from public.offerings where id = offering_id;
    assert first_code ~ '^DV[0-9]+$' and substring(first_code, 3)::bigint >= 1000, 'Tiếp nối mã cũ và không cắt cụt sau DV999';
    perform public.save_offering(offering_id, 'MA-KHAC', 'Tên đã sửa', 'service', 75, 20000, 1, 'inactive', false, null);
    assert (select code = first_code and name = 'Tên đã sửa' and price = 20000 and duration_minutes = 75 from public.offerings where id = offering_id), 'Sửa dịch vụ giữ nguyên mã ngay cả khi client gửi mã khác';
    perform public.save_offering(offering_id, '', 'Mã ẩn khi sửa', 'service', 60, null, 1, 'active', false, null);
    assert (select code = first_code from public.offerings where id = offering_id), 'Sửa không cần nhập mã';
    update public.offerings set deleted_at = now() where id = offering_id;
    offering_id := public.save_offering(null, null, 'Gói tự động', 'package', 60, 500000, 5, 'active', false, null);
    select code into next_code from public.offerings where id = offering_id;
    assert substring(next_code, 3)::bigint = substring(first_code, 3)::bigint + 1, 'Gói liệu trình dùng chung dãy mã, không dùng lại mã đã lưu trữ';
    assert (select count(*) = 1 from public.audit_logs where action = 'offering.created' and subject_id = offering_id::text), 'Cấp mã vẫn ghi audit';
    begin
        perform public.save_offering(null, '', '', 'service', 60, 0, 1, 'active', false, null);
        raise exception 'Phải từ chối thiếu tên';
    exception when sqlstate 'P0001' then
        assert sqlerrm = 'Thông tin dịch vụ hoặc gói liệu trình chưa hợp lệ.';
    end;
    assert not has_sequence_privilege('authenticated', 'private.offering_code_seq', 'usage'), 'Client không được cấp mã trực tiếp';
    perform set_config('request.jwt.claim.sub', 'a0000000-0000-4000-8000-000000000008', true);
    begin
        perform public.save_offering(null, '', 'Không có quyền', 'service', 60, 0, 1, 'active', false, null);
        raise exception 'Phải từ chối tài khoản ngoài danh sách quản lý';
    exception when insufficient_privilege then
        assert sqlerrm = 'Tài khoản không có quyền quản lý.';
    end;
end;
$$;
rollback;
