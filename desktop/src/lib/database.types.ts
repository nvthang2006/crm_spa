export type Json = string | number | boolean | null | { [key: string]: Json | undefined } | Json[];

export type Database = {
    graphql_public: {
        Tables: {
            [_ in never]: never;
        };
        Views: {
            [_ in never]: never;
        };
        Functions: {
            graphql: {
                Args: {
                    extensions?: Json;
                    operationName?: string;
                    query?: string;
                    variables?: Json;
                };
                Returns: Json;
            };
        };
        Enums: {
            [_ in never]: never;
        };
        CompositeTypes: {
            [_ in never]: never;
        };
    };
    public: {
        Tables: {
            app_access: {
                Row: {
                    created_at: string;
                    is_active: boolean;
                    user_id: string;
                };
                ComputedFields: never;
                Insert: {
                    created_at?: string;
                    is_active?: boolean;
                    user_id: string;
                };
                Update: {
                    created_at?: string;
                    is_active?: boolean;
                    user_id?: string;
                };
                Relationships: [];
            };
            appointments: {
                Row: {
                    appointment_date: string;
                    cancel_reason: string | null;
                    cancelled_at: string | null;
                    checked_in_at: string | null;
                    code: string;
                    completed_at: string | null;
                    created_at: string;
                    created_by: string | null;
                    customer_id: number;
                    customer_package_id: number | null;
                    end_time: string | null;
                    id: number;
                    notes: string | null;
                    offering_id: number;
                    override_conflict_reason: string | null;
                    payment_type: string;
                    primary_employee_id: number | null;
                    service_resource_id: number | null;
                    source: string;
                    start_time: string | null;
                    started_at: string | null;
                    status: string;
                    support_employee_id: number | null;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    appointment_date: string;
                    cancel_reason?: string | null;
                    cancelled_at?: string | null;
                    checked_in_at?: string | null;
                    code: string;
                    completed_at?: string | null;
                    created_at?: string;
                    created_by?: string | null;
                    customer_id: number;
                    customer_package_id?: number | null;
                    end_time: string | null;
                    id?: number;
                    notes?: string | null;
                    offering_id: number;
                    override_conflict_reason?: string | null;
                    payment_type?: string;
                    primary_employee_id?: number | null;
                    service_resource_id?: number | null;
                    source?: string;
                    start_time: string | null;
                    started_at?: string | null;
                    status?: string;
                    support_employee_id?: number | null;
                    updated_at?: string;
                };
                Update: {
                    appointment_date?: string;
                    cancel_reason?: string | null;
                    cancelled_at?: string | null;
                    checked_in_at?: string | null;
                    code?: string;
                    completed_at?: string | null;
                    created_at?: string;
                    created_by?: string | null;
                    customer_id?: number;
                    customer_package_id?: number | null;
                    end_time?: string | null;
                    id?: number;
                    notes?: string | null;
                    offering_id?: number;
                    override_conflict_reason?: string | null;
                    payment_type?: string;
                    primary_employee_id?: number | null;
                    service_resource_id?: number | null;
                    source?: string;
                    start_time?: string | null;
                    started_at?: string | null;
                    status?: string;
                    support_employee_id?: number | null;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "appointments_customer_id_fkey";
                        columns: ["customer_id"];
                        isOneToOne: false;
                        referencedRelation: "customers";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "appointments_customer_package_id_fkey";
                        columns: ["customer_package_id"];
                        isOneToOne: false;
                        referencedRelation: "customer_packages";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "appointments_offering_id_fkey";
                        columns: ["offering_id"];
                        isOneToOne: false;
                        referencedRelation: "offerings";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "appointments_primary_employee_id_fkey";
                        columns: ["primary_employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "appointments_service_resource_id_fkey";
                        columns: ["service_resource_id"];
                        isOneToOne: false;
                        referencedRelation: "service_resources";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "appointments_support_employee_id_fkey";
                        columns: ["support_employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                ];
            };
            attendance_records: {
                Row: {
                    attendance_date: string;
                    check_in_at: string | null;
                    check_out_at: string | null;
                    created_at: string;
                    employee_id: number;
                    id: number;
                    late_minutes: number;
                    notes: string | null;
                    recorded_by: string | null;
                    status: string;
                    updated_at: string;
                    work_schedule_id: number | null;
                };
                ComputedFields: never;
                Insert: {
                    attendance_date: string;
                    check_in_at?: string | null;
                    check_out_at?: string | null;
                    created_at?: string;
                    employee_id: number;
                    id?: number;
                    late_minutes?: number;
                    notes?: string | null;
                    recorded_by?: string | null;
                    status?: string;
                    updated_at?: string;
                    work_schedule_id?: number | null;
                };
                Update: {
                    attendance_date?: string;
                    check_in_at?: string | null;
                    check_out_at?: string | null;
                    created_at?: string;
                    employee_id?: number;
                    id?: number;
                    late_minutes?: number;
                    notes?: string | null;
                    recorded_by?: string | null;
                    status?: string;
                    updated_at?: string;
                    work_schedule_id?: number | null;
                };
                Relationships: [
                    {
                        foreignKeyName: "attendance_records_employee_id_fkey";
                        columns: ["employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "attendance_records_work_schedule_id_fkey";
                        columns: ["work_schedule_id"];
                        isOneToOne: false;
                        referencedRelation: "work_schedules";
                        referencedColumns: ["id"];
                    },
                ];
            };
            audit_logs: {
                Row: {
                    action: string;
                    after: Json | null;
                    before: Json | null;
                    created_at: string;
                    id: number;
                    ip_address: unknown;
                    reason: string | null;
                    subject_id: string | null;
                    subject_type: string | null;
                    user_id: string | null;
                };
                ComputedFields: never;
                Insert: {
                    action: string;
                    after?: Json | null;
                    before?: Json | null;
                    created_at: string;
                    id?: number;
                    ip_address?: unknown;
                    reason?: string | null;
                    subject_id?: string | null;
                    subject_type?: string | null;
                    user_id?: string | null;
                };
                Update: {
                    action?: string;
                    after?: Json | null;
                    before?: Json | null;
                    created_at?: string;
                    id?: number;
                    ip_address?: unknown;
                    reason?: string | null;
                    subject_id?: string | null;
                    subject_type?: string | null;
                    user_id?: string | null;
                };
                Relationships: [];
            };
            business_settings: {
                Row: {
                    address: string;
                    business_name: string;
                    id: number;
                    phone: string | null;
                    time_zone: string;
                    updated_at: string;
                    updated_by: string | null;
                };
                ComputedFields: never;
                Insert: {
                    address?: string;
                    business_name: string;
                    id?: number;
                    phone?: string | null;
                    time_zone?: string;
                    updated_at?: string;
                    updated_by?: string | null;
                };
                Update: {
                    address?: string;
                    business_name?: string;
                    id?: number;
                    phone?: string | null;
                    time_zone?: string;
                    updated_at?: string;
                    updated_by?: string | null;
                };
                Relationships: [];
            };
            commission_entries: {
                Row: {
                    amount: number;
                    appointment_id: number | null;
                    basis: string;
                    calculation_type: string;
                    commission_rule_id: number | null;
                    created_at: string;
                    employee_id: number;
                    id: number;
                    invoice_id: number | null;
                    occurred_at: string;
                    offering_id: number | null;
                    rate: number;
                    status: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    amount?: number;
                    appointment_id?: number | null;
                    basis: string;
                    calculation_type: string;
                    commission_rule_id?: number | null;
                    created_at?: string;
                    employee_id: number;
                    id?: number;
                    invoice_id?: number | null;
                    occurred_at: string;
                    offering_id?: number | null;
                    rate?: number;
                    status?: string;
                    updated_at?: string;
                };
                Update: {
                    amount?: number;
                    appointment_id?: number | null;
                    basis?: string;
                    calculation_type?: string;
                    commission_rule_id?: number | null;
                    created_at?: string;
                    employee_id?: number;
                    id?: number;
                    invoice_id?: number | null;
                    occurred_at?: string;
                    offering_id?: number | null;
                    rate?: number;
                    status?: string;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "commission_entries_appointment_id_fkey";
                        columns: ["appointment_id"];
                        isOneToOne: false;
                        referencedRelation: "appointments";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "commission_entries_commission_rule_id_fkey";
                        columns: ["commission_rule_id"];
                        isOneToOne: false;
                        referencedRelation: "commission_rules";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "commission_entries_employee_id_fkey";
                        columns: ["employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "commission_entries_invoice_id_fkey";
                        columns: ["invoice_id"];
                        isOneToOne: false;
                        referencedRelation: "invoices";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "commission_entries_offering_id_fkey";
                        columns: ["offering_id"];
                        isOneToOne: false;
                        referencedRelation: "offerings";
                        referencedColumns: ["id"];
                    },
                ];
            };
            commission_rules: {
                Row: {
                    basis: string;
                    beneficiary_employee_id: number | null;
                    beneficiary_type: string;
                    calculation_type: string;
                    created_at: string;
                    ends_on: string | null;
                    id: number;
                    is_active: boolean;
                    notes: string | null;
                    offering_id: number;
                    starts_on: string;
                    updated_at: string;
                    value: number;
                };
                ComputedFields: never;
                Insert: {
                    basis?: string;
                    beneficiary_employee_id?: number | null;
                    beneficiary_type?: string;
                    calculation_type?: string;
                    created_at?: string;
                    ends_on?: string | null;
                    id?: number;
                    is_active?: boolean;
                    notes?: string | null;
                    offering_id: number;
                    starts_on: string;
                    updated_at?: string;
                    value?: number;
                };
                Update: {
                    basis?: string;
                    beneficiary_employee_id?: number | null;
                    beneficiary_type?: string;
                    calculation_type?: string;
                    created_at?: string;
                    ends_on?: string | null;
                    id?: number;
                    is_active?: boolean;
                    notes?: string | null;
                    offering_id?: number;
                    starts_on?: string;
                    updated_at?: string;
                    value?: number;
                };
                Relationships: [
                    {
                        foreignKeyName: "commission_rules_beneficiary_employee_id_fkey";
                        columns: ["beneficiary_employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "commission_rules_offering_id_fkey";
                        columns: ["offering_id"];
                        isOneToOne: false;
                        referencedRelation: "offerings";
                        referencedColumns: ["id"];
                    },
                ];
            };
            customer_health_profiles: {
                Row: {
                    allergy_notes: string | null;
                    care_notes: string | null;
                    contraindications: string | null;
                    created_at: string;
                    current_condition: string | null;
                    customer_id: number;
                    id: number;
                    last_updated_by: string | null;
                    preferred_employee_id: number | null;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    allergy_notes?: string | null;
                    care_notes?: string | null;
                    contraindications?: string | null;
                    created_at?: string;
                    current_condition?: string | null;
                    customer_id: number;
                    id?: number;
                    last_updated_by?: string | null;
                    preferred_employee_id?: number | null;
                    updated_at?: string;
                };
                Update: {
                    allergy_notes?: string | null;
                    care_notes?: string | null;
                    contraindications?: string | null;
                    created_at?: string;
                    current_condition?: string | null;
                    customer_id?: number;
                    id?: number;
                    last_updated_by?: string | null;
                    preferred_employee_id?: number | null;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "customer_health_profiles_customer_id_fkey";
                        columns: ["customer_id"];
                        isOneToOne: true;
                        referencedRelation: "customers";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "customer_health_profiles_preferred_employee_id_fkey";
                        columns: ["preferred_employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                ];
            };
            customer_packages: {
                Row: {
                    code: string;
                    created_at: string;
                    customer_id: number;
                    expires_on: string | null;
                    id: number;
                    notes: string | null;
                    offering_id: number;
                    purchased_on: string;
                    sessions_total: number;
                    sessions_used: number;
                    starts_on: string | null;
                    status: string;
                    unit_price: number;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    code: string;
                    created_at?: string;
                    customer_id: number;
                    expires_on?: string | null;
                    id?: number;
                    notes?: string | null;
                    offering_id: number;
                    purchased_on: string;
                    sessions_total: number;
                    sessions_used?: number;
                    starts_on?: string | null;
                    status?: string;
                    unit_price?: number;
                    updated_at?: string;
                };
                Update: {
                    code?: string;
                    created_at?: string;
                    customer_id?: number;
                    expires_on?: string | null;
                    id?: number;
                    notes?: string | null;
                    offering_id?: number;
                    purchased_on?: string;
                    sessions_total?: number;
                    sessions_used?: number;
                    starts_on?: string | null;
                    status?: string;
                    unit_price?: number;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "customer_packages_customer_id_fkey";
                        columns: ["customer_id"];
                        isOneToOne: false;
                        referencedRelation: "customers";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "customer_packages_offering_id_fkey";
                        columns: ["offering_id"];
                        isOneToOne: false;
                        referencedRelation: "offerings";
                        referencedColumns: ["id"];
                    },
                ];
            };
            customers: {
                Row: {
                    address: string | null;
                    birthday: string | null;
                    code: string;
                    created_at: string;
                    deleted_at: string | null;
                    email: string | null;
                    id: number;
                    last_visit_at: string | null;
                    membership_tier: string;
                    name: string;
                    notes: string | null;
                    phone: string | null;
                    status: string;
                    total_spend: number;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    address?: string | null;
                    birthday?: string | null;
                    code: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    email?: string | null;
                    id?: number;
                    last_visit_at?: string | null;
                    membership_tier?: string;
                    name: string;
                    notes?: string | null;
                    phone: string | null;
                    status?: string;
                    total_spend?: number;
                    updated_at?: string;
                };
                Update: {
                    address?: string | null;
                    birthday?: string | null;
                    code?: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    email?: string | null;
                    id?: number;
                    last_visit_at?: string | null;
                    membership_tier?: string;
                    name?: string;
                    notes?: string | null;
                    phone?: string | null;
                    status?: string;
                    total_spend?: number;
                    updated_at?: string;
                };
                Relationships: [];
            };
            employees: {
                Row: {
                    base_salary: number | null;
                    code: string;
                    created_at: string;
                    deleted_at: string | null;
                    email: string | null;
                    id: number;
                    job_title: string;
                    name: string;
                    notes: string | null;
                    phone: string | null;
                    shift_name: string | null;
                    status: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    base_salary?: number | null;
                    code: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    email?: string | null;
                    id?: number;
                    job_title: string;
                    name: string;
                    notes?: string | null;
                    phone?: string | null;
                    shift_name?: string | null;
                    status?: string;
                    updated_at?: string;
                };
                Update: {
                    base_salary?: number | null;
                    code?: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    email?: string | null;
                    id?: number;
                    job_title?: string;
                    name?: string;
                    notes?: string | null;
                    phone?: string | null;
                    shift_name?: string | null;
                    status?: string;
                    updated_at?: string;
                };
                Relationships: [];
            };
            invoice_items: {
                Row: {
                    created_at: string;
                    description: string;
                    employee_id: number | null;
                    id: number;
                    invoice_id: number;
                    line_total: number;
                    offering_id: number | null;
                    package_sessions: number;
                    quantity: number;
                    unit_price: number;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    created_at?: string;
                    description: string;
                    employee_id?: number | null;
                    id?: number;
                    invoice_id: number;
                    line_total?: number;
                    offering_id?: number | null;
                    package_sessions?: number;
                    quantity?: number;
                    unit_price?: number;
                    updated_at?: string;
                };
                Update: {
                    created_at?: string;
                    description?: string;
                    employee_id?: number | null;
                    id?: number;
                    invoice_id?: number;
                    line_total?: number;
                    offering_id?: number | null;
                    package_sessions?: number;
                    quantity?: number;
                    unit_price?: number;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "invoice_items_employee_id_fkey";
                        columns: ["employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "invoice_items_invoice_id_fkey";
                        columns: ["invoice_id"];
                        isOneToOne: false;
                        referencedRelation: "invoices";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "invoice_items_offering_id_fkey";
                        columns: ["offering_id"];
                        isOneToOne: false;
                        referencedRelation: "offerings";
                        referencedColumns: ["id"];
                    },
                ];
            };
            invoice_refunds: {
                Row: {
                    amount: number;
                    code: string;
                    created_at: string;
                    id: number;
                    invoice_id: number;
                    method: string;
                    processed_by: string | null;
                    reason: string;
                    reference: string | null;
                    refunded_at: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    amount: number;
                    code: string;
                    created_at?: string;
                    id?: number;
                    invoice_id: number;
                    method: string;
                    processed_by?: string | null;
                    reason: string;
                    reference?: string | null;
                    refunded_at: string;
                    updated_at?: string;
                };
                Update: {
                    amount?: number;
                    code?: string;
                    created_at?: string;
                    id?: number;
                    invoice_id?: number;
                    method?: string;
                    processed_by?: string | null;
                    reason?: string;
                    reference?: string | null;
                    refunded_at?: string;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "invoice_refunds_invoice_id_fkey";
                        columns: ["invoice_id"];
                        isOneToOne: true;
                        referencedRelation: "invoices";
                        referencedColumns: ["id"];
                    },
                ];
            };
            invoices: {
                Row: {
                    appointment_id: number | null;
                    code: string;
                    created_at: string;
                    created_by: string | null;
                    customer_id: number;
                    customer_package_id: number | null;
                    daily_entry_key: string | null;
                    discount_amount: number;
                    id: number;
                    issued_at: string | null;
                    notes: string | null;
                    paid_at: string | null;
                    payment_method: string | null;
                    status: string;
                    subtotal: number;
                    total_received: number;
                    type: string;
                    updated_at: string;
                    voided_at: string | null;
                };
                ComputedFields: never;
                Insert: {
                    appointment_id?: number | null;
                    code: string;
                    created_at?: string;
                    created_by?: string | null;
                    customer_id: number;
                    customer_package_id?: number | null;
                    daily_entry_key?: string | null;
                    discount_amount?: number;
                    id?: number;
                    issued_at?: string | null;
                    notes?: string | null;
                    paid_at?: string | null;
                    payment_method?: string | null;
                    status?: string;
                    subtotal?: number;
                    total_received?: number;
                    type?: string;
                    updated_at?: string;
                    voided_at?: string | null;
                };
                Update: {
                    appointment_id?: number | null;
                    code?: string;
                    created_at?: string;
                    created_by?: string | null;
                    customer_id?: number;
                    customer_package_id?: number | null;
                    daily_entry_key?: string | null;
                    discount_amount?: number;
                    id?: number;
                    issued_at?: string | null;
                    notes?: string | null;
                    paid_at?: string | null;
                    payment_method?: string | null;
                    status?: string;
                    subtotal?: number;
                    total_received?: number;
                    type?: string;
                    updated_at?: string;
                    voided_at?: string | null;
                };
                Relationships: [
                    {
                        foreignKeyName: "invoices_appointment_id_fkey";
                        columns: ["appointment_id"];
                        isOneToOne: false;
                        referencedRelation: "appointments";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "invoices_customer_id_fkey";
                        columns: ["customer_id"];
                        isOneToOne: false;
                        referencedRelation: "customers";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "invoices_customer_package_id_fkey";
                        columns: ["customer_package_id"];
                        isOneToOne: false;
                        referencedRelation: "customer_packages";
                        referencedColumns: ["id"];
                    },
                ];
            };
            offerings: {
                Row: {
                    code: string;
                    created_at: string;
                    deleted_at: string | null;
                    description: string | null;
                    duration_minutes: number;
                    featured: boolean;
                    id: number;
                    name: string;
                    price: number | null;
                    service_category_id: number | null;
                    sessions_total: number;
                    status: string;
                    type: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    code: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    description?: string | null;
                    duration_minutes?: number;
                    featured?: boolean;
                    id?: number;
                    name: string;
                    price?: number | null;
                    service_category_id?: number | null;
                    sessions_total?: number;
                    status?: string;
                    type?: string;
                    updated_at?: string;
                };
                Update: {
                    code?: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    description?: string | null;
                    duration_minutes?: number;
                    featured?: boolean;
                    id?: number;
                    name?: string;
                    price?: number | null;
                    service_category_id?: number | null;
                    sessions_total?: number;
                    status?: string;
                    type?: string;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "offerings_service_category_id_fkey";
                        columns: ["service_category_id"];
                        isOneToOne: false;
                        referencedRelation: "service_categories";
                        referencedColumns: ["id"];
                    },
                ];
            };
            operational_alert_reads: {
                Row: {
                    created_at: string;
                    id: number;
                    operational_alert_id: number;
                    read_at: string;
                    updated_at: string;
                    user_id: string;
                };
                ComputedFields: never;
                Insert: {
                    created_at?: string;
                    id?: number;
                    operational_alert_id: number;
                    read_at: string;
                    updated_at?: string;
                    user_id: string;
                };
                Update: {
                    created_at?: string;
                    id?: number;
                    operational_alert_id?: number;
                    read_at?: string;
                    updated_at?: string;
                    user_id?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "operational_alert_reads_operational_alert_id_fkey";
                        columns: ["operational_alert_id"];
                        isOneToOne: false;
                        referencedRelation: "operational_alerts";
                        referencedColumns: ["id"];
                    },
                ];
            };
            operational_alerts: {
                Row: {
                    action_url: string | null;
                    alert_key: string;
                    context: Json | null;
                    created_at: string;
                    id: number;
                    is_active: boolean;
                    message: string;
                    resolved_at: string | null;
                    severity: string;
                    title: string;
                    type: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    action_url?: string | null;
                    alert_key: string;
                    context?: Json | null;
                    created_at?: string;
                    id?: number;
                    is_active?: boolean;
                    message: string;
                    resolved_at?: string | null;
                    severity?: string;
                    title: string;
                    type: string;
                    updated_at?: string;
                };
                Update: {
                    action_url?: string | null;
                    alert_key?: string;
                    context?: Json | null;
                    created_at?: string;
                    id?: number;
                    is_active?: boolean;
                    message?: string;
                    resolved_at?: string | null;
                    severity?: string;
                    title?: string;
                    type?: string;
                    updated_at?: string;
                };
                Relationships: [];
            };
            package_usages: {
                Row: {
                    appointment_id: number | null;
                    created_at: string;
                    customer_package_id: number;
                    direction: string;
                    id: number;
                    performed_by: number | null;
                    recorded_by: string | null;
                    session_number: number;
                    updated_at: string;
                    used_at: string;
                    verification_method: string;
                    verification_note: string | null;
                };
                ComputedFields: never;
                Insert: {
                    appointment_id?: number | null;
                    created_at?: string;
                    customer_package_id: number;
                    direction?: string;
                    id?: number;
                    performed_by?: number | null;
                    recorded_by?: string | null;
                    session_number: number;
                    updated_at?: string;
                    used_at: string;
                    verification_method?: string;
                    verification_note?: string | null;
                };
                Update: {
                    appointment_id?: number | null;
                    created_at?: string;
                    customer_package_id?: number;
                    direction?: string;
                    id?: number;
                    performed_by?: number | null;
                    recorded_by?: string | null;
                    session_number?: number;
                    updated_at?: string;
                    used_at?: string;
                    verification_method?: string;
                    verification_note?: string | null;
                };
                Relationships: [
                    {
                        foreignKeyName: "package_usages_appointment_id_fkey";
                        columns: ["appointment_id"];
                        isOneToOne: false;
                        referencedRelation: "appointments";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "package_usages_customer_package_id_fkey";
                        columns: ["customer_package_id"];
                        isOneToOne: false;
                        referencedRelation: "customer_packages";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "package_usages_performed_by_fkey";
                        columns: ["performed_by"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                ];
            };
            payments: {
                Row: {
                    amount: number;
                    created_at: string;
                    id: number;
                    invoice_id: number;
                    method: string;
                    paid_at: string;
                    receipt_code: string;
                    received_by: string | null;
                    reference: string | null;
                    status: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    amount: number;
                    created_at?: string;
                    id?: number;
                    invoice_id: number;
                    method: string;
                    paid_at: string;
                    receipt_code: string;
                    received_by?: string | null;
                    reference?: string | null;
                    status?: string;
                    updated_at?: string;
                };
                Update: {
                    amount?: number;
                    created_at?: string;
                    id?: number;
                    invoice_id?: number;
                    method?: string;
                    paid_at?: string;
                    receipt_code?: string;
                    received_by?: string | null;
                    reference?: string | null;
                    status?: string;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "payments_invoice_id_fkey";
                        columns: ["invoice_id"];
                        isOneToOne: false;
                        referencedRelation: "invoices";
                        referencedColumns: ["id"];
                    },
                ];
            };
            payroll_adjustments: {
                Row: {
                    amount: number;
                    code: string;
                    created_at: string;
                    created_by: string | null;
                    employee_id: number;
                    id: number;
                    payroll_period_id: number;
                    reason: string;
                    reviewed_at: string | null;
                    reviewed_by: string | null;
                    status: string;
                    type: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    amount: number;
                    code: string;
                    created_at?: string;
                    created_by?: string | null;
                    employee_id: number;
                    id?: number;
                    payroll_period_id: number;
                    reason: string;
                    reviewed_at?: string | null;
                    reviewed_by?: string | null;
                    status?: string;
                    type: string;
                    updated_at?: string;
                };
                Update: {
                    amount?: number;
                    code?: string;
                    created_at?: string;
                    created_by?: string | null;
                    employee_id?: number;
                    id?: number;
                    payroll_period_id?: number;
                    reason?: string;
                    reviewed_at?: string | null;
                    reviewed_by?: string | null;
                    status?: string;
                    type?: string;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "payroll_adjustments_employee_id_fkey";
                        columns: ["employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "payroll_adjustments_payroll_period_id_fkey";
                        columns: ["payroll_period_id"];
                        isOneToOne: false;
                        referencedRelation: "payroll_periods";
                        referencedColumns: ["id"];
                    },
                ];
            };
            payroll_lines: {
                Row: {
                    base_salary: number;
                    commission_amount: number;
                    created_at: string;
                    deductions: number;
                    employee_id: number;
                    id: number;
                    net_pay: number;
                    payroll_period_id: number;
                    status: string;
                    tips: number;
                    updated_at: string;
                    work_days: number;
                };
                ComputedFields: never;
                Insert: {
                    base_salary?: number;
                    commission_amount?: number;
                    created_at?: string;
                    deductions?: number;
                    employee_id: number;
                    id?: number;
                    net_pay?: number;
                    payroll_period_id: number;
                    status?: string;
                    tips?: number;
                    updated_at?: string;
                    work_days?: number;
                };
                Update: {
                    base_salary?: number;
                    commission_amount?: number;
                    created_at?: string;
                    deductions?: number;
                    employee_id?: number;
                    id?: number;
                    net_pay?: number;
                    payroll_period_id?: number;
                    status?: string;
                    tips?: number;
                    updated_at?: string;
                    work_days?: number;
                };
                Relationships: [
                    {
                        foreignKeyName: "payroll_lines_employee_id_fkey";
                        columns: ["employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                    {
                        foreignKeyName: "payroll_lines_payroll_period_id_fkey";
                        columns: ["payroll_period_id"];
                        isOneToOne: false;
                        referencedRelation: "payroll_periods";
                        referencedColumns: ["id"];
                    },
                ];
            };
            payroll_periods: {
                Row: {
                    code: string;
                    created_at: string;
                    id: number;
                    locked_at: string | null;
                    locked_by: string | null;
                    period_end: string;
                    period_start: string;
                    status: string;
                    total_base_salary: number;
                    total_commission: number;
                    total_net_pay: number;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    code: string;
                    created_at?: string;
                    id?: number;
                    locked_at?: string | null;
                    locked_by?: string | null;
                    period_end: string;
                    period_start: string;
                    status?: string;
                    total_base_salary?: number;
                    total_commission?: number;
                    total_net_pay?: number;
                    updated_at?: string;
                };
                Update: {
                    code?: string;
                    created_at?: string;
                    id?: number;
                    locked_at?: string | null;
                    locked_by?: string | null;
                    period_end?: string;
                    period_start?: string;
                    status?: string;
                    total_base_salary?: number;
                    total_commission?: number;
                    total_net_pay?: number;
                    updated_at?: string;
                };
                Relationships: [];
            };
            resource_areas: {
                Row: {
                    code: string;
                    created_at: string;
                    deleted_at: string | null;
                    description: string | null;
                    floor: string | null;
                    id: number;
                    name: string;
                    status: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    code: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    description?: string | null;
                    floor?: string | null;
                    id?: number;
                    name: string;
                    status?: string;
                    updated_at?: string;
                };
                Update: {
                    code?: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    description?: string | null;
                    floor?: string | null;
                    id?: number;
                    name?: string;
                    status?: string;
                    updated_at?: string;
                };
                Relationships: [];
            };
            service_categories: {
                Row: {
                    code: string;
                    created_at: string;
                    deleted_at: string | null;
                    id: number;
                    is_active: boolean;
                    name: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    code: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    id?: number;
                    is_active?: boolean;
                    name: string;
                    updated_at?: string;
                };
                Update: {
                    code?: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    id?: number;
                    is_active?: boolean;
                    name?: string;
                    updated_at?: string;
                };
                Relationships: [];
            };
            service_resources: {
                Row: {
                    code: string;
                    created_at: string;
                    deleted_at: string | null;
                    id: number;
                    maintenance_note: string | null;
                    maintenance_until: string | null;
                    name: string;
                    resource_area_id: number;
                    resource_type: string;
                    status: string;
                    updated_at: string;
                };
                ComputedFields: never;
                Insert: {
                    code: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    id?: number;
                    maintenance_note?: string | null;
                    maintenance_until?: string | null;
                    name: string;
                    resource_area_id: number;
                    resource_type?: string;
                    status?: string;
                    updated_at?: string;
                };
                Update: {
                    code?: string;
                    created_at?: string;
                    deleted_at?: string | null;
                    id?: number;
                    maintenance_note?: string | null;
                    maintenance_until?: string | null;
                    name?: string;
                    resource_area_id?: number;
                    resource_type?: string;
                    status?: string;
                    updated_at?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "service_resources_resource_area_id_fkey";
                        columns: ["resource_area_id"];
                        isOneToOne: false;
                        referencedRelation: "resource_areas";
                        referencedColumns: ["id"];
                    },
                ];
            };
            work_schedules: {
                Row: {
                    created_at: string;
                    employee_id: number;
                    ends_at: string;
                    id: number;
                    notes: string | null;
                    shift_name: string;
                    starts_at: string;
                    status: string;
                    updated_at: string;
                    work_date: string;
                };
                ComputedFields: never;
                Insert: {
                    created_at?: string;
                    employee_id: number;
                    ends_at: string;
                    id?: number;
                    notes?: string | null;
                    shift_name: string;
                    starts_at: string;
                    status?: string;
                    updated_at?: string;
                    work_date: string;
                };
                Update: {
                    created_at?: string;
                    employee_id?: number;
                    ends_at?: string;
                    id?: number;
                    notes?: string | null;
                    shift_name?: string;
                    starts_at?: string;
                    status?: string;
                    updated_at?: string;
                    work_date?: string;
                };
                Relationships: [
                    {
                        foreignKeyName: "work_schedules_employee_id_fkey";
                        columns: ["employee_id"];
                        isOneToOne: false;
                        referencedRelation: "employees";
                        referencedColumns: ["id"];
                    },
                ];
            };
        };
        Views: {
            [_ in never]: never;
        };
        Functions: {
            // Sinh bằng Supabase CLI; kiểu null của tham số RPC được đối chiếu với migration PostgreSQL.
            record_daily_shift: {
                Args: {
                    p_request_id: string;
                    p_customer_id: number;
                    p_offering_id: number;
                    p_employee_id: number;
                    p_classification: string;
                    p_collected: number;
                    p_work_date: string;
                    p_payment_method: string;
                    p_customer_package_id?: number | null;
                    p_commission_type?: string | null;
                    p_commission_value?: number | null;
                    p_notes?: string | null;
                };
                Returns: Json;
            };
            complete_appointment: { Args: { p_appointment_id: number }; Returns: Json };
            consume_customer_package_session: {
                Args: {
                    p_customer_package_id: number;
                    p_note?: string | null;
                    p_performed_by: number | null;
                };
                Returns: Json;
            };
            create_payroll_adjustment: {
                Args: {
                    p_amount: number;
                    p_employee_id: number;
                    p_period_id: number;
                    p_reason: string;
                    p_type: string;
                };
                Returns: Database['public']['Tables']['payroll_adjustments']['Row'];
            };
            generate_payroll: {
                Args: {
                    p_period_end: string;
                    p_period_start: string;
                };
                Returns: Database['public']['Tables']['payroll_periods']['Row'];
            };
            lock_payroll: {
                Args: {
                    p_period_id: number;
                };
                Returns: Database['public']['Tables']['payroll_periods']['Row'];
            };
            recalculate_payroll: {
                Args: {
                    p_period_id: number;
                };
                Returns: Database['public']['Tables']['payroll_periods']['Row'];
            };
            review_payroll_adjustment: {
                Args: {
                    p_adjustment_id: number;
                    p_approve: boolean;
                    p_reason?: string | null;
                };
                Returns: Database['public']['Tables']['payroll_adjustments']['Row'];
            };
            create_invoice: {
                Args: {
                    p_appointment_id: number | null;
                    p_customer_id: number;
                    p_discount_amount: number;
                    p_employee_id: number | null;
                    p_offering_id: number;
                };
                Returns: Json;
            };
            create_invoice_idempotent: {
                Args: {
                    p_appointment_id: number | null;
                    p_customer_id: number;
                    p_discount_amount: number;
                    p_employee_id: number | null;
                    p_offering_id: number;
                    p_request_id: string;
                };
                Returns: Json;
            };
            get_customer_health_profile: { Args: { p_customer_id: number }; Returns: Json };
            has_app_access: { Args: Record<PropertyKey, never>; Returns: boolean };
            pay_invoice: {
                Args: { p_invoice_id: number; p_method: string; p_reference?: string | null };
                Returns: Json;
            };
            pay_invoice_idempotent: {
                Args: {
                    p_invoice_id: number;
                    p_method: string;
                    p_reference: string | null;
                    p_request_id: string;
                };
                Returns: Json;
            };
            refund_invoice: {
                Args: {
                    p_invoice_id: number;
                    p_method: string;
                    p_reason: string;
                    p_reference?: string | null;
                };
                Returns: Json;
            };
            refund_invoice_idempotent: {
                Args: {
                    p_invoice_id: number;
                    p_method: string;
                    p_reason: string;
                    p_reference: string | null;
                    p_request_id: string;
                };
                Returns: Json;
            };
            restore_customer_package_session: {
                Args: { p_customer_package_id: number; p_reason: string; p_session_number: number };
                Returns: Json;
            };
            save_appointment: {
                Args: {
                    p_appointment_date: string;
                    p_appointment_id: number | null;
                    p_customer_id: number;
                    p_customer_package_id: number | null;
                    p_end_time: string | null;
                    p_notes: string | null;
                    p_offering_id: number;
                    p_override_reason?: string | null;
                    p_payment_type: string;
                    p_primary_employee_id: number | null;
                    p_service_resource_id: number | null;
                    p_source: string;
                    p_start_time: string | null;
                    p_support_employee_id: number | null;
                };
                Returns: {
                    appointment_date: string;
                    cancel_reason: string | null;
                    cancelled_at: string | null;
                    checked_in_at: string | null;
                    code: string;
                    completed_at: string | null;
                    created_at: string;
                    created_by: string | null;
                    customer_id: number;
                    customer_package_id: number | null;
                    end_time: string | null;
                    id: number;
                    notes: string | null;
                    offering_id: number;
                    override_conflict_reason: string | null;
                    payment_type: string;
                    primary_employee_id: number | null;
                    service_resource_id: number | null;
                    source: string;
                    start_time: string | null;
                    started_at: string | null;
                    status: string;
                    support_employee_id: number | null;
                    updated_at: string;
                };
                SetofOptions: {
                    from: "*";
                    to: "appointments";
                    isOneToOne: true;
                    isSetofReturn: false;
                };
            };
            save_business_settings: {
                Args: { p_address: string; p_business_name: string; p_phone: string | null };
                Returns: undefined;
            };
            save_commission_rule: {
                Args: {
                    p_basis: string;
                    p_beneficiary_employee_id: number | null;
                    p_beneficiary_type: string;
                    p_calculation_type: string;
                    p_ends_on: string | null;
                    p_is_active: boolean;
                    p_notes: string | null;
                    p_offering_id: number;
                    p_rule_id: number | null;
                    p_starts_on: string;
                    p_value: number;
                };
                Returns: number;
            };
            save_customer: {
                Args: {
                    p_address: string | null;
                    p_allergy_notes: string | null;
                    p_birthday: string | null;
                    p_care_notes: string | null;
                    p_code: string;
                    p_contraindications: string | null;
                    p_current_condition: string | null;
                    p_customer_id: number | null;
                    p_email: string | null;
                    p_membership_tier: string;
                    p_name: string;
                    p_notes: string | null;
                    p_phone: string | null;
                    p_preferred_employee_id: number | null;
                    p_update_health: boolean;
                };
                Returns: {
                    address: string | null;
                    birthday: string | null;
                    code: string;
                    created_at: string;
                    deleted_at: string | null;
                    email: string | null;
                    id: number;
                    last_visit_at: string | null;
                    membership_tier: string;
                    name: string;
                    notes: string | null;
                    phone: string | null;
                    status: string;
                    total_spend: number;
                    updated_at: string;
                };
                SetofOptions: {
                    from: "*";
                    to: "customers";
                    isOneToOne: true;
                    isSetofReturn: false;
                };
            };
            save_employee: {
                Args: {
                    p_base_salary: number | null;
                    p_code: string;
                    p_employee_id: number | null;
                    p_job_title: string;
                    p_name: string;
                    p_phone: string | null;
                    p_shift_name: string | null;
                };
                Returns: number;
            };
            save_offering: {
                Args: {
                    p_code: string;
                    p_description: string | null;
                    p_duration_minutes: number;
                    p_featured: boolean;
                    p_name: string;
                    p_offering_id: number | null;
                    p_price: number | null;
                    p_sessions_total: number;
                    p_status: string;
                    p_type: string;
                };
                Returns: number;
            };
            search_invoice_ids: {
                Args: {
                    p_date_from: string | null;
                    p_date_to: string | null;
                    p_limit: number;
                    p_method: string;
                    p_offset: number;
                    p_search: string;
                    p_status: string;
                };
                Returns: {
                    invoice_id: number;
                    total_count: number;
                }[];
            };
            set_commission_rule_active: { Args: { p_rule_id: number }; Returns: boolean };
            set_employee_status: { Args: { p_employee_id: number }; Returns: string };
            transition_appointment: {
                Args: { p_appointment_id: number; p_reason?: string | null; p_status: string };
                Returns: {
                    appointment_date: string;
                    cancel_reason: string | null;
                    cancelled_at: string | null;
                    checked_in_at: string | null;
                    code: string;
                    completed_at: string | null;
                    created_at: string;
                    created_by: string | null;
                    customer_id: number;
                    customer_package_id: number | null;
                    end_time: string | null;
                    id: number;
                    notes: string | null;
                    offering_id: number;
                    override_conflict_reason: string | null;
                    payment_type: string;
                    primary_employee_id: number | null;
                    service_resource_id: number | null;
                    source: string;
                    start_time: string | null;
                    started_at: string | null;
                    status: string;
                    support_employee_id: number | null;
                    updated_at: string;
                };
                SetofOptions: {
                    from: "*";
                    to: "appointments";
                    isOneToOne: true;
                    isSetofReturn: false;
                };
            };
            use_package_session: {
                Args: { p_appointment_id: number; p_note?: string | null };
                Returns: Json;
            };
            void_invoice: {
                Args: { p_invoice_id: number; p_reason: string };
                Returns: {
                    appointment_id: number | null;
                    code: string;
                    created_at: string;
                    created_by: string | null;
                    customer_id: number;
                    customer_package_id: number | null;
                    daily_entry_key: string | null;
                    discount_amount: number;
                    id: number;
                    issued_at: string | null;
                    notes: string | null;
                    paid_at: string | null;
                    payment_method: string | null;
                    status: string;
                    subtotal: number;
                    total_received: number;
                    type: string;
                    updated_at: string;
                    voided_at: string | null;
                };
                SetofOptions: {
                    from: "*";
                    to: "invoices";
                    isOneToOne: true;
                    isSetofReturn: false;
                };
            };
            void_invoice_idempotent: {
                Args: { p_invoice_id: number; p_reason: string; p_request_id: string };
                Returns: Json;
            };
        };
        Enums: {
            [_ in never]: never;
        };
        CompositeTypes: {
            [_ in never]: never;
        };
    };
};

type DatabaseWithoutInternals = Omit<Database, "__InternalSupabase">;

type DefaultSchema = DatabaseWithoutInternals[Extract<keyof Database, "public">];

export type Tables<
    DefaultSchemaTableNameOrOptions extends
        | keyof (DefaultSchema["Tables"] & DefaultSchema["Views"])
        | { schema: keyof DatabaseWithoutInternals },
    TableName extends (DefaultSchemaTableNameOrOptions extends {
        schema: keyof DatabaseWithoutInternals;
    }
        ? keyof (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
              DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])
        : never) = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof DatabaseWithoutInternals }
    ? (DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"] &
          DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Views"])[TableName] extends {
          Row: infer R;
      }
        ? R
        : never
    : DefaultSchemaTableNameOrOptions extends keyof (DefaultSchema["Tables"] &
            DefaultSchema["Views"])
      ? (DefaultSchema["Tables"] &
            DefaultSchema["Views"])[DefaultSchemaTableNameOrOptions] extends {
            Row: infer R;
        }
          ? R
          : never
      : never;

export type TablesInsert<
    DefaultSchemaTableNameOrOptions extends
        | keyof DefaultSchema["Tables"]
        | { schema: keyof DatabaseWithoutInternals },
    TableName extends (DefaultSchemaTableNameOrOptions extends {
        schema: keyof DatabaseWithoutInternals;
    }
        ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
        : never) = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof DatabaseWithoutInternals }
    ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
          Insert: infer I;
      }
        ? I
        : never
    : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
      ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
            Insert: infer I;
        }
          ? I
          : never
      : never;

export type TablesUpdate<
    DefaultSchemaTableNameOrOptions extends
        | keyof DefaultSchema["Tables"]
        | { schema: keyof DatabaseWithoutInternals },
    TableName extends (DefaultSchemaTableNameOrOptions extends {
        schema: keyof DatabaseWithoutInternals;
    }
        ? keyof DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"]
        : never) = never,
> = DefaultSchemaTableNameOrOptions extends { schema: keyof DatabaseWithoutInternals }
    ? DatabaseWithoutInternals[DefaultSchemaTableNameOrOptions["schema"]]["Tables"][TableName] extends {
          Update: infer U;
      }
        ? U
        : never
    : DefaultSchemaTableNameOrOptions extends keyof DefaultSchema["Tables"]
      ? DefaultSchema["Tables"][DefaultSchemaTableNameOrOptions] extends {
            Update: infer U;
        }
          ? U
          : never
      : never;

export type Enums<
    DefaultSchemaEnumNameOrOptions extends
        | keyof DefaultSchema["Enums"]
        | { schema: keyof DatabaseWithoutInternals },
    EnumName extends (DefaultSchemaEnumNameOrOptions extends {
        schema: keyof DatabaseWithoutInternals;
    }
        ? keyof DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"]
        : never) = never,
> = DefaultSchemaEnumNameOrOptions extends { schema: keyof DatabaseWithoutInternals }
    ? DatabaseWithoutInternals[DefaultSchemaEnumNameOrOptions["schema"]]["Enums"][EnumName]
    : DefaultSchemaEnumNameOrOptions extends keyof DefaultSchema["Enums"]
      ? DefaultSchema["Enums"][DefaultSchemaEnumNameOrOptions]
      : never;

export type CompositeTypes<
    PublicCompositeTypeNameOrOptions extends
        | keyof DefaultSchema["CompositeTypes"]
        | { schema: keyof DatabaseWithoutInternals },
    CompositeTypeName extends (PublicCompositeTypeNameOrOptions extends {
        schema: keyof DatabaseWithoutInternals;
    }
        ? keyof DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"]
        : never) = never,
> = PublicCompositeTypeNameOrOptions extends { schema: keyof DatabaseWithoutInternals }
    ? DatabaseWithoutInternals[PublicCompositeTypeNameOrOptions["schema"]]["CompositeTypes"][CompositeTypeName]
    : PublicCompositeTypeNameOrOptions extends keyof DefaultSchema["CompositeTypes"]
      ? DefaultSchema["CompositeTypes"][PublicCompositeTypeNameOrOptions]
      : never;

export const Constants = {
    graphql_public: {
        Enums: {},
    },
    public: {
        Enums: {},
    },
} as const;
