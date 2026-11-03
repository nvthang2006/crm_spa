-- Ghi nhận một ca và hoa hồng trong cùng giao dịch; gửi lại không tạo phiếu trùng.
create or replace function public.record_daily_shift(
    p_request_id uuid,
    p_customer_id bigint,
    p_offering_id bigint,
    p_employee_id bigint,
    p_classification text,
    p_collected bigint,
    p_work_date date,
    p_payment_method text,
    p_customer_package_id bigint default null,
    p_commission_type text default null,
    p_commission_value numeric default null,
    p_notes text default null
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    offering_row public.offerings%rowtype;
    invoice_row public.invoices%rowtype;
    package_row public.customer_packages%rowtype;
    rule_row public.commission_rules%rowtype;
    personal_rule_id bigint;
    recipient_id bigint;
    usage_id bigint;
    session_number integer;
    commission_base numeric;
    entry_amount bigint;
    total_commission bigint := 0;
    occurred_at timestamptz;
    saved_response jsonb;
    request_response jsonb;
    request_payload jsonb := pg_catalog.jsonb_build_object(
        'workflow', 'daily_shift', 'customer_id', p_customer_id, 'offering_id', p_offering_id,
        'employee_id', p_employee_id, 'classification', p_classification, 'collected', p_collected,
        'work_date', p_work_date, 'payment_method', p_payment_method,
        'customer_package_id', p_customer_package_id, 'commission_type', p_commission_type,
        'commission_value', p_commission_value, 'notes', p_notes
    );
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    saved_response := private.claim_invoice_request('create_invoice', p_request_id, request_payload);
    if saved_response is not null then
        return saved_response;
    end if;
    if p_classification is null or p_classification not in ('retail', 'package')
        or p_collected is null or p_collected < 0 or p_collected > 9007199254740991
        or p_work_date is null or not pg_catalog.isfinite(p_work_date)
        or p_payment_method is null or p_payment_method not in ('cash', 'vietqr', 'pos') then
        raise exception using errcode = 'P0001', message = 'Ngày làm việc, phân loại, tiền thực thu hoặc phương thức thanh toán không hợp lệ.';
    end if;
    if pg_catalog.char_length(coalesce(p_notes, '')) > 500 then
        raise exception using errcode = 'P0001', message = 'Ghi chú không được vượt quá 500 ký tự.';
    end if;
    if (p_commission_type is null) <> (p_commission_value is null)
        or (p_commission_type is not null and (
            p_commission_type not in ('percentage', 'fixed') or p_commission_value < 0
            or p_commission_value::text in ('NaN', 'Infinity', '-Infinity')
            or (p_commission_type = 'percentage' and (p_commission_value > 100 or p_commission_value <> pg_catalog.round(p_commission_value, 2)))
            or (p_commission_type = 'fixed' and (p_commission_value > 9999999999 or p_commission_value <> pg_catalog.trunc(p_commission_value)))
        )) then
        raise exception using errcode = 'P0001', message = 'Hoa hồng phải là tỷ lệ từ 0 đến 100% (tối đa 2 chữ số thập phân) hoặc số tiền nguyên không quá 9.999.999.999 đồng.';
    end if;
    perform pg_catalog.pg_advisory_xact_lock(20261102, 1);
    if exists (select 1 from public.payroll_periods where status = 'locked' and p_work_date between period_start and period_end) then
        raise exception using errcode = 'P0001', message = 'Ngày làm việc thuộc kỳ lương đã khóa. Vui lòng chọn ngày thuộc kỳ chưa khóa.';
    end if;
    perform 1 from public.customers where id = p_customer_id and status = 'active' and deleted_at is null for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Khách hàng không còn hoạt động hoặc không tồn tại.';
    end if;
    perform 1 from public.employees where id = p_employee_id and status = 'active' and deleted_at is null;
    if not found then
        raise exception using errcode = 'P0001', message = 'Vui lòng chọn nhân viên đang hoạt động.';
    end if;
    select * into offering_row from public.offerings where id = p_offering_id and status = 'active' and deleted_at is null;
    if not found then
        raise exception using errcode = 'P0001', message = 'Dịch vụ hoặc gói liệu trình không còn hoạt động.';
    end if;
    if p_classification = 'retail' and p_customer_package_id is not null then
        raise exception using errcode = 'P0001', message = 'Ca khách lẻ không sử dụng thẻ liệu trình.';
    end if;
    occurred_at := (p_work_date + (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::time) at time zone 'Asia/Ho_Chi_Minh';
    if p_classification = 'package' and p_customer_package_id is not null then
        select * into package_row from public.customer_packages where id = p_customer_package_id for update;
        if not found or package_row.customer_id <> p_customer_id or package_row.offering_id <> p_offering_id
            or package_row.status <> 'active' or package_row.sessions_used >= package_row.sessions_total
            or package_row.starts_on > p_work_date or (package_row.expires_on is not null and package_row.expires_on < p_work_date) then
            raise exception using errcode = 'P0001', message = 'Thẻ liệu trình không khớp khách hàng, dịch vụ hoặc không còn buổi hợp lệ trong ngày đã chọn.';
        end if;
        select coalesce(pg_catalog.max(u.session_number), 0) + 1 into session_number from public.package_usages u
        where u.customer_package_id = package_row.id and u.direction = 'use';
        insert into public.package_usages(customer_package_id, performed_by, recorded_by, session_number, direction, verification_method, verification_note, used_at)
        values (package_row.id, p_employee_id, auth.uid(), session_number, 'use', 'admin_confirmation', p_notes, occurred_at)
        returning id into usage_id;
        update public.customer_packages set sessions_used = sessions_used + 1,
            status = case when sessions_used + 1 >= sessions_total then 'completed' else status end, updated_at = pg_catalog.now()
        where id = package_row.id;
    end if;
    insert into public.invoices(customer_id, created_by, code, daily_entry_key, type, status, subtotal, discount_amount, total_received, payment_method, issued_at, paid_at, notes)
    values (p_customer_id, auth.uid(), 'HD-' || pg_catalog.upper(pg_catalog.replace(p_request_id::text, '-', '')),
        'shift-' || p_request_id::text, case when p_classification = 'retail' then offering_row.type else 'service' end,
        'paid', p_collected, 0, p_collected, p_payment_method, occurred_at, occurred_at,
        case when p_classification = 'package' then '[Ca gói] ' else '[Ca lẻ] ' end || coalesce(nullif(pg_catalog.btrim(p_notes), ''), 'Nhập ca làm cuối ngày'))
    returning * into invoice_row;
    insert into public.invoice_items(invoice_id, offering_id, employee_id, description, quantity, unit_price, line_total, package_sessions)
    values (invoice_row.id, p_offering_id, p_employee_id, offering_row.name, 1, p_collected, p_collected,
        case when invoice_row.type = 'package' then offering_row.sessions_total else 0 end);
    insert into public.payments(invoice_id, received_by, receipt_code, method, amount, paid_at)
    values (invoice_row.id, auth.uid(), 'PT-' || invoice_row.id::text || '-' || pg_catalog.substr(p_request_id::text, 1, 8), p_payment_method, p_collected, occurred_at);
    update public.customers set total_spend = total_spend + p_collected,
        last_visit_at = greatest(last_visit_at, occurred_at), updated_at = pg_catalog.now() where id = p_customer_id;
    if invoice_row.type = 'package' then
        insert into public.customer_packages(customer_id, offering_id, code, purchased_on, starts_on, expires_on, sessions_total, sessions_used, unit_price, status, notes)
        values (p_customer_id, p_offering_id, 'THE-' || invoice_row.id::text || '-' || pg_catalog.substr(p_request_id::text, 1, 8), p_work_date, p_work_date, p_work_date + interval '1 year', offering_row.sessions_total, 0, p_collected, 'active', 'Tạo tự động từ ' || invoice_row.code)
        returning * into package_row;
        update public.invoices set customer_package_id = package_row.id where id = invoice_row.id returning * into invoice_row;
    end if;
    commission_base := case when p_classification = 'package' and p_collected = 0
        then coalesce(offering_row.price, 0)::numeric / greatest(1, offering_row.sessions_total) else p_collected end;
    select r.id into personal_rule_id from public.commission_rules r where r.offering_id = p_offering_id
        and r.is_active and r.basis in ('payment', 'completion') and r.beneficiary_type = 'performer_employee'
        and r.beneficiary_employee_id = p_employee_id and r.starts_on <= p_work_date and (r.ends_on is null or r.ends_on >= p_work_date)
        order by r.starts_on desc, r.id desc limit 1;
    if p_commission_type is not null then
        entry_amount := case when p_commission_type = 'fixed' then p_commission_value::bigint else pg_catalog.round(commission_base * p_commission_value / 100)::bigint end;
        insert into public.commission_entries(employee_id, invoice_id, offering_id, basis, calculation_type, rate, amount, status, occurred_at)
        values (p_employee_id, invoice_row.id, p_offering_id, 'completion', p_commission_type, p_commission_value, entry_amount, 'pending', occurred_at);
        total_commission := entry_amount;
    end if;
    for rule_row in select r.* from public.commission_rules r where r.offering_id = p_offering_id and r.is_active
        and r.basis in ('payment', 'completion') and r.starts_on <= p_work_date and (r.ends_on is null or r.ends_on >= p_work_date)
        and (r.beneficiary_type = 'specific_employee' or (p_commission_type is null and (
            (r.beneficiary_type = 'performer_employee' and r.id = personal_rule_id)
            or (r.beneficiary_type = 'primary_technician' and personal_rule_id is null))))
    loop
        recipient_id := case when rule_row.beneficiary_type = 'specific_employee' then rule_row.beneficiary_employee_id else p_employee_id end;
        if recipient_id is null then continue; end if;
        entry_amount := greatest(0, case when rule_row.calculation_type = 'fixed' then pg_catalog.round(rule_row.value)::bigint
            else pg_catalog.round(commission_base * rule_row.value / 100)::bigint end);
        insert into public.commission_entries(employee_id, invoice_id, offering_id, commission_rule_id, basis, calculation_type, rate, amount, status, occurred_at)
        values (recipient_id, invoice_row.id, p_offering_id, rule_row.id, rule_row.basis, rule_row.calculation_type, rule_row.value, entry_amount, 'pending', occurred_at);
        total_commission := total_commission + entry_amount;
    end loop;
    request_response := pg_catalog.jsonb_build_object('invoice', pg_catalog.to_jsonb(invoice_row), 'commission_amount', total_commission, 'package_usage_id', usage_id);
    perform private.finish_invoice_request('create_invoice', p_request_id, request_response);
    perform private.write_audit_log('daily_shift.recorded', 'invoice', invoice_row.id::text, null,
        request_response || pg_catalog.jsonb_build_object('input', request_payload), p_notes);
    return request_response;
end;
$$;
revoke all on function public.record_daily_shift(uuid, bigint, bigint, bigint, text, bigint, date, text, bigint, text, numeric, text) from public, anon;
grant execute on function public.record_daily_shift(uuid, bigint, bigint, bigint, text, bigint, date, text, bigint, text, numeric, text) to authenticated;
notify pgrst, 'reload schema';
