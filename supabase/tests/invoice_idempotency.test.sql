begin;

select plan(17);

insert into auth.users (
    id, aud, role, email, encrypted_password, email_confirmed_at,
    raw_app_meta_data, raw_user_meta_data, created_at, updated_at
)
values (
    'a0000000-0000-4000-8000-000000000001', 'authenticated', 'authenticated',
    'invoice-idempotency-test@sunnybeauty.local', '', pg_catalog.now(),
    '{"provider":"email","providers":["email"]}'::jsonb, '{}'::jsonb,
    pg_catalog.now(), pg_catalog.now()
);
insert into public.app_access (user_id) values ('a0000000-0000-4000-8000-000000000001');
insert into public.offerings (code, name, type, duration_minutes, price)
values ('IDEM-SERVICE', 'Dịch vụ kiểm thử idempotency', 'service', 60, 10000);
insert into public.customers (code, name, phone, membership_tier) values
    ('IDEM-CREATE', 'Khách kiểm thử tạo hóa đơn', '0900000011', 'Thành viên'),
    ('IDEM-PAY', 'Khách kiểm thử thanh toán', '0900000012', 'Thành viên'),
    ('IDEM-VOID', 'Khách kiểm thử hủy hóa đơn', '0900000013', 'Thành viên'),
    ('IDEM-REFUND', 'Khách kiểm thử hoàn tiền', '0900000014', 'Thành viên');

set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000001';
set local request.jwt.claims = '{"sub":"a0000000-0000-4000-8000-000000000001","role":"authenticated"}';

select is(
    (
        public.create_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000001',
            (select id from public.customers where code = 'IDEM-CREATE'),
            (select id from public.offerings where code = 'IDEM-SERVICE'),
            null, null, 1000
        )->'invoice'->>'id'
    ),
    (
        public.create_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000001',
            (select id from public.customers where code = 'IDEM-CREATE'),
            (select id from public.offerings where code = 'IDEM-SERVICE'),
            null, null, 1000
        )->'invoice'->>'id'
    ),
    'Gửi lại yêu cầu tạo hóa đơn trả về đúng hóa đơn ban đầu'
);
select is(
    (select count(*) from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-CREATE')),
    1::bigint,
    'Yêu cầu tạo hóa đơn gửi lại không tạo bản ghi trùng'
);
select throws_ok(
    $$select public.create_invoice_idempotent(
        'b0000000-0000-4000-8000-000000000001',
        (select id from public.customers where code = 'IDEM-CREATE'),
        (select id from public.offerings where code = 'IDEM-SERVICE'),
        null, null, 1001
    )$$,
    '22023',
    'Mã yêu cầu đã được dùng với nội dung khác.',
    'Không cho phép tái sử dụng mã yêu cầu tạo hóa đơn với dữ liệu khác'
);

select public.create_invoice_idempotent(
    'b0000000-0000-4000-8000-000000000004',
    (select id from public.customers where code = 'IDEM-PAY'),
    (select id from public.offerings where code = 'IDEM-SERVICE'),
    null, null, 1000
);
select is(
    (
        public.pay_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000002',
            (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-PAY')),
            'cash', null
        )->'payment'->>'id'
    ),
    (
        public.pay_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000002',
            (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-PAY')),
            'cash', null
        )->'payment'->>'id'
    ),
    'Gửi lại yêu cầu thu tiền trả về đúng phiếu thu ban đầu'
);
select is(
    (select count(*) from public.payments where invoice_id = (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-PAY'))),
    1::bigint,
    'Yêu cầu thu tiền gửi lại không tạo phiếu thu trùng'
);
select is(
    (select total_spend from public.customers where code = 'IDEM-PAY'),
    9000::bigint,
    'Gửi lại yêu cầu thu tiền không cộng chi tiêu khách lần thứ hai'
);
select throws_ok(
    $$select public.pay_invoice_idempotent(
        'b0000000-0000-4000-8000-000000000002',
        (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-PAY')),
        'vietqr', 'GIAO-DICH-KHAC'
    )$$,
    '22023',
    'Mã yêu cầu đã được dùng với nội dung khác.',
    'Không cho phép tái sử dụng mã yêu cầu thu tiền với dữ liệu khác'
);

select public.create_invoice_idempotent(
    'b0000000-0000-4000-8000-000000000005',
    (select id from public.customers where code = 'IDEM-VOID'),
    (select id from public.offerings where code = 'IDEM-SERVICE'),
    null, null, 1000
);
select is(
    (
        public.void_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000003',
            (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-VOID')),
            'Khách yêu cầu hủy hóa đơn kiểm thử'
        )->>'id'
    ),
    (
        public.void_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000003',
            (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-VOID')),
            'Khách yêu cầu hủy hóa đơn kiểm thử'
        )->>'id'
    ),
    'Gửi lại yêu cầu hủy trả về đúng hóa đơn đã hủy'
);
select is(
    (select count(*) from public.audit_logs where action = 'invoice.voided' and subject_id = (select id::text from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-VOID'))),
    1::bigint,
    'Gửi lại yêu cầu hủy không ghi audit trùng'
);

select public.create_invoice_idempotent(
    'b0000000-0000-4000-8000-000000000006',
    (select id from public.customers where code = 'IDEM-REFUND'),
    (select id from public.offerings where code = 'IDEM-SERVICE'),
    null, null, 1000
);
select public.pay_invoice_idempotent(
    'b0000000-0000-4000-8000-000000000007',
    (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-REFUND')),
    'cash', null
);
select is(
    (
        public.refund_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000008',
            (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-REFUND')),
            'cash', 'Khách yêu cầu hoàn tiền kiểm thử', null
        )->'refund'->>'id'
    ),
    (
        public.refund_invoice_idempotent(
            'b0000000-0000-4000-8000-000000000008',
            (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-REFUND')),
            'cash', 'Khách yêu cầu hoàn tiền kiểm thử', null
        )->'refund'->>'id'
    ),
    'Gửi lại yêu cầu hoàn tiền trả về đúng phiếu hoàn ban đầu'
);
select is(
    (select count(*) from public.invoice_refunds where invoice_id = (select id from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-REFUND'))),
    1::bigint,
    'Yêu cầu hoàn tiền gửi lại không tạo phiếu hoàn trùng'
);
select is(
    (select total_spend from public.customers where code = 'IDEM-REFUND'),
    0::bigint,
    'Gửi lại yêu cầu hoàn tiền không trừ chi tiêu khách lần thứ hai'
);
select is(
    (select count(*) from public.audit_logs where action = 'invoice.refunded' and subject_id = (select id::text from public.invoices where customer_id = (select id from public.customers where code = 'IDEM-REFUND'))),
    1::bigint,
    'Gửi lại yêu cầu hoàn tiền không ghi audit trùng'
);

select ok(
    has_function_privilege('authenticated', 'public.create_invoice_idempotent(uuid,bigint,bigint,bigint,bigint,bigint)', 'execute')
    and has_function_privilege('authenticated', 'public.pay_invoice_idempotent(uuid,bigint,text,text)', 'execute')
    and has_function_privilege('authenticated', 'public.void_invoice_idempotent(uuid,bigint,text)', 'execute')
    and has_function_privilege('authenticated', 'public.refund_invoice_idempotent(uuid,bigint,text,text,text)', 'execute'),
    'Các RPC idempotency chỉ mở cho tài khoản đã đăng nhập'
);
select ok(
    not has_function_privilege('anon', 'public.create_invoice_idempotent(uuid,bigint,bigint,bigint,bigint,bigint)', 'execute')
    and not has_function_privilege('anon', 'public.pay_invoice_idempotent(uuid,bigint,text,text)', 'execute')
    and not has_function_privilege('anon', 'public.void_invoice_idempotent(uuid,bigint,text)', 'execute')
    and not has_function_privilege('anon', 'public.refund_invoice_idempotent(uuid,bigint,text,text,text)', 'execute'),
    'Tài khoản ẩn danh không thể gọi các RPC idempotency'
);
select ok(
    not has_table_privilege('authenticated', 'private.idempotency_requests', 'select'),
    'Ứng dụng không thể đọc trực tiếp dữ liệu yêu cầu idempotency'
);
select ok(
    not has_function_privilege('authenticated', 'private.claim_invoice_request(text,uuid,jsonb)', 'execute')
    and not has_function_privilege('authenticated', 'private.finish_invoice_request(text,uuid,jsonb)', 'execute'),
    'Ứng dụng không thể gọi trực tiếp hàm nội bộ idempotency'
);

select * from finish();
rollback;
