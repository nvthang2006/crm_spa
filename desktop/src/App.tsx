import { Popup } from "./Popup";
import {
    lazy,
    Suspense,
    useEffect,
    useMemo,
    useRef,
    useState,
    type FormEvent,
} from "react";
import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import {
    ArrowUpRight,
    BadgeCheck,
    Banknote,
    CalendarDays,
    ChevronLeft,
    ChevronRight,
    CreditCard,
    Ellipsis,
    Flower2,
    LayoutDashboard,
    LogOut,
    Plus,
    RefreshCw,
    Search,
    Settings2,
    ShieldCheck,
    Sparkles,
    UsersRound,
    WalletCards,
    type LucideIcon,
} from "lucide-react";
import { type Session } from "@supabase/supabase-js";
import { check, type Update } from "@tauri-apps/plugin-updater";
import { isTauri } from "@tauri-apps/api/core";
import { toast, Toaster } from "sonner";
import { getSupabase, hasDesktopConfiguration } from "./lib/supabase";
import {
    formatVnd,
    loadOfferings,
    saveOffering,
    type Offering,
    type OfferingDraft,
} from "./lib/offerings";
import { loadBusinessSettings } from "./lib/team";
import "./styles.css";

const AppointmentsWorkbench = lazy(
    () => import("./features/appointments/AppointmentsWorkbench"),
);
const CustomersWorkbench = lazy(
    () => import("./features/customers/CustomersWorkbench"),
);
const InvoicesWorkbench = lazy(
    () => import("./features/invoices/InvoicesWorkbench"),
);
const TeamWorkbench = lazy(() => import("./features/team/TeamWorkbench"));
const BusinessSettingsPage = lazy(
    () => import("./features/team/BusinessSettingsPage"),
);
const DailyRevenueWorkbench = lazy(
    () => import("./features/daily-revenue/DailyRevenueWorkbench"),
);
const PayrollWorkbench = lazy(
    () => import("./features/payroll/PayrollWorkbench"),
);

type Section =
    | "overview"
    | "daily-revenue"
    | "services"
    | "appointments"
    | "customers"
    | "invoices"
    | "team"
    | "payroll"
    | "settings";
const sections: {
    id: Section;
    label: string;
    icon: LucideIcon;
    hidden?: boolean;
}[] = [
    { id: "overview", label: "Tổng quan", icon: LayoutDashboard },
    { id: "daily-revenue", label: "Nhập ca cuối ngày", icon: Banknote },
    { id: "appointments", label: "Lịch hẹn", icon: CalendarDays },
    { id: "customers", label: "Khách hàng", icon: UsersRound },
    {
        id: "services",
        label: "Dịch vụ & gói liệu trình",
        icon: Flower2,
    },
    { id: "invoices", label: "Hóa đơn", icon: WalletCards },
    { id: "team", label: "Nhân sự", icon: BadgeCheck },
    { id: "payroll", label: "Bảng lương", icon: CreditCard },
    { id: "settings", label: "Cấu hình cơ sở", icon: Settings2, hidden: true },
];
const emptyOffering: OfferingDraft = {
    code: "",
    name: "",
    type: "service",
    duration_minutes: 60,
    price: null,
    sessions_total: 1,
    status: "active",
    featured: false,
    description: "",
    commission: {
        enabled: false,
        ruleId: null,
        calculationType: "fixed",
        value: 0,
    },
};
const startedAt = new Date();

export default function App() {
    const supabase = useMemo(() => getSupabase(), []);
    const queryClient = useQueryClient();
    const [session, setSession] = useState<Session | null>(null);
    const [ready, setReady] = useState(!supabase);
    const [currentDate, setCurrentDate] = useState(startedAt);
    const [section, setSection] = useState<Section>("overview");
    const [search, setSearch] = useState("");
    const [typeFilter, setTypeFilter] = useState("all");
    const [statusFilter, setStatusFilter] = useState("all");
    const [offeringPage, setOfferingPage] = useState(0);
    const [performancePage, setPerformancePage] = useState(0);
    const offeringScroll = useRef<HTMLDivElement>(null);
    const performanceScroll = useRef<HTMLDivElement>(null);
    const [editing, setEditing] = useState<Offering | null | false>(false);
    const [appointmentEditing, setAppointmentEditing] = useState(false);
    const [customerEditing, setCustomerEditing] = useState(false);
    const [invoiceEditing, setInvoiceEditing] = useState(false);
    const [teamEditing, setTeamEditing] = useState(false);
    const [settingsEditing, setSettingsEditing] = useState(false);
    const [dailyRevenueEditing, setDailyRevenueEditing] = useState(false);
    const [payrollEditing, setPayrollEditing] = useState(false);
    const [draft, setDraft] = useState<OfferingDraft>(emptyOffering);
    const [availableUpdate, setAvailableUpdate] = useState<Update | null>(null);
    const [updateDownloaded, setUpdateDownloaded] = useState(false);
    const [updateBusy, setUpdateBusy] = useState(false);
    const [updateDismissed, setUpdateDismissed] = useState(false);

    useEffect(() => {
        if (!supabase) return;
        void supabase.auth.getSession().then(({ data }) => {
            setSession(data.session);
            setReady(true);
        });
        const { data } = supabase.auth.onAuthStateChange((_event, value) => {
            setSession(value);
            if (!value) void queryClient.clear();
        });
        return () => data.subscription.unsubscribe();
    }, [queryClient, supabase]);

    useEffect(() => {
        const refreshDate = () => setCurrentDate(new Date());
        const timer = window.setInterval(refreshDate, 60_000);
        window.addEventListener("focus", refreshDate);
        return () => {
            window.clearInterval(timer);
            window.removeEventListener("focus", refreshDate);
        };
    }, []);

    useEffect(() => {
        if (!ready || !session || !isTauri()) return;
        let cancelled = false;
        void check()
            .then((update) => {
                if (cancelled) {
                    update?.close();
                    return;
                }
                setAvailableUpdate(update);
                setUpdateDownloaded(false);
                setUpdateDismissed(false);
            })
            .catch(() => undefined);
        return () => {
            cancelled = true;
        };
    }, [ready, session]);

    async function checkForUpdates() {
        if (!isTauri()) {
            toast.info("Cập nhật chỉ có trong ứng dụng Windows.");
            return;
        }
        setUpdateBusy(true);
        try {
            const update = await check();
            setAvailableUpdate(update);
            setUpdateDownloaded(false);
            setUpdateDismissed(false);
            toast[update ? "info" : "success"](
                update
                    ? `Đã có phiên bản ${update.version}.`
                    : "Bạn đang dùng phiên bản mới nhất.",
            );
        } catch {
            toast.error(
                "Chưa kiểm tra được bản cập nhật. Kiểm tra kết nối rồi thử lại.",
            );
        } finally {
            setUpdateBusy(false);
        }
    }

    async function downloadUpdate() {
        if (!availableUpdate) return;
        setUpdateBusy(true);
        try {
            await availableUpdate.download();
            setUpdateDownloaded(true);
            toast.success(
                "Đã tải bản cập nhật. Cài đặt khi bạn sẵn sàng khởi động lại.",
            );
        } catch {
            toast.error(
                "Không tải được bản cập nhật. Thử lại khi kết nối ổn định.",
            );
        } finally {
            setUpdateBusy(false);
        }
    }

    async function installUpdate() {
        if (
            !availableUpdate ||
            save.isPending ||
            editing !== false ||
            appointmentEditing ||
            customerEditing ||
            invoiceEditing ||
            teamEditing ||
            settingsEditing ||
            dailyRevenueEditing ||
            payrollEditing
        )
            return;
        setUpdateBusy(true);
        try {
            await availableUpdate.install();
        } catch {
            setUpdateBusy(false);
            toast.error("Không cài được bản cập nhật. Bạn có thể thử lại sau.");
        }
    }

    const access = useQuery({
        queryKey: ["manager-access", session?.user.id],
        enabled: Boolean(session && supabase),
        retry: false,
        queryFn: async () => {
            const { data, error } = await supabase!.rpc("has_app_access");
            if (error) throw error;
            return data === true;
        },
    });
    const businessSettings = useQuery({
        queryKey: ["business-settings"],
        enabled: Boolean(access.data && supabase),
        queryFn: () => loadBusinessSettings(supabase!),
        staleTime: 60_000,
    });
    const offerings = useQuery({
        queryKey: ["offerings"],
        enabled: Boolean(access.data && section === "services"),
        queryFn: () => loadOfferings(supabase!),
    });
    const metrics = useQuery({
        queryKey: ["overview"],
        enabled: Boolean(access.data && section === "overview"),
        queryFn: async () => {
            const today = new Date().toLocaleDateString("sv-SE", {
                timeZone: "Asia/Ho_Chi_Minh",
            });
            const nextLocalDay = new Date(`${today}T00:00:00Z`);
            nextLocalDay.setUTCDate(nextLocalDay.getUTCDate() + 1);
            const tomorrow = nextLocalDay.toISOString().slice(0, 10);
            const [
                appointments,
                customers,
                employees,
                invoicesRes,
                commissionsRes,
            ] = await Promise.all([
                supabase!
                    .from("appointments")
                    .select("id", { count: "exact", head: true })
                    .eq("appointment_date", today),
                supabase!
                    .from("customers")
                    .select("id", { count: "exact", head: true })
                    .is("deleted_at", null),
                supabase!
                    .from("employees")
                    .select("id, name, job_title")
                    .eq("status", "active")
                    .is("deleted_at", null)
                    .order("name"),
                supabase!
                    .from("invoices")
                    .select(
                        "id, type, customer_id, total_received, customer_package_id, notes, items:invoice_items(id, employee_id, line_total)",
                    )
                    .eq("status", "paid")
                    .gte("paid_at", `${today}T00:00:00+07:00`)
                    .lt("paid_at", `${tomorrow}T00:00:00+07:00`),
                supabase!
                    .from("commission_entries")
                    .select("id, employee_id, amount, status")
                    .gte("occurred_at", `${today}T00:00:00+07:00`)
                    .lt("occurred_at", `${tomorrow}T00:00:00+07:00`),
            ]);
            const error =
                appointments.error ??
                customers.error ??
                employees.error ??
                invoicesRes.error ??
                commissionsRes.error;
            if (error) throw error;

            const paidInvoices = (invoicesRes.data ?? []) as {
                id: number;
                type: string;
                customer_id: number;
                total_received: number;
                customer_package_id: number | null;
                notes: string | null;
                items: {
                    id: number;
                    employee_id: number | null;
                    line_total: number;
                }[];
            }[];
            const activeCommissions = (
                (commissionsRes.data ?? []) as {
                    id: number;
                    employee_id: number;
                    amount: number;
                    status: string;
                }[]
            ).filter((c) => c.status !== "reversed");
            const totalRevenue = paidInvoices.reduce(
                (sum, row) => sum + Number(row.total_received),
                0,
            );
            const totalCommission = activeCommissions.reduce(
                (sum, row) => sum + Number(row.amount),
                0,
            );

            const packageSessions = paidInvoices.filter(
                (inv) =>
                    inv.customer_package_id !== null ||
                    (inv.notes && inv.notes.includes("làm gói")),
            ).length;
            const retailSessions = paidInvoices.length - packageSessions;
            const uniqueCustomersToday = new Set(
                paidInvoices.map((inv) => inv.customer_id),
            ).size;

            const activeEmployees = (employees.data ?? []) as {
                id: number;
                name: string;
                job_title: string;
            }[];
            const staffPerformance = activeEmployees
                .map((emp) => {
                    const empInvoices = paidInvoices.filter((inv) =>
                        (inv.items ?? []).some(
                            (item) => item.employee_id === emp.id,
                        ),
                    );
                    const empPkg = empInvoices.filter(
                        (inv) =>
                            inv.customer_package_id !== null ||
                            (inv.notes && inv.notes.includes("làm gói")),
                    ).length;
                    const empRetail = empInvoices.length - empPkg;
                    const empRevenue = empInvoices.reduce(
                        (sum, inv) => sum + Number(inv.total_received),
                        0,
                    );
                    const empComm = activeCommissions
                        .filter((c) => c.employee_id === emp.id)
                        .reduce((sum, c) => sum + Number(c.amount), 0);
                    return {
                        id: emp.id,
                        name: emp.name,
                        job_title: emp.job_title,
                        totalSessions: empInvoices.length,
                        retailSessions: empRetail,
                        packageSessions: empPkg,
                        revenue: empRevenue,
                        commission: empComm,
                    };
                })
                .filter((emp) => emp.totalSessions > 0 || emp.commission > 0)
                .sort((a, b) => b.commission - a.commission);

            return {
                appointments: appointments.count ?? 0,
                customers: customers.count ?? 0,
                employees: activeEmployees.length,
                revenue: totalRevenue,
                commission: totalCommission,
                retailSessions,
                packageSessions,
                uniqueCustomersToday,
                staffPerformance,
            };
        },
    });
    const save = useMutation({
        mutationFn: () => saveOffering(supabase!, draft, editing || null),
        onSuccess: () => {
            toast.success(
                editing ? "Đã cập nhật dịch vụ." : "Đã thêm dịch vụ.",
            );
            setEditing(false);
            setDraft(emptyOffering);
            void queryClient.invalidateQueries({ queryKey: ["offerings"] });
        },
        onError: (error: Error) =>
            toast.error(error.message || "Không thể lưu dịch vụ."),
    });
    const visible = useMemo(
        () =>
            (offerings.data ?? []).filter((row) => {
                const term = search.trim().toLocaleLowerCase("vi");
                return (
                    (!term ||
                        `${row.name} ${row.code}`
                            .toLocaleLowerCase("vi")
                            .includes(term)) &&
                    (typeFilter === "all" || row.type === typeFilter) &&
                    (statusFilter === "all" || row.status === statusFilter)
                );
            }),
        [offerings.data, search, typeFilter, statusFilter],
    );

    const currentOfferingPage = Math.min(
        offeringPage,
        Math.max(0, Math.ceil(visible.length / 10) - 1),
    );
    const performance = metrics.data?.staffPerformance ?? [];
    const currentPerformancePage = Math.min(
        performancePage,
        Math.max(0, Math.ceil(performance.length / 5) - 1),
    );

    function edit(row?: Offering) {
        save.reset();
        setEditing(row ?? null);
        setDraft(
            row
                ? {
                      code: row.code,
                      name: row.name,
                      type: row.type,
                      duration_minutes: row.duration_minutes,
                      price: row.price,
                      sessions_total: row.sessions_total,
                      status: row.status,
                      featured: row.featured,
                      description: row.description ?? "",
                      commission: row.commission
                          ? {
                                enabled: row.commission.enabled,
                                ruleId: row.commission.ruleId,
                                calculationType: row.commission.calculationType,
                                value: row.commission.value,
                                startsOn: row.commission.startsOn,
                            }
                          : {
                                enabled: false,
                                ruleId: null,
                                calculationType: "fixed",
                                value: 0,
                            },
                  }
                : emptyOffering,
        );
    }
    async function signIn(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const { error } = await supabase!.auth.signInWithPassword({
            email: String(form.get("email")).trim(),
            password: String(form.get("password")),
        });
        if (error) toast.error("Email hoặc mật khẩu chưa chính xác.");
    }

    if (!hasDesktopConfiguration()) return <Setup />;
    if (!ready)
        return (
            <div className="center-state">
                <div className="brand-mark">
                    <Flower2 />
                </div>
                <span>Đang mở CRM Spa…</span>
            </div>
        );
    if (!session)
        return (
            <div className="auth-shell">
                <Brand />
                <section className="auth-card">
                    <div className="auth-flower">
                        <Flower2 />
                    </div>
                    <span className="eyebrow">CHÀO MỪNG TRỞ LẠI</span>
                    <h1>Đăng nhập</h1>
                    <p>Đăng nhập để tiếp tục quản lý CRM Spa.</p>
                    <form onSubmit={(event) => void signIn(event)}>
                        <label>
                            Email
                            <input
                                name="email"
                                type="email"
                                autoComplete="username"
                                required
                                placeholder="ten@email.com"
                            />
                        </label>
                        <label>
                            Mật khẩu
                            <input
                                name="password"
                                type="password"
                                autoComplete="current-password"
                                required
                                placeholder="Nhập mật khẩu"
                            />
                        </label>
                        <button className="primary-button full-button">
                            Đăng nhập vào hệ thống <ArrowUpRight size={16} />
                        </button>
                    </form>
                    <div className="auth-note">
                        <ShieldCheck size={16} /> Tài khoản được cấp riêng cho
                        chủ cơ sở.
                    </div>
                </section>
                <small className="auth-footer">
                    CRM Spa <i>·</i> SPA MANAGEMENT
                </small>
                <Toaster position="top-right" richColors />
            </div>
        );
    if (access.isPending)
        return (
            <div className="center-state">Đang kiểm tra quyền truy cập…</div>
        );
    if (access.isError)
        return (
            <div className="center-state">
                <p>Chưa kết nối được dữ liệu.</p>
                <button onClick={() => void access.refetch()}>Thử lại</button>
            </div>
        );
    if (!access.data)
        return (
            <div className="center-state">
                <h2>Tài khoản chưa được cấp quyền</h2>
                <p>Thêm tài khoản vào danh sách người quản lý Supabase.</p>
                <button onClick={() => void supabase!.auth.signOut()}>
                    Đăng xuất
                </button>
            </div>
        );

    const title =
        sections.find((item) => item.id === section)?.label ?? "Tổng quan";
    const businessName = businessSettings.data?.business_name || "CRM Spa";
    const businessAddress =
        businessSettings.data?.address || "Chưa cấu hình địa chỉ";
    const day = new Intl.DateTimeFormat("vi-VN", {
        weekday: "long",
        day: "numeric",
        month: "long",
        year: "numeric",
        timeZone: "Asia/Ho_Chi_Minh",
    }).format(currentDate);
    const overviewCards = [
        {
            label: "Doanh thu hôm nay",
            value: metrics.data ? `${formatVnd(metrics.data.revenue)} ₫` : "—",
            note: "Từ hóa đơn đã thanh toán",
            icon: WalletCards,
        },
        {
            label: "Hoa hồng KTV hôm nay",
            value: metrics.data
                ? `${formatVnd(metrics.data.commission)} ₫`
                : "—",
            note: "Từ các ca làm hoàn thành",
            icon: Banknote,
        },
        {
            label: "Số ca làm hôm nay",
            value: metrics.data
                ? `${metrics.data.retailSessions + metrics.data.packageSessions} ca`
                : "—",
            note: metrics.data
                ? `${metrics.data.retailSessions} ca lẻ · ${metrics.data.packageSessions} ca gói`
                : "Phục vụ trong ngày",
            icon: CalendarDays,
        },
        {
            label: "Khách phục vụ hôm nay",
            value: metrics.data
                ? `${metrics.data.uniqueCustomersToday} khách`
                : "—",
            note: `Tổng hồ sơ: ${metrics.data?.customers ?? "—"} khách`,
            icon: UsersRound,
        },
    ] satisfies {
        label: string;
        value: number | string;
        note: string;
        icon: LucideIcon;
    }[];
    return (
        <div className="app-frame">
            <aside className="sidebar">
                <div className="sidebar-header">
                    <Brand onSelect={() => setSection("overview")} />
                </div>
                <nav className="sidebar-nav" aria-label="Điều hướng chính">
                    {sections
                        .filter((item) => !item.hidden)
                        .map((item) => {
                            const Icon = item.icon;
                            const isActive = section === item.id;
                            return (
                                <button
                                    key={item.id}
                                    type="button"
                                    className={`nav-item ${isActive ? "active" : ""}`}
                                    aria-label={item.label}
                                    title={item.label}
                                    aria-current={isActive ? "page" : undefined}
                                    onClick={() => setSection(item.id)}
                                >
                                    <Icon size={18} aria-hidden="true" />
                                    <span>{item.label}</span>
                                </button>
                            );
                        })}
                </nav>
                <div className="sidebar-bottom">
                    <div className="sidebar-status-row">
                        <span className="sidebar-status">
                            <i className="online-dot" aria-hidden="true" />
                            Đang vận hành
                        </span>
                        <span className="sidebar-version">v0.1.0</span>
                    </div>
                    <small className="sidebar-address" title={businessAddress}>
                        {businessAddress}
                    </small>
                </div>
            </aside>
            <div className="main-column">
                <header className="topbar">
                    <div>
                        <span>{businessName}</span>
                        <i>/</i>
                        <b>{title}</b>
                    </div>
                    <div className="topbar-right">
                        <span className="connected">
                            <i className="online-dot" /> Đã kết nối
                        </span>
                        <button
                            className="icon-button"
                            aria-label="Kiểm tra bản cập nhật"
                            title="Kiểm tra bản cập nhật"
                            disabled={updateBusy}
                            onClick={() => void checkForUpdates()}
                        >
                            <RefreshCw size={17} />
                        </button>
                        <span className="top-divider" />
                        <button
                            className="profile-button"
                            onClick={() => void supabase!.auth.signOut()}
                            title="Đăng xuất"
                        >
                            <span className="profile-avatar">
                                {session.user.email?.slice(0, 1).toUpperCase()}
                            </span>
                            <span>
                                <b>Chủ spa</b>
                                <small>{session.user.email}</small>
                            </span>
                            <LogOut size={16} />
                        </button>
                    </div>
                </header>
                <main className={`page-content page-content--${section}`}>
                    {availableUpdate && !updateDismissed && (
                        <section className="update-banner" role="status">
                            <div>
                                <span className="update-symbol">
                                    <RefreshCw size={17} />
                                </span>
                                <span>
                                    <b>
                                        Đã có CRM Spa phiên bản{" "}
                                        {availableUpdate.version}
                                    </b>
                                    <small>
                                        {updateDownloaded
                                            ? "Đã tải xong. Cài đặt sẽ khởi động lại phần mềm."
                                            : "Tải bản cập nhật khi thuận tiện."}
                                    </small>
                                </span>
                            </div>
                            {updateDownloaded ? (
                                <button
                                    className="primary-button"
                                    disabled={
                                        updateBusy ||
                                        save.isPending ||
                                        editing !== false ||
                                        appointmentEditing ||
                                        customerEditing ||
                                        invoiceEditing ||
                                        teamEditing ||
                                        settingsEditing ||
                                        dailyRevenueEditing
                                    }
                                    onClick={() => void installUpdate()}
                                >
                                    {updateBusy
                                        ? "Đang cài…"
                                        : "Khởi động lại để cập nhật"}
                                </button>
                            ) : (
                                <button
                                    className="primary-button"
                                    disabled={updateBusy}
                                    onClick={() => void downloadUpdate()}
                                >
                                    {updateBusy
                                        ? "Đang tải…"
                                        : "Tải bản cập nhật"}
                                </button>
                            )}
                            <button
                                className="update-dismiss"
                                onClick={() => setUpdateDismissed(true)}
                            >
                                Để sau
                            </button>
                        </section>
                    )}
                    {![
                        "appointments",
                        "customers",
                        "invoices",
                        "team",
                        "settings",
                        "daily-revenue",
                    ].includes(section) && (
                        <div className="page-heading">
                            <div>
                                <span className="eyebrow">
                                    {day.toLocaleUpperCase("vi-VN")}
                                </span>
                                <h1>{title}</h1>
                                <p>
                                    {section === "overview"
                                        ? "Theo dõi hoạt động và tiến độ kinh doanh hôm nay."
                                        : section === "services"
                                          ? "Quản lý danh mục dịch vụ và các gói liệu trình."
                                          : "Khu vực này sẽ được chuyển ở giai đoạn tiếp theo."}
                                </p>
                            </div>
                            {section === "services" && (
                                <button
                                    className="primary-button"
                                    onClick={() => edit()}
                                >
                                    <Plus size={17} /> Thêm dịch vụ
                                </button>
                            )}
                        </div>
                    )}
                    {section === "overview" && (
                        <>
                            <div className="section-title">
                                <div>
                                    <h2>Hôm nay tại CRM Spa</h2>
                                    <p>
                                        Các chỉ số tổng hợp theo dữ liệu vận
                                        hành.
                                    </p>
                                </div>
                                <button
                                    className="text-button"
                                    onClick={() => void metrics.refetch()}
                                >
                                    Làm mới dữ liệu <ArrowUpRight size={15} />
                                </button>
                            </div>
                            {metrics.isError ? (
                                <div className="error-box">
                                    Chưa tải được dữ liệu. Kiểm tra kết nối rồi
                                    thử lại.
                                    <button
                                        onClick={() => void metrics.refetch()}
                                    >
                                        Thử lại
                                    </button>
                                </div>
                            ) : (
                                <div className="metric-grid">
                                    {overviewCards.map(
                                        (
                                            {
                                                label,
                                                value,
                                                note,
                                                icon: CardIcon,
                                            },
                                            index,
                                        ) => (
                                            <button
                                                className="metric-card"
                                                key={label}
                                                onClick={() => {
                                                    if (
                                                        index === 0 ||
                                                        index === 1 ||
                                                        index === 2
                                                    )
                                                        setSection(
                                                            "daily-revenue",
                                                        );
                                                    else if (index === 3)
                                                        setSection("customers");
                                                }}
                                            >
                                                <span
                                                    className={`metric-icon metric-${index}`}
                                                >
                                                    <CardIcon size={19} />
                                                </span>
                                                <span className="metric-label">
                                                    {label}
                                                </span>
                                                <b>
                                                    {metrics.isPending
                                                        ? "—"
                                                        : value}
                                                </b>
                                                <small>{note}</small>
                                            </button>
                                        ),
                                    )}
                                </div>
                            )}
                            <div className="section-title">
                                <div>
                                    <h2>
                                        Năng suất & Hoa hồng nhân viên hôm nay
                                    </h2>
                                    <p>
                                        Tổng hợp số ca thực hiện, doanh thu mang
                                        lại và hoa hồng tính theo từng KTV.
                                    </p>
                                </div>
                                <button
                                    className="text-button"
                                    onClick={() => setSection("daily-revenue")}
                                >
                                    Nhập thêm ca làm <ArrowUpRight size={15} />
                                </button>
                            </div>
                            <div className="data-panel">
                                {metrics.isPending ? (
                                    <div className="empty-state">
                                        Đang tổng hợp dữ liệu nhân viên…
                                    </div>
                                ) : !metrics.data?.staffPerformance.length ? (
                                    <div
                                        className="empty-state"
                                        style={{ minHeight: 140 }}
                                    >
                                        <Sparkles size={24} />
                                        <b>
                                            Chưa có ca làm nào được ghi nhận hôm
                                            nay
                                        </b>
                                        <span>
                                            Bấm "Nhập ca cuối ngày" để ghi nhận
                                            ca làm và tính hoa hồng tự động cho
                                            nhân viên.
                                        </span>
                                        <button
                                            className="primary-button"
                                            style={{ marginTop: 8 }}
                                            onClick={() =>
                                                setSection("daily-revenue")
                                            }
                                        >
                                            <Plus size={15} /> Nhập ca làm ngay
                                        </button>
                                    </div>
                                ) : (
                                    <div
                                        className="table-scroll"
                                        ref={performanceScroll}
                                        tabIndex={0}
                                        role="region"
                                        aria-label="Bảng năng suất nhân viên, có thể cuộn"
                                    >
                                        <table>
                                            <thead>
                                                <tr>
                                                    <th>KỸ THUẬT VIÊN</th>
                                                    <th>CHỨC DANH</th>
                                                    <th
                                                        style={{
                                                            textAlign: "center",
                                                        }}
                                                    >
                                                        TỔNG CA
                                                    </th>
                                                    <th
                                                        style={{
                                                            textAlign: "center",
                                                        }}
                                                    >
                                                        CA LẺ
                                                    </th>
                                                    <th
                                                        style={{
                                                            textAlign: "center",
                                                        }}
                                                    >
                                                        CA GÓI
                                                    </th>
                                                    <th
                                                        style={{
                                                            textAlign: "right",
                                                        }}
                                                    >
                                                        DOANH THU ĐÓNG GÓP
                                                    </th>
                                                    <th
                                                        style={{
                                                            textAlign: "right",
                                                        }}
                                                    >
                                                        HOA HỒNG NHẬN ĐƯỢC
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                {performance
                                                    .slice(
                                                        currentPerformancePage *
                                                            5,
                                                        (currentPerformancePage +
                                                            1) *
                                                            5,
                                                    )
                                                    .map((emp) => (
                                                        <tr key={emp.id}>
                                                            <td>
                                                                <div
                                                                    style={{
                                                                        display:
                                                                            "flex",
                                                                        alignItems:
                                                                            "center",
                                                                        gap: 8,
                                                                    }}
                                                                >
                                                                    <span
                                                                        className="profile-avatar"
                                                                        style={{
                                                                            width: 26,
                                                                            height: 26,
                                                                            fontSize: 11,
                                                                        }}
                                                                    >
                                                                        {emp.name
                                                                            .slice(
                                                                                0,
                                                                                1,
                                                                            )
                                                                            .toUpperCase()}
                                                                    </span>
                                                                    <b>
                                                                        {
                                                                            emp.name
                                                                        }
                                                                    </b>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                {emp.job_title}
                                                            </td>
                                                            <td
                                                                style={{
                                                                    textAlign:
                                                                        "center",
                                                                }}
                                                            >
                                                                <span
                                                                    className="daily-revenue-badge"
                                                                    style={{
                                                                        background:
                                                                            "#f0f3f2",
                                                                    }}
                                                                >
                                                                    {
                                                                        emp.totalSessions
                                                                    }
                                                                </span>
                                                            </td>
                                                            <td
                                                                style={{
                                                                    textAlign:
                                                                        "center",
                                                                }}
                                                            >
                                                                {
                                                                    emp.retailSessions
                                                                }
                                                            </td>
                                                            <td
                                                                style={{
                                                                    textAlign:
                                                                        "center",
                                                                }}
                                                            >
                                                                {
                                                                    emp.packageSessions
                                                                }
                                                            </td>
                                                            <td
                                                                style={{
                                                                    textAlign:
                                                                        "right",
                                                                    fontWeight: 600,
                                                                }}
                                                            >
                                                                {formatVnd(
                                                                    emp.revenue,
                                                                )}{" "}
                                                                ₫
                                                            </td>
                                                            <td
                                                                style={{
                                                                    textAlign:
                                                                        "right",
                                                                    color: "#b45309",
                                                                    fontWeight: 700,
                                                                }}
                                                            >
                                                                {formatVnd(
                                                                    emp.commission,
                                                                )}{" "}
                                                                ₫
                                                            </td>
                                                        </tr>
                                                    ))}
                                            </tbody>
                                        </table>
                                    </div>
                                )}
                                {performance.length > 0 && (
                                    <TablePagination
                                        label="nhân viên"
                                        total={performance.length}
                                        page={currentPerformancePage}
                                        pageSize={5}
                                        onPage={(page) => {
                                            setPerformancePage(page);
                                            performanceScroll.current?.scrollTo(
                                                { top: 0 },
                                            );
                                        }}
                                    />
                                )}
                            </div>
                            <details className="overview-shortcuts">
                                <summary>
                                    Truy cập nhanh & không gian làm việc
                                </summary>
                                <div className="quick-links">
                                    <button
                                        onClick={() =>
                                            setSection("daily-revenue")
                                        }
                                    >
                                        <Banknote />
                                        <span>
                                            <b>Nhập ca làm cuối ngày</b>
                                            <small>
                                                Ghi nhận nhanh ca làm & tính hoa
                                                hồng KTV
                                            </small>
                                        </span>
                                        <ArrowUpRight />
                                    </button>
                                    <button
                                        onClick={() =>
                                            setSection("appointments")
                                        }
                                    >
                                        <CalendarDays />
                                        <span>
                                            <b>Lịch hẹn trong ngày</b>
                                            <small>
                                                Theo dõi lịch và trạng thái phục
                                                vụ
                                            </small>
                                        </span>
                                        <ArrowUpRight />
                                    </button>
                                    <button
                                        onClick={() => setSection("services")}
                                    >
                                        <Flower2 />
                                        <span>
                                            <b>Danh mục dịch vụ</b>
                                            <small>
                                                Cập nhật dịch vụ, giá & hoa hồng
                                                KTV
                                            </small>
                                        </span>
                                        <ArrowUpRight />
                                    </button>
                                </div>
                                <section className="welcome-banner">
                                    <div>
                                        <span>
                                            <Sparkles size={15} /> KHÔNG GIAN
                                            LÀM VIỆC CỦA BẠN
                                        </span>
                                        <h2>Một ngày thật nhiều năng lượng!</h2>
                                        <p>
                                            Chúc bạn điều hành CRM Spa thật
                                            nhẹ nhàng và trọn vẹn.
                                        </p>
                                        <button
                                            onClick={() =>
                                                setSection("daily-revenue")
                                            }
                                        >
                                            Nhập ca làm hôm nay{" "}
                                            <ArrowUpRight size={15} />
                                        </button>
                                    </div>
                                    <div className="banner-art">
                                        <span />
                                        <Flower2 size={76} />
                                    </div>
                                </section>
                            </details>
                        </>
                    )}
                    {section === "services" && (
                        <section className="data-panel services-panel">
                            <div className="panel-heading">
                                <div>
                                    <span className="eyebrow">
                                        DANH MỤC ĐANG QUẢN LÝ
                                    </span>
                                    <h2>Dịch vụ & gói liệu trình</h2>
                                    <p>
                                        Giá niêm yết và thời lượng phục vụ tại
                                        cơ sở.
                                    </p>
                                </div>
                                <strong>
                                    {offerings.data?.length ?? "—"}{" "}
                                    <small>mục hiển thị</small>
                                </strong>
                            </div>
                            <div className="table-tools">
                                <label className="search-box">
                                    <Search size={17} />
                                    <input
                                        aria-label="Tìm dịch vụ"
                                        placeholder="Tìm theo tên hoặc mã dịch vụ…"
                                        value={search}
                                        onChange={(event) => {
                                            setSearch(event.target.value);
                                            setOfferingPage(0);
                                        }}
                                    />
                                </label>
                                <select
                                    aria-label="Lọc theo loại"
                                    value={typeFilter}
                                    onChange={(event) => {
                                        setTypeFilter(event.target.value);
                                        setOfferingPage(0);
                                    }}
                                >
                                    <option value="all">Tất cả loại</option>
                                    <option value="service">Dịch vụ</option>
                                    <option value="package">
                                        Gói liệu trình
                                    </option>
                                </select>
                                <select
                                    aria-label="Lọc trạng thái"
                                    value={statusFilter}
                                    onChange={(event) => {
                                        setStatusFilter(event.target.value);
                                        setOfferingPage(0);
                                    }}
                                >
                                    <option value="all">Mọi trạng thái</option>
                                    <option value="active">Đang áp dụng</option>
                                    <option value="inactive">Tạm ngưng</option>
                                </select>
                                <button
                                    className="text-button"
                                    onClick={() => {
                                        setSearch("");
                                        setTypeFilter("all");
                                        setStatusFilter("all");
                                        setOfferingPage(0);
                                    }}
                                >
                                    Xóa lọc
                                </button>
                            </div>
                            {offerings.isPending ? (
                                <div className="empty-state">
                                    Đang tải danh mục dịch vụ…
                                </div>
                            ) : offerings.isError ? (
                                <div className="empty-state">
                                    Chưa thể tải danh mục.{" "}
                                    <button
                                        onClick={() => void offerings.refetch()}
                                    >
                                        Thử lại
                                    </button>
                                </div>
                            ) : !visible.length ? (
                                <div className="empty-state">
                                    <Flower2 size={28} />
                                    <b>Chưa có dịch vụ phù hợp</b>
                                    <span>
                                        Thêm dịch vụ đầu tiên hoặc đổi bộ lọc
                                        đang chọn.
                                    </span>
                                    {!search &&
                                        typeFilter === "all" &&
                                        statusFilter === "all" && (
                                            <button
                                                className="primary-button"
                                                onClick={() => edit()}
                                            >
                                                <Plus size={16} /> Thêm dịch vụ
                                            </button>
                                        )}
                                </div>
                            ) : (
                                <div
                                    className="table-scroll"
                                    ref={offeringScroll}
                                    tabIndex={0}
                                    role="region"
                                    aria-label="Bảng dịch vụ, có thể cuộn"
                                >
                                    <table>
                                        <thead>
                                            <tr>
                                                <th>TÊN DỊCH VỤ</th>
                                                <th>MÃ</th>
                                                <th>LOẠI</th>
                                                <th>THỜI LƯỢNG</th>
                                                <th>GIÁ NIÊM YẾT</th>
                                                <th>HOA HỒNG KTV</th>
                                                <th>TRẠNG THÁI</th>
                                                <th />
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {visible
                                                .slice(
                                                    currentOfferingPage * 10,
                                                    (currentOfferingPage + 1) *
                                                        10,
                                                )
                                                .map((row) => (
                                                    <tr key={row.id}>
                                                        <td>
                                                            <div className="service-name">
                                                                <span className="service-mark">
                                                                    <Flower2
                                                                        size={
                                                                            17
                                                                        }
                                                                    />
                                                                </span>
                                                                <span>
                                                                    <b>
                                                                        {
                                                                            row.name
                                                                        }
                                                                    </b>
                                                                    <small>
                                                                        {row.description ||
                                                                            "Chưa có mô tả"}
                                                                    </small>
                                                                </span>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <code>
                                                                {row.code}
                                                            </code>
                                                        </td>
                                                        <td>
                                                            {row.type ===
                                                            "package"
                                                                ? "Gói liệu trình"
                                                                : "Dịch vụ"}
                                                        </td>
                                                        <td>
                                                            {row.type ===
                                                            "package"
                                                                ? `${row.sessions_total} buổi`
                                                                : `${row.duration_minutes} phút`}
                                                        </td>
                                                        <td className="price">
                                                            {row.price === null
                                                                ? "Chưa thiết lập"
                                                                : `${formatVnd(row.price)} ₫`}
                                                        </td>
                                                        <td className="commission-cell">
                                                            {row.commission
                                                                ?.enabled &&
                                                            row.commission
                                                                .value > 0 ? (
                                                                <span className="commission-pill">
                                                                    {row
                                                                        .commission
                                                                        .calculationType ===
                                                                    "fixed"
                                                                        ? `${formatVnd(row.commission.value)} ₫/buổi`
                                                                        : `${row.commission.value}% DT`}
                                                                </span>
                                                            ) : (
                                                                <span
                                                                    style={{
                                                                        color: "var(--muted)",
                                                                        fontSize: 11,
                                                                    }}
                                                                >
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td>
                                                            <span
                                                                className={`status ${row.status}`}
                                                            >
                                                                <i />
                                                                {row.status ===
                                                                "active"
                                                                    ? "Đang áp dụng"
                                                                    : "Tạm ngưng"}
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <button
                                                                className="icon-button row-edit"
                                                                aria-label={`Chỉnh sửa ${row.name}`}
                                                                onClick={() =>
                                                                    edit(row)
                                                                }
                                                            >
                                                                <Ellipsis
                                                                    size={19}
                                                                />
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}
                            <TablePagination
                                label="dịch vụ"
                                total={visible.length}
                                page={currentOfferingPage}
                                pageSize={10}
                                onPage={(page) => {
                                    setOfferingPage(page);
                                    offeringScroll.current?.scrollTo({
                                        top: 0,
                                    });
                                }}
                            />
                        </section>
                    )}
                    {section === "daily-revenue" && (
                        <Suspense
                            fallback={
                                <div className="center-state">
                                    Đang tải nhập ca cuối ngày…
                                </div>
                            }
                        >
                            <DailyRevenueWorkbench
                                client={supabase!}
                                onOpenInvoices={() => setSection("invoices")}
                                onEditorStateChange={setDailyRevenueEditing}
                            />
                        </Suspense>
                    )}
                    {section === "appointments" && (
                        <Suspense
                            fallback={
                                <div className="center-state">
                                    Đang tải lịch hẹn…
                                </div>
                            }
                        >
                            <AppointmentsWorkbench
                                client={supabase!}
                                onOpenInvoices={() => setSection("invoices")}
                                onEditorStateChange={setAppointmentEditing}
                            />
                        </Suspense>
                    )}
                    {section === "customers" && (
                        <Suspense
                            fallback={
                                <div className="center-state">
                                    Đang tải khách hàng…
                                </div>
                            }
                        >
                            <CustomersWorkbench
                                client={supabase!}
                                onEditorStateChange={setCustomerEditing}
                            />
                        </Suspense>
                    )}
                    {section === "invoices" && (
                        <Suspense
                            fallback={
                                <div className="center-state">
                                    Đang tải hóa đơn…
                                </div>
                            }
                        >
                            <InvoicesWorkbench
                                client={supabase!}
                                onEditorStateChange={setInvoiceEditing}
                            />
                        </Suspense>
                    )}
                    {section === "team" && (
                        <Suspense
                            fallback={
                                <div className="center-state">
                                    Đang tải nhân sự…
                                </div>
                            }
                        >
                            <TeamWorkbench
                                client={supabase!}
                                onEditorStateChange={setTeamEditing}
                            />
                        </Suspense>
                    )}
                    {section === "settings" && (
                        <Suspense
                            fallback={
                                <div className="center-state">
                                    Đang tải cấu hình cơ sở…
                                </div>
                            }
                        >
                            <BusinessSettingsPage
                                client={supabase!}
                                onEditorStateChange={setSettingsEditing}
                            />
                        </Suspense>
                    )}
                    {section === "payroll" && (
                        <Suspense
                            fallback={
                                <div className="center-state">
                                    Đang tải bảng lương…
                                </div>
                            }
                        >
                            <PayrollWorkbench
                                client={supabase!}
                                onEditorStateChange={setPayrollEditing}
                            />
                        </Suspense>
                    )}
                    {![
                        "overview",
                        "services",
                        "appointments",
                        "customers",
                        "invoices",
                        "team",
                        "settings",
                        "daily-revenue",
                        "payroll",
                    ].includes(section) && (
                        <section className="coming-card">
                            <div>
                                <Sparkles size={22} />
                            </div>
                            <span className="eyebrow">
                                ĐANG CHUYỂN GIAO DIỆN
                            </span>
                            <h2>{title} sẽ sớm có mặt</h2>
                            <p>
                                Module này sẽ được chuyển cùng các quy tắc vận
                                hành đang dùng tại CRM Spa.
                            </p>
                        </section>
                    )}
                </main>
                <footer className="app-footer">
                    <span>
                        {businessName} <i>·</i> Quản lý vận hành spa
                    </span>
                    <span>© 2026 CRM Spa</span>
                </footer>
            </div>
            {editing !== false && (
                <OfferingModal
                    value={draft}
                    editing={Boolean(editing)}
                    busy={save.isPending}
                    saveError={save.error?.message}
                    onChange={setDraft}
                    onClose={() => setEditing(false)}
                    onSave={() => save.mutate()}
                />
            )}
            <Toaster position="top-right" richColors closeButton />
        </div>
    );
}

function Brand({ onSelect }: { onSelect?: () => void }) {
    return (
        <a
            className="brand"
            href="#tong-quan"
            onClick={(event) => {
                if (!onSelect) return;
                event.preventDefault();
                onSelect();
            }}
        >
            <span className="brand-mark" aria-hidden="true">
                <Flower2 size={20} />
            </span>
            <span className="brand-copy">
                <b>
                    Sunny <span>Beauty</span>
                </b>
                <small>Bàn điều hành spa</small>
            </span>
        </a>
    );
}
function Setup() {
    return (
        <div className="auth-shell">
            <Brand />
            <section className="auth-card">
                <div className="auth-flower">
                    <ShieldCheck />
                </div>
                <span className="eyebrow">CẦN CẤU HÌNH LẦN ĐẦU</span>
                <h1>Kết nối Supabase</h1>
                <p>
                    Sao chép <code>desktop/.env.example</code> thành{" "}
                    <code>desktop/.env.local</code>, sau đó thêm Project URL và
                    publishable key.
                </p>
                <div className="auth-note">
                    <ShieldCheck size={16} /> Không đưa secret key hoặc
                    service_role vào ứng dụng.
                </div>
            </section>
            <small className="auth-footer">
                CRM Spa <i>·</i> SPA MANAGEMENT
            </small>
        </div>
    );
}

function OfferingModal({
    value,
    editing,
    busy,
    saveError,
    onChange,
    onClose,
    onSave,
}: {
    value: OfferingDraft;
    editing: boolean;
    busy: boolean;
    saveError?: string;
    onChange: (value: OfferingDraft) => void;
    onClose: () => void;
    onSave: () => void;
}) {
    const [error, setError] = useState("");
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!value.name.trim()) {
            setError("Vui lòng nhập tên dịch vụ.");
            return;
        }
        if (value.price !== null && value.price < 0) {
            setError("Giá dịch vụ không được âm.");
            return;
        }
        if (value.type === "package" && value.sessions_total < 1) {
            setError("Gói liệu trình phải có ít nhất một buổi.");
            return;
        }
        if (value.commission.enabled && value.commission.value < 0) {
            setError("Mức hoa hồng không được âm.");
            return;
        }
        if (
            value.commission.enabled &&
            value.commission.calculationType === "percentage" &&
            value.commission.value > 100
        ) {
            setError("Tỷ lệ hoa hồng % không được vượt quá 100%.");
            return;
        }
        setError("");
        onSave();
    }
    return (
        <Popup
            title={editing ? "Chỉnh sửa dịch vụ" : "Thêm dịch vụ mới"}
            busy={busy}
            onClose={onClose}
        >
            <form className="offering-popup-form" onSubmit={submit}>
                <div className="modal-body">
                    <label>
                        <span>
                            Tên dịch vụ <b>*</b>
                        </span>
                        <input
                            autoFocus
                            required
                            maxLength={160}
                            value={value.name}
                            placeholder="Nhập tên dịch vụ"
                            onChange={(event) =>
                                onChange({ ...value, name: event.target.value })
                            }
                        />
                    </label>
                    <div className="form-grid">
                        <label>
                            Loại hình
                            <select
                                value={value.type}
                                onChange={(event) =>
                                    onChange({
                                        ...value,
                                        type: event.target
                                            .value as OfferingDraft["type"],
                                    })
                                }
                            >
                                <option value="service">Dịch vụ</option>
                                <option value="package">Gói liệu trình</option>
                            </select>
                        </label>
                        <label>
                            {value.type === "package"
                                ? "Số buổi"
                                : "Thời lượng (phút)"}
                            <input
                                type="number"
                                min={1}
                                value={
                                    value.type === "package"
                                        ? value.sessions_total
                                        : value.duration_minutes
                                }
                                onChange={(event) =>
                                    onChange(
                                        value.type === "package"
                                            ? {
                                                  ...value,
                                                  sessions_total: Number(
                                                      event.target.value,
                                                  ),
                                              }
                                            : {
                                                  ...value,
                                                  duration_minutes: Number(
                                                      event.target.value,
                                                  ),
                                              },
                                    )
                                }
                            />
                        </label>
                    </div>
                    <label>
                        Giá niêm yết (VND)
                        <input
                            type="number"
                            min={0}
                            step={1000}
                            value={value.price ?? ""}
                            placeholder="0"
                            onChange={(event) =>
                                onChange({
                                    ...value,
                                    price: event.target.value
                                        ? Number(event.target.value)
                                        : null,
                                })
                            }
                        />
                    </label>

                    <div className="modal-commission-box">
                        <label className="active-check">
                            <input
                                type="checkbox"
                                checked={value.commission.enabled}
                                onChange={(e) =>
                                    onChange({
                                        ...value,
                                        commission: {
                                            ...value.commission,
                                            enabled: e.target.checked,
                                        },
                                    })
                                }
                            />
                            <b>Cấu hình hoa hồng KTV thực hiện</b>
                        </label>
                        {value.commission.enabled && (
                            <div className="form-grid">
                                <label>
                                    Hình thức tính
                                    <select
                                        value={value.commission.calculationType}
                                        onChange={(e) =>
                                            onChange({
                                                ...value,
                                                commission: {
                                                    ...value.commission,
                                                    calculationType: e.target
                                                        .value as
                                                        | "fixed"
                                                        | "percentage",
                                                },
                                            })
                                        }
                                    >
                                        <option value="fixed">
                                            Cố định (₫ / buổi)
                                        </option>
                                        <option value="percentage">
                                            Theo % doanh thu
                                        </option>
                                    </select>
                                </label>
                                <label>
                                    {value.commission.calculationType ===
                                    "fixed"
                                        ? "Mức hoa hồng (₫)"
                                        : "Tỷ lệ hoa hồng (%)"}
                                    <input
                                        type="number"
                                        min={0}
                                        step={
                                            value.commission.calculationType ===
                                            "fixed"
                                                ? 1000
                                                : 1
                                        }
                                        max={
                                            value.commission.calculationType ===
                                            "percentage"
                                                ? 100
                                                : undefined
                                        }
                                        value={value.commission.value || ""}
                                        placeholder={
                                            value.commission.calculationType ===
                                            "fixed"
                                                ? "VD: 50000"
                                                : "VD: 10"
                                        }
                                        onChange={(e) =>
                                            onChange({
                                                ...value,
                                                commission: {
                                                    ...value.commission,
                                                    value:
                                                        Number(
                                                            e.target.value,
                                                        ) || 0,
                                                },
                                            })
                                        }
                                    />
                                </label>
                            </div>
                        )}
                    </div>

                    <label className="active-check">
                        <input
                            type="checkbox"
                            checked={value.status === "active"}
                            onChange={(event) =>
                                onChange({
                                    ...value,
                                    status: event.target.checked
                                        ? "active"
                                        : "inactive",
                                })
                            }
                        />{" "}
                        Đang áp dụng
                    </label>
                    {(error || saveError) && (
                        <p className="form-error" role="alert">
                            {error || saveError}
                        </p>
                    )}
                </div>
                <footer>
                    <button
                        type="button"
                        className="secondary-button"
                        disabled={busy}
                        onClick={onClose}
                    >
                        Hủy
                    </button>
                    <button className="primary-button" disabled={busy}>
                        {busy
                            ? "Đang lưu…"
                            : editing
                              ? "Lưu thay đổi"
                              : "Thêm vào danh mục"}
                    </button>
                </footer>
            </form>
        </Popup>
    );
}

function TablePagination({
    label,
    total,
    page,
    pageSize,
    onPage,
}: {
    label: string;
    total: number;
    page: number;
    pageSize: number;
    onPage: (page: number) => void;
}) {
    const pages = Math.max(1, Math.ceil(total / pageSize));
    return (
        <nav className="table-pagination" aria-label={`Phân trang ${label}`}>
            <span>
                {total
                    ? `${page * pageSize + 1}–${Math.min((page + 1) * pageSize, total)} / ${total}`
                    : "0"}{" "}
                {label}
            </span>
            <div>
                <button
                    type="button"
                    className="icon-button"
                    aria-label={`Trang trước ${label}`}
                    disabled={page === 0}
                    onClick={() => onPage(page - 1)}
                >
                    <ChevronLeft size={16} />
                </button>
                <span>
                    Trang {page + 1}/{pages}
                </span>
                <button
                    type="button"
                    className="icon-button"
                    aria-label={`Trang sau ${label}`}
                    disabled={page + 1 >= pages}
                    onClick={() => onPage(page + 1)}
                >
                    <ChevronRight size={16} />
                </button>
            </div>
        </nav>
    );
}
