begin;
insert into auth.users(id) values ('a0000000-0000-4000-8000-000000000013');
insert into public.app_access(user_id) values ('a0000000-0000-4000-8000-000000000013');
set local request.jwt.claim.sub = 'a0000000-0000-4000-8000-000000000013';
do $$
declare
    customer_id bigint;
    offering_id bigint;
    employee_id bigint;
    card_id bigint;
    row_value public.appointments%rowtype;
    timed_row public.appointments%rowtype;
    visit_date date := (now() at time zone 'Asia/Ho_Chi_Minh')::date;
begin
    insert into public.customers(code,name) values ('TEST-DATE-KH','Khách kiểm thử') returning id into customer_id;
    insert into public.offerings(code,name,price) values ('TEST-DATE-DV','Dịch vụ kiểm thử',100000) returning id into offering_id;
    insert into public.employees(code,name,job_title) values ('TEST-DATE-NV','Nhân viên kiểm thử','Kỹ thuật viên') returning id into employee_id;
    insert into public.customer_packages(customer_id,offering_id,code,purchased_on,expires_on,sessions_total)
      values (customer_id,offering_id,'TEST-LATE',visit_date,visit_date+30,5);
    insert into public.customer_packages(customer_id,offering_id,code,purchased_on,expires_on,sessions_total)
      values (customer_id,offering_id,'TEST-EARLY',visit_date,visit_date+5,5) returning id into card_id;
    insert into public.customer_packages(customer_id,offering_id,code,purchased_on,starts_on,expires_on,sessions_total)
      values (customer_id,offering_id,'TEST-FUTURE',visit_date,visit_date+1,visit_date+2,5);
    insert into public.customer_packages(customer_id,offering_id,code,purchased_on,expires_on,sessions_total)
      values (customer_id,offering_id,'TEST-EXPIRED',visit_date-10,visit_date-1,5);
    row_value := public.save_appointment(null,customer_id,offering_id,employee_id,null,null,visit_date,null,null,'package',null,'booked',null);
    assert row_value.start_time is null and row_value.end_time is null, 'Lưu lịch chỉ có ngày';
    assert row_value.customer_package_id = card_id, 'Tự chọn thẻ hợp lệ hết hạn sớm nhất';
    assert (select sessions_used=0 from public.customer_packages where id=card_id), 'Lưu lịch không trừ buổi';
    perform public.transition_appointment(row_value.id,'arrived');
    perform public.transition_appointment(row_value.id,'in_service');
    perform public.complete_appointment(row_value.id);
    assert (select sessions_used=1 from public.customer_packages where id=card_id), 'Hoàn thành trừ đúng một buổi';
    begin
      perform public.use_package_session(row_value.id,null);
      raise exception 'Không được trừ buổi hai lần';
    exception when sqlstate 'P0001' then
      assert sqlerrm <> 'Không được trừ buổi hai lần';
    end;
    row_value := public.save_appointment(null,customer_id,offering_id,employee_id,null,null,visit_date,null,null,'service',null,'booked',null);
    assert row_value.customer_package_id is null and row_value.payment_type='service', 'Không dùng thẻ là dịch vụ lẻ';
    timed_row := public.save_appointment(null,customer_id,offering_id,employee_id,null,null,visit_date,'09:00','10:00','service',null,'booked',null);
    timed_row := public.save_appointment(timed_row.id,customer_id,offering_id,employee_id,null,null,visit_date,null,null,'service',null,'booked','Sửa ghi chú');
    assert timed_row.start_time='09:00'::time and timed_row.end_time='10:00'::time, 'Sửa lịch cũ giữ giờ';
    begin
      perform public.save_appointment(null,customer_id,offering_id,employee_id,null,null,visit_date,'09:30','10:30','service',null,'booked',null);
      raise exception 'Phải chặn trùng giờ';
    exception when sqlstate 'P0001' then
      assert sqlerrm like '%trùng kỹ thuật viên%';
    end;
    begin
      perform public.save_appointment(null,customer_id,offering_id,null,null,null,visit_date,'09:00',null,'service',null,'booked',null);
      raise exception 'Phải chặn giờ thiếu';
    exception when sqlstate 'P0001' then
      assert sqlerrm like '%khung giờ chưa hợp lệ%';
    end;
    begin
      perform public.save_appointment(null,customer_id,offering_id,null,null,null,visit_date+6,null,null,'package',card_id,'booked',null);
      raise exception 'Phải chặn thẻ hết hạn';
    exception when sqlstate 'P0001' then
      assert sqlerrm like '%thẻ liệu trình phù hợp%';
    end;
    perform set_config('request.jwt.claim.sub','a0000000-0000-4000-8000-000000000014',true);
    begin
      perform public.save_appointment(null,customer_id,offering_id,null,null,null,visit_date,null,null,'service',null,'booked',null);
      raise exception 'Phải chặn tài khoản không có quyền';
    exception when insufficient_privilege then
      assert sqlerrm='Tài khoản không có quyền quản lý.';
    end;
end;
$$;
rollback;
