-- Persist completed invoice requests so a retry after a network timeout returns the original result.

create table private.idempotency_requests (
    actor_id uuid not null references auth.users(id) on delete cascade,
    operation text not null check (operation in ('create_invoice', 'pay_invoice', 'void_invoice', 'refund_invoice')),
    request_id uuid not null,
    request_payload jsonb not null check (pg_catalog.jsonb_typeof(request_payload) = 'object'),
    response jsonb,
    created_at timestamptz not null default pg_catalog.now(),
    primary key (actor_id, operation, request_id)
);
alter table private.idempotency_requests enable row level security;
revoke all on table private.idempotency_requests from public, anon, authenticated;

create or replace function private.claim_invoice_request(
    p_operation text,
    p_request_id uuid,
    p_request_payload jsonb
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    current_actor uuid := auth.uid();
    saved_payload jsonb;
    saved_response jsonb;
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    if p_operation is null or p_operation not in ('create_invoice', 'pay_invoice', 'void_invoice', 'refund_invoice')
        or p_request_id is null
        or p_request_payload is null
        or pg_catalog.jsonb_typeof(p_request_payload) is distinct from 'object' then
        raise exception using errcode = '22023', message = 'Mã yêu cầu hóa đơn chưa hợp lệ.';
    end if;

    insert into private.idempotency_requests (actor_id, operation, request_id, request_payload)
    values (current_actor, p_operation, p_request_id, p_request_payload)
    on conflict (actor_id, operation, request_id) do nothing;
    if found then
        return null;
    end if;

    select request_payload, response
    into saved_payload, saved_response
    from private.idempotency_requests
    where actor_id = current_actor and operation = p_operation and request_id = p_request_id
    for update;
    if not found then
        raise exception using errcode = 'XX000', message = 'Không thể khôi phục yêu cầu hóa đơn đã gửi.';
    end if;
    if saved_payload is distinct from p_request_payload then
        raise exception using errcode = '22023', message = 'Mã yêu cầu đã được dùng với nội dung khác.';
    end if;
    if saved_response is null then
        raise exception using errcode = 'XX000', message = 'Yêu cầu hóa đơn chưa có kết quả hoàn tất.';
    end if;

    return saved_response;
end;
$$;
revoke all on function private.claim_invoice_request(text, uuid, jsonb) from public, anon, authenticated;

create or replace function private.finish_invoice_request(
    p_operation text,
    p_request_id uuid,
    p_response jsonb
)
returns void
language plpgsql
security definer
set search_path = ''
as $$
begin
    if p_response is null then
        raise exception using errcode = '22023', message = 'Kết quả yêu cầu hóa đơn chưa hợp lệ.';
    end if;

    update private.idempotency_requests
    set response = p_response
    where actor_id = auth.uid()
        and operation = p_operation
        and request_id = p_request_id
        and response is null;
    if not found then
        raise exception using errcode = 'XX000', message = 'Không thể lưu kết quả yêu cầu hóa đơn.';
    end if;
end;
$$;
revoke all on function private.finish_invoice_request(text, uuid, jsonb) from public, anon, authenticated;

create or replace function public.create_invoice_idempotent(
    p_request_id uuid,
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
    saved_response jsonb;
    request_response jsonb;
    request_payload jsonb := pg_catalog.jsonb_build_object(
        'customer_id', p_customer_id,
        'offering_id', p_offering_id,
        'appointment_id', p_appointment_id,
        'employee_id', p_employee_id,
        'discount_amount', p_discount_amount
    );
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    saved_response := private.claim_invoice_request('create_invoice', p_request_id, request_payload);
    if saved_response is not null then
        return saved_response;
    end if;

    request_response := public.create_invoice(
        p_customer_id, p_offering_id, p_appointment_id, p_employee_id, p_discount_amount
    );
    perform private.finish_invoice_request('create_invoice', p_request_id, request_response);
    return request_response;
end;
$$;
revoke all on function public.create_invoice_idempotent(uuid, bigint, bigint, bigint, bigint, bigint) from public, anon;
grant execute on function public.create_invoice_idempotent(uuid, bigint, bigint, bigint, bigint, bigint) to authenticated;

create or replace function public.pay_invoice_idempotent(
    p_request_id uuid,
    p_invoice_id bigint,
    p_method text,
    p_reference text
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    saved_response jsonb;
    request_response jsonb;
    request_payload jsonb := pg_catalog.jsonb_build_object(
        'invoice_id', p_invoice_id,
        'method', p_method,
        'reference', p_reference
    );
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    saved_response := private.claim_invoice_request('pay_invoice', p_request_id, request_payload);
    if saved_response is not null then
        return saved_response;
    end if;

    request_response := public.pay_invoice(p_invoice_id, p_method, p_reference);
    perform private.finish_invoice_request('pay_invoice', p_request_id, request_response);
    return request_response;
end;
$$;
revoke all on function public.pay_invoice_idempotent(uuid, bigint, text, text) from public, anon;
grant execute on function public.pay_invoice_idempotent(uuid, bigint, text, text) to authenticated;

create or replace function public.void_invoice_idempotent(
    p_request_id uuid,
    p_invoice_id bigint,
    p_reason text
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    saved_response jsonb;
    request_response jsonb;
    request_payload jsonb := pg_catalog.jsonb_build_object(
        'invoice_id', p_invoice_id,
        'reason', p_reason
    );
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    saved_response := private.claim_invoice_request('void_invoice', p_request_id, request_payload);
    if saved_response is not null then
        return saved_response;
    end if;

    request_response := pg_catalog.to_jsonb(public.void_invoice(p_invoice_id, p_reason));
    perform private.finish_invoice_request('void_invoice', p_request_id, request_response);
    return request_response;
end;
$$;
revoke all on function public.void_invoice_idempotent(uuid, bigint, text) from public, anon;
grant execute on function public.void_invoice_idempotent(uuid, bigint, text) to authenticated;

create or replace function public.refund_invoice_idempotent(
    p_request_id uuid,
    p_invoice_id bigint,
    p_method text,
    p_reason text,
    p_reference text
)
returns jsonb
language plpgsql
security definer
set search_path = ''
as $$
declare
    saved_response jsonb;
    request_response jsonb;
    request_payload jsonb := pg_catalog.jsonb_build_object(
        'invoice_id', p_invoice_id,
        'method', p_method,
        'reason', p_reason,
        'reference', p_reference
    );
begin
    if not coalesce(private.has_manager_access(), false) then
        raise exception using errcode = '42501', message = 'Tài khoản không có quyền quản lý.';
    end if;
    saved_response := private.claim_invoice_request('refund_invoice', p_request_id, request_payload);
    if saved_response is not null then
        return saved_response;
    end if;

    request_response := public.refund_invoice(p_invoice_id, p_method, p_reason, p_reference);
    perform private.finish_invoice_request('refund_invoice', p_request_id, request_response);
    return request_response;
end;
$$;
revoke all on function public.refund_invoice_idempotent(uuid, bigint, text, text, text) from public, anon;
grant execute on function public.refund_invoice_idempotent(uuid, bigint, text, text, text) to authenticated;
