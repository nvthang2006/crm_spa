-- Run invoice creation, payment, voiding, refunds, and commissions atomically.

create or replace function public.search_invoice_ids(
    p_search text,
    p_status text,
    p_method text,
    p_date_from date,
    p_date_to date,
    p_limit integer,
    p_offset integer
)
returns table(invoice_id bigint, total_count bigint)
language plpgsql
stable
security definer
set search_path = ''
as $$
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_status not in ('all', 'draft', 'pending', 'paid', 'void', 'refunded')
        or p_method not in ('all', 'cash', 'vietqr', 'pos') then
        raise exception using errcode = 'P0001', message = 'Bộ lọc hóa đơn không hợp lệ.';
    end if;
    if p_date_from is not null and p_date_to is not null and p_date_to < p_date_from then
        raise exception using errcode = 'P0001', message = 'Khoảng ngày lọc hóa đơn chưa hợp lệ.';
    end if;
    if coalesce(pg_catalog.char_length(p_search), 0) > 120 or p_limit < 1 or p_limit > 500 or p_offset < 0 then
        raise exception using errcode = 'P0001', message = 'Thông tin tìm kiếm hoặc phân trang chưa hợp lệ.';
    end if;

    return query
    select invoice.id, pg_catalog.count(*) over ()
    from public.invoices invoice
    join public.customers customer on customer.id = invoice.customer_id
    where (p_status = 'all' or invoice.status = p_status)
        and (p_method = 'all' or invoice.payment_method = p_method)
        and (p_date_from is null or invoice.issued_at >= (p_date_from::timestamp at time zone 'Asia/Ho_Chi_Minh'))
        and (p_date_to is null or invoice.issued_at < ((p_date_to + 1)::timestamp at time zone 'Asia/Ho_Chi_Minh'))
        and (nullif(pg_catalog.btrim(p_search), '') is null
            or invoice.code ilike '%' || pg_catalog.btrim(p_search) || '%'
            or customer.name ilike '%' || pg_catalog.btrim(p_search) || '%'
            or customer.phone ilike '%' || pg_catalog.btrim(p_search) || '%')
    order by invoice.issued_at desc nulls last, invoice.id desc
    offset p_offset limit p_limit;
end;
$$;
revoke all on function public.search_invoice_ids(text, text, text, date, date, integer, integer) from public, anon;
grant execute on function public.search_invoice_ids(text, text, text, date, date, integer, integer) to authenticated;

create or replace function public.create_invoice(
    p_customer_id bigint,
    p_offering_id bigint,
    p_appointment_id bigint,
    p_employee_id bigint,
    p_discount_amount bigint
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    customer_row public.customers%rowtype;
    offering_row public.offerings%rowtype;
    appointment_row public.appointments%rowtype;
    invoice_row public.invoices%rowtype;
    item_row public.invoice_items%rowtype;
    before_appointment jsonb;
    subtotal bigint;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_discount_amount is null or p_discount_amount < 0 then
        raise exception using errcode = 'P0001', message = 'Mức giảm giá phải từ 0 đồng trở lên.';
    end if;

    select * into customer_row
    from public.customers where id = p_customer_id and status = 'active' and deleted_at is null;
    if not found then
        raise exception using errcode = 'P0001', message = 'Khách hàng không tồn tại hoặc đã ngừng hoạt động.';
    end if;
    select * into offering_row
    from public.offerings where id = p_offering_id and status = 'active' and deleted_at is null;
    if not found then
        raise exception using errcode = 'P0001', message = 'Chỉ dịch vụ hoặc gói đang hoạt động mới được lập hóa đơn.';
    end if;
    if p_employee_id is not null and not exists (
        select 1 from public.employees where id = p_employee_id and status = 'active' and deleted_at is null
    ) then
        raise exception using errcode = 'P0001', message = 'Nhân sự được chọn không còn hoạt động.';
    end if;
    subtotal := coalesce(offering_row.price, 0);
    if p_discount_amount > subtotal then
        raise exception using errcode = 'P0001', message = 'Mức giảm không được lớn hơn giá niêm yết.';
    end if;

    if p_appointment_id is not null then
        select * into appointment_row
        from public.appointments where id = p_appointment_id for update;
        if not found or appointment_row.customer_id <> p_customer_id then
            raise exception using errcode = 'P0001', message = 'Lịch hẹn không thuộc khách hàng đã chọn.';
        end if;
        if appointment_row.status <> 'completed_pending_payment' or appointment_row.payment_type <> 'service' then
            raise exception using errcode = 'P0001', message = 'Chỉ lịch dịch vụ đã hoàn thành và đang chờ thanh toán mới được liên kết.';
        end if;
        if offering_row.type <> 'service' or appointment_row.offering_id <> offering_row.id then
            raise exception using errcode = 'P0001', message = 'Dịch vụ trên hóa đơn không khớp với lịch hẹn.';
        end if;
        if exists (select 1 from public.invoices where appointment_id = p_appointment_id) then
            raise exception using errcode = 'P0001', message = 'Lịch hẹn đã có hóa đơn.';
        end if;
    end if;

    insert into public.invoices (
        customer_id, appointment_id, code, type, status, subtotal, discount_amount,
        total_received, created_by, issued_at
    ) values (
        p_customer_id, p_appointment_id,
        'HD-' || pg_catalog.to_char(pg_catalog.clock_timestamp(), 'YYYYMMDDHH24MISS') || '-'
            || pg_catalog.upper(pg_catalog.substr(pg_catalog.replace(pg_catalog.gen_random_uuid()::text, '-', ''), 1, 3)),
        offering_row.type, 'pending', subtotal, p_discount_amount, 0, auth.uid(), pg_catalog.now()
    ) returning * into invoice_row;

    insert into public.invoice_items (
        invoice_id, offering_id, employee_id, description, quantity, unit_price, line_total, package_sessions
    ) values (
        invoice_row.id, offering_row.id,
        case when p_employee_id is not null then p_employee_id else appointment_row.primary_employee_id end,
        offering_row.name, 1, subtotal, subtotal,
        case when offering_row.type = 'package' then offering_row.sessions_total else 0 end
    ) returning * into item_row;

    perform private.write_audit_log('invoice.created', 'invoice', invoice_row.id::text, null, pg_catalog.to_jsonb(invoice_row), null);
    return pg_catalog.jsonb_build_object('invoice', pg_catalog.to_jsonb(invoice_row), 'item', pg_catalog.to_jsonb(item_row));
end;
$$;
revoke all on function public.create_invoice(bigint, bigint, bigint, bigint, bigint) from public, anon;
grant execute on function public.create_invoice(bigint, bigint, bigint, bigint, bigint) to authenticated;

create or replace function private.create_invoice_commissions(
    p_invoice_id bigint,
    p_payment_amount bigint,
    p_occurred_at timestamptz
)
returns void
language plpgsql
security definer
set search_path = ''
as $$
declare
    invoice_row public.invoices%rowtype;
    item_row record;
    rule_row public.commission_rules%rowtype;
    performer_id bigint;
    beneficiary_id bigint;
    personal_rule_id bigint;
    total_quantity integer;
    base_amount bigint;
    commission_amount bigint;
    occurred_date date := (pg_catalog.timezone('Asia/Ho_Chi_Minh', p_occurred_at))::date;
begin
    select * into invoice_row from public.invoices where id = p_invoice_id;
    if not found then
        return;
    end if;
    select coalesce(pg_catalog.sum(quantity), 0)::integer into total_quantity
    from public.invoice_items where invoice_id = invoice_row.id;

    for item_row in
        select item.id, item.offering_id, item.employee_id, item.quantity, offering.type
        from public.invoice_items item
        join public.offerings offering on offering.id = item.offering_id
        where item.invoice_id = invoice_row.id
    loop
        select appointment.primary_employee_id, appointment.support_employee_id
        into performer_id, beneficiary_id
        from public.appointments appointment
        where appointment.id = invoice_row.appointment_id;

        if invoice_row.appointment_id is null then
            performer_id := item_row.employee_id;
            beneficiary_id := null;
        end if;
        performer_id := coalesce(performer_id, item_row.employee_id);

        personal_rule_id := null;
        if performer_id is not null then
            select rule.id into personal_rule_id
            from public.commission_rules rule
            where rule.offering_id = item_row.offering_id
                and rule.is_active
                and rule.basis in ('payment', 'completion')
                and rule.beneficiary_type = 'performer_employee'
                and rule.beneficiary_employee_id = performer_id
                and rule.starts_on <= occurred_date
                and (rule.ends_on is null or rule.ends_on >= occurred_date)
            order by rule.starts_on desc, rule.id desc
            limit 1;
        end if;

        for rule_row in
            select rule.*
            from public.commission_rules rule
            where rule.offering_id = item_row.offering_id
                and rule.is_active
                and rule.basis in ('payment', 'completion')
                and rule.starts_on <= occurred_date
                and (rule.ends_on is null or rule.ends_on >= occurred_date)
                and (
                    rule.beneficiary_type not in ('performer_employee', 'primary_technician')
                    or (rule.beneficiary_type = 'performer_employee' and rule.id = personal_rule_id)
                    or (rule.beneficiary_type = 'primary_technician' and personal_rule_id is null)
                )
        loop
            beneficiary_id := case
                when rule_row.beneficiary_type = 'support_technician' then (
                    select appointment.support_employee_id from public.appointments appointment where appointment.id = invoice_row.appointment_id
                )
                when rule_row.beneficiary_type = 'specific_employee' then rule_row.beneficiary_employee_id
                else performer_id
            end;
            if beneficiary_id is null or not exists (
                select 1 from public.employees where id = beneficiary_id
            ) then
                continue;
            end if;
            if exists (
                select 1 from public.commission_entries
                where invoice_id = invoice_row.id and employee_id = beneficiary_id and commission_rule_id = rule_row.id
            ) then
                continue;
            end if;

            base_amount := pg_catalog.round(
                p_payment_amount::numeric * item_row.quantity / greatest(1, total_quantity)
            )::bigint;
            commission_amount := case
                when rule_row.calculation_type = 'fixed' then pg_catalog.round(rule_row.value * item_row.quantity)::bigint
                else pg_catalog.round(base_amount::numeric * rule_row.value / 100)::bigint
            end;
            insert into public.commission_entries (
                employee_id, invoice_id, appointment_id, offering_id, commission_rule_id,
                basis, calculation_type, rate, amount, status, occurred_at
            ) values (
                beneficiary_id, invoice_row.id, invoice_row.appointment_id, item_row.offering_id, rule_row.id,
                rule_row.basis, rule_row.calculation_type, rule_row.value, greatest(0, commission_amount), 'pending', p_occurred_at
            );
        end loop;
    end loop;
end;
$$;
revoke all on function private.create_invoice_commissions(bigint, bigint, timestamptz) from public, anon, authenticated;

create or replace function public.pay_invoice(
    p_invoice_id bigint,
    p_method text,
    p_reference text default null
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    invoice_row public.invoices%rowtype;
    appointment_row public.appointments%rowtype;
    payment_row public.payments%rowtype;
    package_row public.customer_packages%rowtype;
    first_package_item record;
    before_invoice jsonb;
    before_appointment jsonb;
    payment_amount bigint;
    item_count integer;
    linked_offerings integer;
    distinct_types integer;
    offering_type text;
    payment_timestamp timestamptz := pg_catalog.now();
    today_local date := (pg_catalog.timezone('Asia/Ho_Chi_Minh', pg_catalog.now()))::date;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_method not in ('cash', 'vietqr', 'pos') then
        raise exception using errcode = 'P0001', message = 'Phương thức thu tiền không hợp lệ.';
    end if;
    if p_method in ('vietqr', 'pos') and nullif(pg_catalog.btrim(p_reference), '') is null then
        raise exception using errcode = 'P0001', message = 'Vui lòng nhập mã giao dịch cho VietQR hoặc POS.';
    end if;
    if p_reference is not null and pg_catalog.char_length(p_reference) > 120 then
        raise exception using errcode = 'P0001', message = 'Mã giao dịch không được vượt quá 120 ký tự.';
    end if;

    select * into invoice_row from public.invoices where id = p_invoice_id for update;
    if not found or invoice_row.status <> 'pending' then
        raise exception using errcode = 'P0001', message = 'Chỉ hóa đơn đang chờ thanh toán mới được thu tiền.';
    end if;
    select count(*)::integer, count(offering.id)::integer, count(distinct offering.type)::integer, min(offering.type)
    into item_count, linked_offerings, distinct_types, offering_type
    from public.invoice_items item
    left join public.offerings offering on offering.id = item.offering_id
    where item.invoice_id = invoice_row.id;
    if item_count = 0 then
        raise exception using errcode = 'P0001', message = 'Hóa đơn phải có ít nhất một dòng hàng trước khi thu tiền.';
    end if;
    if linked_offerings <> item_count or distinct_types <> 1 or offering_type <> invoice_row.type then
        raise exception using errcode = 'P0001', message = 'Loại hóa đơn không khớp với dịch vụ hoặc gói trong các dòng hàng.';
    end if;

    if invoice_row.appointment_id is not null then
        select * into appointment_row from public.appointments where id = invoice_row.appointment_id for update;
        if not found then
            raise exception using errcode = 'P0001', message = 'Không tìm thấy lịch hẹn liên kết.';
        end if;
        if invoice_row.type = 'package' then
            raise exception using errcode = 'P0001', message = 'Phiếu bán gói liệu trình phải được lập độc lập, không liên kết lịch dịch vụ.';
        end if;
        if appointment_row.status <> 'completed_pending_payment' then
            raise exception using errcode = 'P0001', message = 'Lịch hẹn phải hoàn thành dịch vụ trước khi thu tiền.';
        end if;
        if appointment_row.payment_type <> 'service' then
            raise exception using errcode = 'P0001', message = 'Lịch dùng thẻ liệu trình không tạo phiếu thu dịch vụ.';
        end if;
        if item_count <> 1 or appointment_row.customer_id <> invoice_row.customer_id
            or not exists (select 1 from public.invoice_items where invoice_id = invoice_row.id and offering_id = appointment_row.offering_id) then
            raise exception using errcode = 'P0001', message = 'Hóa đơn không khớp với lịch hẹn liên kết.';
        end if;
    end if;

    payment_amount := greatest(0, invoice_row.subtotal - invoice_row.discount_amount);
    insert into public.payments (
        invoice_id, received_by, receipt_code, method, amount, reference, paid_at
    ) values (
        invoice_row.id, auth.uid(),
        'PT-' || pg_catalog.to_char(pg_catalog.clock_timestamp(), 'YYYYMMDDHH24MISS') || '-'
            || pg_catalog.upper(pg_catalog.substr(pg_catalog.replace(pg_catalog.gen_random_uuid()::text, '-', ''), 1, 3)),
        p_method, payment_amount, nullif(pg_catalog.btrim(p_reference), ''), payment_timestamp
    ) returning * into payment_row;

    before_invoice := pg_catalog.to_jsonb(invoice_row);
    update public.invoices
    set status = 'paid', total_received = payment_amount, payment_method = p_method,
        paid_at = payment_timestamp, updated_at = pg_catalog.now()
    where id = invoice_row.id returning * into invoice_row;

    update public.customers
    set total_spend = total_spend + payment_amount,
        last_visit_at = case when last_visit_at is null or last_visit_at < payment_timestamp then payment_timestamp else last_visit_at end,
        updated_at = pg_catalog.now()
    where id = invoice_row.customer_id;

    if invoice_row.type = 'package' then
        select item.id, item.offering_id, item.description, item.quantity, item.unit_price,
            item.package_sessions, offering.sessions_total
        into first_package_item
        from public.invoice_items item
        join public.offerings offering on offering.id = item.offering_id
        where item.invoice_id = invoice_row.id and offering.type = 'package'
        order by item.id
        limit 1;
        if first_package_item.id is null then
            raise exception using errcode = 'P0001', message = 'Hóa đơn gói phải có ít nhất một dịch vụ loại gói.';
        end if;

        insert into public.customer_packages (
            customer_id, offering_id, code, purchased_on, starts_on, expires_on,
            sessions_total, sessions_used, unit_price, status, notes
        ) values (
            invoice_row.customer_id, first_package_item.offering_id,
            'THE-' || pg_catalog.to_char(pg_catalog.clock_timestamp(), 'YYYYMMDDHH24MISS') || '-'
                || pg_catalog.upper(pg_catalog.substr(pg_catalog.replace(pg_catalog.gen_random_uuid()::text, '-', ''), 1, 4)),
            today_local, today_local, today_local + interval '1 year',
            case when first_package_item.package_sessions > 0 then first_package_item.package_sessions else first_package_item.sessions_total end,
            0, first_package_item.unit_price, 'active', 'Tạo tự động từ ' || invoice_row.code
        ) returning * into package_row;
        update public.invoices set customer_package_id = package_row.id, updated_at = pg_catalog.now()
        where id = invoice_row.id returning * into invoice_row;
    end if;

    if invoice_row.appointment_id is not null and appointment_row.status = 'completed_pending_payment' then
        before_appointment := pg_catalog.to_jsonb(appointment_row);
        update public.appointments set status = 'paid', updated_at = pg_catalog.now()
        where id = appointment_row.id returning * into appointment_row;
        perform private.write_audit_log(
            'appointment.status_changed', 'appointment', appointment_row.id::text,
            before_appointment, pg_catalog.to_jsonb(appointment_row), null
        );
    end if;

    perform private.create_invoice_commissions(invoice_row.id, payment_amount, payment_timestamp);
    perform private.write_audit_log('invoice.paid', 'invoice', invoice_row.id::text, before_invoice, pg_catalog.to_jsonb(invoice_row), null);
    return pg_catalog.jsonb_build_object(
        'invoice', pg_catalog.to_jsonb(invoice_row),
        'payment', pg_catalog.to_jsonb(payment_row),
        'customer_package', case when package_row.id is null then null else pg_catalog.to_jsonb(package_row) end
    );
end;
$$;
revoke all on function public.pay_invoice(bigint, text, text) from public, anon;
grant execute on function public.pay_invoice(bigint, text, text) to authenticated;

create or replace function public.void_invoice(p_invoice_id bigint, p_reason text)
returns public.invoices
language plpgsql
security definer
set search_path = ''
as $$
declare
    invoice_row public.invoices%rowtype;
    before_row jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if nullif(pg_catalog.btrim(p_reason), '') is null
        or pg_catalog.char_length(pg_catalog.btrim(p_reason)) < 5
        or pg_catalog.char_length(p_reason) > 500 then
        raise exception using errcode = 'P0001', message = 'Lý do hủy hóa đơn phải có từ 5 đến 500 ký tự.';
    end if;
    select * into invoice_row from public.invoices where id = p_invoice_id for update;
    if not found then
        raise exception using errcode = 'P0001', message = 'Không tìm thấy hóa đơn.';
    end if;
    if invoice_row.status = 'paid' then
        raise exception using errcode = 'P0001', message = 'Không thể hủy hóa đơn đã thanh toán; hãy lập phiếu hoàn hoặc điều chỉnh.';
    end if;
    if invoice_row.status = 'void' then
        raise exception using errcode = 'P0001', message = 'Hóa đơn đã được hủy trước đó.';
    end if;
    before_row := pg_catalog.to_jsonb(invoice_row);
    update public.invoices
    set status = 'void', voided_at = pg_catalog.now(), notes = pg_catalog.btrim(p_reason), updated_at = pg_catalog.now()
    where id = p_invoice_id returning * into invoice_row;
    perform private.write_audit_log('invoice.voided', 'invoice', invoice_row.id::text, before_row, pg_catalog.to_jsonb(invoice_row), p_reason);
    return invoice_row;
end;
$$;
revoke all on function public.void_invoice(bigint, text) from public, anon;
grant execute on function public.void_invoice(bigint, text) to authenticated;

create or replace function public.refund_invoice(
    p_invoice_id bigint,
    p_method text,
    p_reason text,
    p_reference text default null
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    invoice_row public.invoices%rowtype;
    customer_row public.customers%rowtype;
    package_row public.customer_packages%rowtype;
    appointment_row public.appointments%rowtype;
    refund_row public.invoice_refunds%rowtype;
    before_invoice jsonb;
    before_appointment jsonb;
    refund_amount bigint;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_method not in ('cash', 'vietqr', 'pos') then
        raise exception using errcode = 'P0001', message = 'Phương thức hoàn tiền không hợp lệ.';
    end if;
    if p_method in ('vietqr', 'pos') and nullif(pg_catalog.btrim(p_reference), '') is null then
        raise exception using errcode = 'P0001', message = 'Vui lòng nhập mã giao dịch hoàn cho VietQR hoặc POS.';
    end if;
    if p_reference is not null and pg_catalog.char_length(p_reference) > 120 then
        raise exception using errcode = 'P0001', message = 'Mã giao dịch không được vượt quá 120 ký tự.';
    end if;
    if nullif(pg_catalog.btrim(p_reason), '') is null or pg_catalog.char_length(pg_catalog.btrim(p_reason)) < 5 or pg_catalog.char_length(p_reason) > 500 then
        raise exception using errcode = 'P0001', message = 'Lý do hoàn tiền phải có từ 5 đến 500 ký tự.';
    end if;

    select * into invoice_row from public.invoices where id = p_invoice_id for update;
    if not found or invoice_row.status <> 'paid' then
        raise exception using errcode = 'P0001', message = 'Chỉ hóa đơn đã thanh toán mới được hoàn tiền.';
    end if;
    if exists (select 1 from public.invoice_refunds where invoice_id = invoice_row.id) then
        raise exception using errcode = 'P0001', message = 'Hóa đơn đã được hoàn tiền trước đó.';
    end if;
    if exists (
        select 1 from public.commission_entries
        where invoice_id = invoice_row.id and status = 'locked'
    ) then
        raise exception using errcode = 'P0001', message = 'Không thể hoàn tiền vì hoa hồng đã thuộc kỳ lương khóa.';
    end if;
    if invoice_row.customer_package_id is not null then
        select * into package_row from public.customer_packages where id = invoice_row.customer_package_id for update;
    end if;
    if invoice_row.type = 'package' and package_row.id is null then
        raise exception using errcode = 'P0001', message = 'Hóa đơn gói chưa có thẻ liệu trình được tạo để hoàn.';
    end if;
    if invoice_row.type = 'package' and exists (
        select 1 from public.package_usages where customer_package_id = package_row.id and direction = 'use'
    ) then
        raise exception using errcode = 'P0001', message = 'Không thể hoàn toàn bộ gói đã phát sinh trừ buổi.';
    end if;
    select coalesce(pg_catalog.sum(amount), 0) into refund_amount
    from public.payments where invoice_id = invoice_row.id;
    if refund_amount <= 0 then
        raise exception using errcode = 'P0001', message = 'Hóa đơn không có số tiền thực thu để hoàn.';
    end if;

    before_invoice := pg_catalog.to_jsonb(invoice_row);
    insert into public.invoice_refunds (
        invoice_id, processed_by, code, method, amount, reference, reason, refunded_at
    ) values (
        invoice_row.id, auth.uid(),
        'HT-' || pg_catalog.to_char(pg_catalog.clock_timestamp(), 'YYYYMMDDHH24MISS') || '-'
            || pg_catalog.upper(pg_catalog.substr(pg_catalog.replace(pg_catalog.gen_random_uuid()::text, '-', ''), 1, 3)),
        p_method, refund_amount, nullif(pg_catalog.btrim(p_reference), ''), pg_catalog.btrim(p_reason), pg_catalog.now()
    ) returning * into refund_row;

    update public.invoices set status = 'refunded', updated_at = pg_catalog.now()
    where id = invoice_row.id returning * into invoice_row;
    select * into customer_row from public.customers where id = invoice_row.customer_id for update;
    update public.customers set total_spend = greatest(0, total_spend - refund_amount), updated_at = pg_catalog.now()
    where id = customer_row.id;
    update public.commission_entries set status = 'reversed', updated_at = pg_catalog.now()
    where invoice_id = invoice_row.id and status = 'pending';

    if package_row.id is not null then
        update public.customer_packages set status = 'refunded', updated_at = pg_catalog.now()
        where id = package_row.id returning * into package_row;
    end if;
    if invoice_row.appointment_id is not null then
        select * into appointment_row from public.appointments where id = invoice_row.appointment_id for update;
        if found and appointment_row.status = 'paid' then
            before_appointment := pg_catalog.to_jsonb(appointment_row);
            update public.appointments set status = 'refunded', updated_at = pg_catalog.now()
            where id = appointment_row.id returning * into appointment_row;
            perform private.write_audit_log(
                'appointment.status_changed', 'appointment', appointment_row.id::text,
                before_appointment, pg_catalog.to_jsonb(appointment_row), 'Hoàn tiền hóa đơn ' || invoice_row.code
            );
        end if;
    end if;

    perform private.write_audit_log('invoice.refunded', 'invoice', invoice_row.id::text, before_invoice, pg_catalog.to_jsonb(invoice_row), p_reason);
    return pg_catalog.jsonb_build_object(
        'invoice', pg_catalog.to_jsonb(invoice_row),
        'refund', pg_catalog.to_jsonb(refund_row),
        'customer_package', case when package_row.id is null then null else pg_catalog.to_jsonb(package_row) end
    );
end;
$$;
revoke all on function public.refund_invoice(bigint, text, text, text) from public, anon;
grant execute on function public.refund_invoice(bigint, text, text, text) to authenticated;
