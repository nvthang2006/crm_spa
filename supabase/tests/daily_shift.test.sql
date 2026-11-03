begin;
set local search_path = public, extensions;
select no_plan();

insert into auth.users(id) values ('a0000000-0000-4000-8000-000000000025'), ('a0000000-0000-4000-8000-000000000026');
insert into public.app_access(user_id) values ('a0000000-0000-4000-8000-000000000025');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000025';

insert into public.customers(id, code, name) values
    (825001, 'DAILY-CUSTOMER', 'Khách kiểm thử nhập ca'),
    (825002, 'DAILY-OTHER', 'Khách hàng khác');
insert into public.employees(id, code, name, job_title, status) values
    (825001, 'DAILY-PERFORMER', 'Nhân viên thực hiện', 'Kỹ thuật viên', 'active'),
    (825002, 'DAILY-BENEFICIARY', 'Người nhận khoản riêng', 'Tư vấn', 'active'),
    (825003, 'DAILY-FALLBACK', 'Nhân viên mức chung', 'Kỹ thuật viên', 'active'),
    (825004, 'DAILY-INACTIVE', 'Nhân viên tạm ngưng', 'Kỹ thuật viên', 'inactive');
insert into public.offerings(id, code, name, type, price, sessions_total) values
    (825001, 'DAILY-SERVICE', 'Dịch vụ nhập ca', 'service', 100000, 1),
    (825002, 'DAILY-PACKAGE', 'Gói liệu trình nhập ca', 'package', 1200000, 6);
insert into public.commission_rules(offering_id, beneficiary_employee_id, beneficiary_type, basis, calculation_type, value, starts_on, ends_on) values
    (825001, null, 'primary_technician', 'completion', 'fixed', 10000, '2020-01-01', null),
    (825001, 825002, 'specific_employee', 'payment', 'fixed', 5000, '2020-01-01', null),
    (825001, 825001, 'performer_employee', 'completion', 'percentage', 20, '2026-10-01', '2026-10-31'),
    (825002, 825001, 'performer_employee', 'completion', 'percentage', 10, '2020-01-01', null);
insert into public.customer_packages(id, customer_id, offering_id, code, purchased_on, starts_on, expires_on, sessions_total, unit_price) values
    (825001, 825001, 825002, 'DAILY-CARD', '2026-09-01', '2026-09-01', '2026-12-31', 6, 1200000),
    (825002, 825002, 825002, 'DAILY-OTHER-CARD', '2026-09-01', '2026-09-01', '2026-12-31', 6, 1200000),
    (825003, 825001, 825002, 'DAILY-FUTURE-CARD', '2026-09-01', '2026-11-01', '2026-12-31', 6, 1200000),
    (825004, 825001, 825002, 'DAILY-EXPIRED-CARD', '2026-09-01', '2026-09-01', '2026-10-01', 6, 1200000),
    (825005, 825001, 825002, 'DAILY-LAST-SESSION', '2026-09-01', '2026-09-01', '2026-12-31', 1, 1200000);
create temp table daily_test_results(label text primary key, response jsonb);

create function pg_temp.record_shift(patch jsonb default '{}'::jsonb)
returns jsonb language plpgsql as $$
declare
    input jsonb := jsonb_build_object(
        'request_id', gen_random_uuid(), 'customer_id', 825001, 'offering_id', 825001,
        'employee_id', 825001, 'classification', 'retail', 'collected', 100000,
        'work_date', '2026-10-12', 'payment_method', 'cash'
    ) || patch;
begin
    return public.record_daily_shift(
        (input->>'request_id')::uuid, (input->>'customer_id')::bigint,
        (input->>'offering_id')::bigint, (input->>'employee_id')::bigint,
        input->>'classification', (input->>'collected')::bigint, (input->>'work_date')::date,
        input->>'payment_method', (input->>'customer_package_id')::bigint,
        input->>'commission_type', (input->>'commission_value')::numeric, input->>'notes'
    );
end;
$$;

insert into daily_test_results values ('personal', pg_temp.record_shift('{"request_id":"b1000001-0000-4000-8000-000000000025"}'));
select is((select (response->>'commission_amount')::bigint from daily_test_results where label='personal'), 25000::bigint,
    'Mức riêng đúng người thực hiện thay mức chung và giữ khoản người được chỉ định');
select is((select count(*) from public.commission_entries where invoice_id=(select (response->'invoice'->>'id')::bigint from daily_test_results where label='personal')), 2::bigint,
    'Một ca chỉ ghi mức riêng và khoản được chỉ định, không ghi thêm mức KTV chính');
select is((select amount from public.commission_entries where employee_id=825001 and invoice_id=(select (response->'invoice'->>'id')::bigint from daily_test_results where label='personal')), 20000::bigint,
    'Hoa hồng người thực hiện lưu theo mức riêng 20%');
select is((select (response->'invoice'->>'paid_at')::timestamptz at time zone 'Asia/Ho_Chi_Minh' from daily_test_results where label='personal')::date,
    '2026-10-12'::date, 'Ngày kế toán dùng múi giờ cơ sở và ngày làm việc được chọn');
select is((select amount from public.payments where invoice_id=(select (response->'invoice'->>'id')::bigint from daily_test_results where label='personal')), 100000::bigint,
    'Ca thành công ghi đúng tiền thanh toán');

insert into daily_test_results values ('fallback', pg_temp.record_shift('{"work_date":"2026-09-30"}'));
select is((select (response->>'commission_amount')::bigint from daily_test_results where label='fallback'), 15000::bigint,
    'Ngày ngoài hiệu lực mức riêng dùng mức chung và khoản chỉ định');
insert into daily_test_results values ('other-performer', pg_temp.record_shift('{"employee_id":825003}'));
select is((select (response->>'commission_amount')::bigint from daily_test_results where label='other-performer'), 15000::bigint,
    'Mức riêng của nhân viên khác không áp dụng cho người thực hiện hiện tại');

insert into daily_test_results values ('fixed', pg_temp.record_shift('{"commission_type":"fixed","commission_value":50000}'));
select is((select (response->>'commission_amount')::bigint from daily_test_results where label='fixed'), 55000::bigint,
    'Mức nhập riêng theo số tiền thay mức người thực hiện, vẫn giữ khoản chỉ định');
insert into daily_test_results values ('zero', pg_temp.record_shift('{"commission_type":"percentage","commission_value":0}'));
select is((select amount from public.commission_entries where employee_id=825001 and invoice_id=(select (response->'invoice'->>'id')::bigint from daily_test_results where label='zero')), 0::bigint,
    'Nhập 0 là mức thay thế hợp lệ, không rơi về cấu hình mặc định');
insert into daily_test_results values ('percentage', pg_temp.record_shift('{"collected":100001,"commission_type":"percentage","commission_value":12.5}'));
select is((select (response->>'commission_amount')::bigint from daily_test_results where label='percentage'), 17500::bigint,
    'Tỷ lệ nhập riêng tính từ tiền thực thu và làm tròn về đồng');

select is(pg_temp.record_shift('{"request_id":"b1000001-0000-4000-8000-000000000025"}'),
    (select response from daily_test_results where label='personal'), 'Gửi lại cùng nội dung trả đúng kết quả đã lưu');
select is((select count(*) from public.invoices where daily_entry_key='shift-b1000001-0000-4000-8000-000000000025'), 1::bigint,
    'Gửi lại không tạo hóa đơn thứ hai');
select is((select count(*) from public.audit_logs where action='daily_shift.recorded' and subject_id=(select response->'invoice'->>'id' from daily_test_results where label='personal')), 1::bigint,
    'Gửi lại không nhân đôi nhật ký nghiệp vụ');
select throws_ok($$select pg_temp.record_shift('{"request_id":"b1000001-0000-4000-8000-000000000025","collected":1}')$$,
    '22023', 'Mã yêu cầu đã được dùng với nội dung khác.', 'Không cho dùng lại mã yêu cầu với số tiền khác');

select throws_ok(format('select pg_temp.record_shift(%L::jsonb)', patch), 'P0001', null, label)
from (values
    ('{"classification":null}'::jsonb, 'Chặn phân loại null'),
    ('{"collected":null}'::jsonb, 'Chặn tiền null'),
    ('{"collected":-1}'::jsonb, 'Chặn tiền âm'),
    ('{"work_date":null}'::jsonb, 'Chặn ngày null'),
    ('{"work_date":"infinity"}'::jsonb, 'Chặn ngày vô cực'),
    ('{"work_date":"-infinity"}'::jsonb, 'Chặn ngày âm vô cực'),
    ('{"payment_method":null}'::jsonb, 'Chặn phương thức null'),
    ('{"commission_type":"fixed"}'::jsonb, 'Chặn loại hoa hồng thiếu mức'),
    ('{"commission_type":"percentage","commission_value":"NaN"}'::jsonb, 'Chặn hoa hồng NaN'),
    ('{"commission_type":"percentage","commission_value":101}'::jsonb, 'Chặn phần trăm trên 100'),
    ('{"commission_type":"percentage","commission_value":1.234}'::jsonb, 'Chặn phần trăm quá hai chữ số thập phân'),
    ('{"commission_type":"fixed","commission_value":1.5}'::jsonb, 'Chặn số tiền hoa hồng không nguyên'),
    ('{"employee_id":825004}'::jsonb, 'Chặn nhân viên tạm ngưng'),
    ('{"customer_id":null}'::jsonb, 'Chặn khách hàng null'),
    ('{"offering_id":null}'::jsonb, 'Chặn dịch vụ null'),
    ('{"customer_package_id":825001}'::jsonb, 'Ca lẻ không được trừ thẻ'),
    ('{"classification":"package","offering_id":825002,"customer_package_id":825002}'::jsonb, 'Chặn thẻ của khách hàng khác'),
    ('{"classification":"package","offering_id":825001,"customer_package_id":825001}'::jsonb, 'Chặn thẻ không khớp dịch vụ'),
    ('{"classification":"package","offering_id":825002,"customer_package_id":825003}'::jsonb, 'Chặn thẻ chưa đến ngày bắt đầu'),
    ('{"classification":"package","offering_id":825002,"customer_package_id":825004}'::jsonb, 'Chặn thẻ đã hết hạn')
) as cases(patch, label);
select throws_ok($$select pg_temp.record_shift('{"request_id":null}')$$, '22023', 'Mã yêu cầu hóa đơn chưa hợp lệ.', 'Chặn mã gửi yêu cầu null');

insert into daily_test_results values ('package', pg_temp.record_shift('{"request_id":"b1000002-0000-4000-8000-000000000025","classification":"package","offering_id":825002,"customer_package_id":825001,"collected":0}'));
select is((select (response->>'commission_amount')::bigint from daily_test_results where label='package'), 20000::bigint,
    'Ca gói doanh thu 0 vẫn tính phần trăm trên giá một buổi');
select is((select sessions_used from public.customer_packages where id=825001), 1, 'Ca gói trừ đúng một buổi');
select is((select count(*) from public.customer_packages where customer_id=825001), 4::bigint,
    'Sử dụng thẻ không tạo thêm thẻ mới');
select is(pg_temp.record_shift('{"request_id":"b1000002-0000-4000-8000-000000000025","classification":"package","offering_id":825002,"customer_package_id":825001,"collected":0}'),
    (select response from daily_test_results where label='package'), 'Gửi lại ca gói trả kết quả cũ');
select is((select sessions_used from public.customer_packages where id=825001), 1, 'Gửi lại ca gói không trừ thêm buổi');
select is((select count(*) from public.package_usages where customer_package_id=825001), 1::bigint,
    'Gửi lại ca gói không tạo usage thứ hai');
insert into daily_test_results values ('last-session', pg_temp.record_shift('{"classification":"package","offering_id":825002,"customer_package_id":825005,"collected":0}'));
select is((select status from public.customer_packages where id=825005), 'completed', 'Buổi cuối chuyển thẻ sang hoàn thành');
select throws_ok($$select pg_temp.record_shift('{"classification":"package","offering_id":825002,"customer_package_id":825005,"collected":0}')$$,
    'P0001', null, 'Thẻ hết buổi không thể sử dụng thêm');

insert into public.payroll_periods(code, period_start, period_end, status) values ('DAILY-LOCKED', '2026-08-01', '2026-08-31', 'locked');
select throws_ok($$select pg_temp.record_shift('{"work_date":"2026-08-10"}')$$, 'P0001',
    'Ngày làm việc thuộc kỳ lương đã khóa. Vui lòng chọn ngày thuộc kỳ chưa khóa.', 'Không cho ghi ca vào ngày thuộc kỳ lương đã khóa');

create function pg_temp.fail_daily_audit() returns trigger language plpgsql as $$
begin
    if new.action='daily_shift.recorded' and new.reason='TEST-ROLLBACK' then
        raise exception using errcode='P0001', message='Lỗi ghi nhật ký mô phỏng';
    end if;
    return new;
end;
$$;
create trigger daily_test_fail_audit before insert on public.audit_logs for each row execute function pg_temp.fail_daily_audit();
select throws_ok($$select pg_temp.record_shift('{"request_id":"b1000003-0000-4000-8000-000000000025","classification":"package","offering_id":825002,"customer_package_id":825001,"collected":0,"notes":"TEST-ROLLBACK"}')$$,
    'P0001', 'Lỗi ghi nhật ký mô phỏng', 'Lỗi cuối giao dịch được trả về thay vì lưu ca dở dang');
select is((select sessions_used from public.customer_packages where id=825001), 1, 'Lỗi sau trừ buổi hoàn tác số buổi');
select is((select count(*) from public.invoices where daily_entry_key='shift-b1000003-0000-4000-8000-000000000025'), 0::bigint,
    'Lỗi sau tạo phiếu hoàn tác phiếu và mọi bảng tài chính');
select is((select count(*) from private.idempotency_requests where request_id='b1000003-0000-4000-8000-000000000025'), 0::bigint,
    'Lỗi hoàn tác claim idempotency, không để yêu cầu chưa hoàn tất');

select ok(not has_function_privilege('anon', 'public.record_daily_shift(uuid,bigint,bigint,bigint,text,bigint,date,text,bigint,text,numeric,text)', 'EXECUTE'),
    'Anon không được execute RPC ghi ca');
select ok(has_function_privilege('authenticated', 'public.record_daily_shift(uuid,bigint,bigint,bigint,text,bigint,date,text,bigint,text,numeric,text)', 'EXECUTE'),
    'Authenticated có thể gọi RPC, quyền quản lý được kiểm tra trong hàm');
set local role authenticated;
select is((public.record_daily_shift('b1000004-0000-4000-8000-000000000025', 825001, 825001, 825001, 'retail', 100000, '2026-10-12', 'cash')->>'commission_amount')::bigint,
    25000::bigint, 'Manager gọi bằng database role authenticated thực tế, không cần quyền superuser');
reset role;
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000026';
select throws_ok($$select pg_temp.record_shift()$$, '42501', 'Tài khoản không có quyền quản lý.', 'Tài khoản đã đăng nhập nhưng không có app_access bị từ chối');
set local request.jwt.claim.sub = '';
select throws_ok($$select pg_temp.record_shift()$$, '42501', 'Tài khoản không có quyền quản lý.', 'Chưa đăng nhập bị từ chối trước mọi ghi dữ liệu');

select * from finish();
rollback;
