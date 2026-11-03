-- Đồng bộ kiểm tra ngày hữu hạn cho máy chủ đã nhận phiên bản RPC trước đó.
do $migration$
declare
    function_definition text;
begin
    function_definition := pg_catalog.pg_get_functiondef('public.record_daily_shift(uuid,bigint,bigint,bigint,text,bigint,date,text,bigint,text,numeric,text)'::regprocedure);
    execute pg_catalog.replace(function_definition,
        'or p_work_date is null or p_payment_method',
        'or p_work_date is null or not pg_catalog.isfinite(p_work_date) or p_payment_method');
end;
$migration$;
notify pgrst, 'reload schema';
