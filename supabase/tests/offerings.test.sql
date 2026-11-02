begin;

select plan(11);

insert into auth.users (
    id, aud, role, email, encrypted_password, email_confirmed_at,
    raw_app_meta_data, raw_user_meta_data, created_at, updated_at
)
values (
    'a0000000-0000-4000-8000-000000000002', 'authenticated', 'authenticated',
    'offerings-workflow-test@sunnybeauty.local', '', pg_catalog.now(),
    '{"provider":"email","providers":["email"]}'::jsonb, '{}'::jsonb,
    pg_catalog.now(), pg_catalog.now()
);
insert into public.app_access (user_id) values ('a0000000-0000-4000-8000-000000000002');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000002';
set local request.jwt.claims = '{"sub":"a0000000-0000-4000-8000-000000000002","role":"authenticated"}';

select public.save_offering(
    null, ' test-offering ', 'Dịch vụ ban đầu', 'service', 60, 10000, 1, 'active', false, 'Mô tả ban đầu'
);
select is(
    (select code || '|' || name || '|' || price::text from public.offerings where code = 'TEST-OFFERING'),
    'TEST-OFFERING|Dịch vụ ban đầu|10000',
    'Tạo dịch vụ qua RPC được ghi vào Supabase và có thể đọc lại'
);
select is(
    (select count(*) from public.audit_logs where action = 'offering.created' and subject_type = 'offering'),
    1::bigint,
    'Tạo dịch vụ ghi một sự kiện audit'
);

select public.save_offering(
    (select id from public.offerings where code = 'TEST-OFFERING'),
    'TEST-OFFERING', 'Dịch vụ đã cập nhật', 'service', 75, 12000, 1, 'inactive', false, 'Mô tả cập nhật'
);
select is(
    (
        select name || '|' || duration_minutes::text || '|' || price::text || '|' || status || '|' || coalesce(description, '')
        from public.offerings where code = 'TEST-OFFERING'
    ),
    'Dịch vụ đã cập nhật|75|12000|inactive|Mô tả cập nhật',
    'Cập nhật dịch vụ giữ đúng các giá trị đã lưu'
);
select is(
    (select count(*) from public.audit_logs where action in ('offering.created', 'offering.updated') and subject_type = 'offering'),
    2::bigint,
    'Cập nhật dịch vụ tạo thêm một sự kiện audit'
);
select throws_ok(
    $$select public.save_offering(null, 'TEST-OFFERING', 'Mã bị trùng', 'service', 60, 10000, 1, 'active', false, null)$$,
    'P0001',
    'Mã dịch vụ đã được sử dụng.',
    'RPC từ chối mã dịch vụ bị trùng'
);
select throws_ok(
    $$select public.save_offering(null, 'TEST-NEGATIVE', 'Giá âm', 'service', 60, -1, 1, 'active', false, null)$$,
    'P0001',
    'Thông tin dịch vụ hoặc gói liệu trình chưa hợp lệ.',
    'RPC từ chối giá dịch vụ âm'
);

select ok(
    has_function_privilege('authenticated', 'public.save_offering(bigint,text,text,text,integer,bigint,integer,text,boolean,text)', 'execute'),
    'Tài khoản quản lý có thể gọi RPC lưu dịch vụ'
);
select ok(
    not has_function_privilege('anon', 'public.save_offering(bigint,text,text,text,integer,bigint,integer,text,boolean,text)', 'execute'),
    'Tài khoản ẩn danh không thể gọi RPC lưu dịch vụ'
);
select ok(
    not has_table_privilege('authenticated', 'public.offerings', 'insert')
    and not has_table_privilege('authenticated', 'public.offerings', 'update')
    and not has_table_privilege('authenticated', 'public.offerings', 'delete')
    and not has_table_privilege('authenticated', 'public.service_categories', 'insert')
    and not has_table_privilege('authenticated', 'public.service_categories', 'update')
    and not has_table_privilege('authenticated', 'public.service_categories', 'delete'),
    'Client chỉ có thể ghi danh mục dịch vụ qua RPC'
);
select ok(
    has_table_privilege('authenticated', 'public.offerings', 'select')
    and has_table_privilege('authenticated', 'public.service_categories', 'select'),
    'Client vẫn có thể đọc danh mục theo chính sách RLS'
);
select ok(
    not has_sequence_privilege('authenticated', 'public.offerings_id_seq', 'usage')
    and not has_sequence_privilege('authenticated', 'public.service_categories_id_seq', 'usage'),
    'Client không thể tự cấp số ID bằng sequence của danh mục'
);

select * from finish();
rollback;
