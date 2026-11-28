import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    ArrowUpRight,
    Bell,
    CheckCircle2,
    CircleAlert,
    MessageCircle,
    Scissors,
    ShieldCheck,
    Star,
    Users,
    UserRound,
} from 'lucide-react';
import type { ReactNode } from 'react';

type RecentCustomer = {
    id: number;
    first_name: string;
    last_name: string | null;
    phone: string;
    created_at: string;
};

type DashboardProps = {
    canViewOperations: boolean;
    canViewAudit: boolean;
    business: { name: string };
    analytics: {
        customer_count: number;
        consented_customer_count: number;
        opted_out_customer_count: number;
        visit_count: number;
        returning_customer_count: number;
        pending_follow_up_count: number;
        sent_message_count: number;
        delivered_message_count: number;
        failed_message_count: number;
        review_count: number;
        average_rating: number | null;
        whatsapp_connected: boolean;
        recent_customers: RecentCustomer[];
    };
};

export default function DashboardPage({
    business,
    analytics,
    canViewOperations,
    canViewAudit,
}: DashboardProps) {
    const retentionRate =
        analytics.customer_count > 0
            ? Math.round(
                  (analytics.returning_customer_count /
                      analytics.customer_count) *
                      100,
              )
            : 0;
    const deliveryRate =
        analytics.sent_message_count > 0
            ? Math.round(
                  (analytics.delivered_message_count /
                      analytics.sent_message_count) *
                      100,
              )
            : 0;
    const consentRate =
        analytics.customer_count > 0
            ? Math.round(
                  (analytics.consented_customer_count /
                      analytics.customer_count) *
                      100,
              )
            : 0;

    const stats = [
        {
            label: 'Customers',
            value: analytics.customer_count,
            detail: `${analytics.consented_customer_count.toLocaleString()} with WhatsApp consent`,
            icon: Users,
            tone: 'blue',
        },
        {
            label: 'Visits recorded',
            value: analytics.visit_count,
            detail: `${analytics.returning_customer_count.toLocaleString()} returning customers`,
            icon: Scissors,
            tone: 'red',
        },
        {
            label: 'Follow-ups queued',
            value: analytics.pending_follow_up_count,
            detail: 'Waiting for their scheduled time',
            icon: MessageCircle,
            tone: 'violet',
        },
        {
            label: 'Reviews received',
            value: analytics.review_count,
            detail:
                analytics.average_rating === null
                    ? 'No explicit ratings yet'
                    : `${analytics.average_rating} average explicit rating`,
            icon: Star,
            tone: 'gold',
        },
    ] as const;

    return (
        <main className="min-h-screen px-4 py-5 sm:px-7 sm:py-8 xl:px-10">
            <Head title="Dashboard" />
            <div className="mx-auto max-w-[1440px]">
                <header className="mb-6 flex flex-wrap items-end justify-between gap-4 sm:mb-8">
                    <div>
                        <p className="text-sm font-medium text-muted-foreground">
                            Your barbershop · Daily overview
                        </p>
                        <h1 className="mt-1 text-2xl font-semibold tracking-tight sm:text-3xl">
                            Good to see you at {business.name}
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            A clear view of your customers, conversations and
                            follow-ups.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <span
                            className={`inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-medium ${analytics.whatsapp_connected ? 'border-emerald-200 bg-emerald-50 text-emerald-800 dark:border-emerald-900 dark:bg-emerald-950/30 dark:text-emerald-200' : 'border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-200'}`}
                        >
                            <span
                                className={`size-2 rounded-full ${analytics.whatsapp_connected ? 'bg-emerald-500' : 'bg-amber-500'}`}
                            />
                            WhatsApp{' '}
                            {analytics.whatsapp_connected
                                ? 'connected'
                                : 'setup needed'}
                        </span>
                        {canViewOperations && (
                            <Link
                                className="inline-flex min-h-10 items-center gap-2 rounded-xl border bg-card px-3 text-sm font-medium transition hover:bg-muted"
                                href="/operations/health"
                            >
                                <Activity size={16} /> System health
                            </Link>
                        )}
                        {canViewAudit && (
                            <Link
                                aria-label="Audit log"
                                className="grid size-10 place-items-center rounded-xl border bg-card transition hover:bg-muted"
                                href="/operations/audit"
                            >
                                <ShieldCheck size={17} />
                            </Link>
                        )}
                    </div>
                </header>

                <section className="relative isolate overflow-hidden rounded-[1.8rem] bg-[#172235] text-white shadow-xl shadow-slate-900/10">
                    <div
                        aria-hidden="true"
                        className="absolute inset-y-0 right-0 -z-10 hidden w-[58%] lg:block"
                    >
                        <img
                            alt=""
                            aria-hidden="true"
                            className="h-full w-full object-cover object-center opacity-55 mix-blend-screen"
                            src="/brand/barbershop-editorial.png"
                        />
                        <div className="absolute inset-0 bg-gradient-to-r from-[#172235] via-[#172235]/75 to-[#172235]/20" />
                    </div>
                    <div
                        aria-hidden="true"
                        className="absolute -right-20 -top-40 -z-10 size-96 rounded-full border border-white/10"
                    />
                    <div className="relative grid min-h-[270px] items-center gap-8 px-6 py-8 sm:px-9 sm:py-10 lg:grid-cols-[minmax(0,1fr)_auto] lg:px-12">
                        <div className="max-w-2xl">
                            <p className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-[#f08378]">
                                <span className="size-1.5 rounded-full bg-[#f08378]" />{' '}
                                Your shop, in good order
                            </p>
                            <h2 className="mt-4 max-w-xl text-3xl font-semibold leading-tight tracking-[-0.035em] sm:text-4xl xl:text-[2.7rem]">
                                Make every visit the beginning of a
                                relationship.
                            </h2>
                            <p className="mt-3 max-w-lg text-sm leading-6 text-white/70 sm:text-base">
                                Keep customer history close, welcome people
                                back, and follow up with care on WhatsApp.
                            </p>
                            <div className="mt-6 flex flex-wrap gap-3">
                                <Link
                                    className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#f08378] px-4 text-sm font-semibold text-[#172235] transition hover:bg-[#ff9b90] focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                    href="/customers"
                                >
                                    View customers <ArrowUpRight size={16} />
                                </Link>
                                <Link
                                    className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 bg-white/5 px-4 text-sm font-medium transition hover:bg-white/10 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                                    href="/inbox"
                                >
                                    Open inbox <MessageCircle size={16} />
                                </Link>
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-3 sm:min-w-[265px]">
                            <div className="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                                <p className="text-xs text-white/65">
                                    Customer return rate
                                </p>
                                <p className="mt-2 text-3xl font-semibold tracking-tight">
                                    {retentionRate}
                                    <span className="ml-0.5 text-lg">%</span>
                                </p>
                                <p className="mt-1 text-[11px] text-white/55">
                                    Based on recorded visits
                                </p>
                            </div>
                            <div className="rounded-2xl border border-white/15 bg-white/10 p-4 backdrop-blur-sm">
                                <p className="text-xs text-white/65">
                                    Message delivery
                                </p>
                                <p className="mt-2 text-3xl font-semibold tracking-tight">
                                    {deliveryRate}
                                    <span className="ml-0.5 text-lg">%</span>
                                </p>
                                <p className="mt-1 text-[11px] text-white/55">
                                    Of sent messages delivered
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="relative flex flex-wrap gap-x-6 gap-y-2 border-t border-white/10 bg-white/[0.035] px-6 py-4 text-xs text-white/65 sm:px-9 lg:px-12">
                        <span>
                            <strong className="text-white">
                                {analytics.customer_count.toLocaleString()}
                            </strong>{' '}
                            customers
                        </span>
                        <span>
                            <strong className="text-white">
                                {analytics.pending_follow_up_count.toLocaleString()}
                            </strong>{' '}
                            follow-ups pending
                        </span>
                        <span className="inline-flex items-center gap-2">
                            <span
                                className={`size-1.5 rounded-full ${analytics.whatsapp_connected ? 'bg-emerald-400' : 'bg-amber-300'}`}
                            />
                            {analytics.whatsapp_connected
                                ? 'Messaging is connected'
                                : 'Complete WhatsApp setup'}
                        </span>
                    </div>
                </section>

                <section
                    aria-label="Key business metrics"
                    className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {stats.map(({ label, value, detail, icon: Icon, tone }) => (
                        <article
                            className="group rounded-2xl border bg-card p-4 shadow-sm shadow-slate-900/[0.025] transition hover:-translate-y-0.5 hover:shadow-md sm:p-5"
                            key={label}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <p className="text-sm text-muted-foreground">
                                        {label}
                                    </p>
                                    <p className="mt-3 text-3xl font-semibold tracking-tight">
                                        {value.toLocaleString()}
                                    </p>
                                </div>
                                <span
                                    className={`grid size-10 place-items-center rounded-xl ${tone === 'blue' ? 'bg-blue-50 text-blue-700 dark:bg-blue-950/50 dark:text-blue-200' : tone === 'red' ? 'bg-rose-50 text-rose-700 dark:bg-rose-950/50 dark:text-rose-200' : tone === 'violet' ? 'bg-violet-50 text-violet-700 dark:bg-violet-950/50 dark:text-violet-200' : 'bg-amber-50 text-amber-800 dark:bg-amber-950/50 dark:text-amber-200'}`}
                                >
                                    <Icon size={18} />
                                </span>
                            </div>
                            <p className="mt-4 border-t pt-3 text-xs text-muted-foreground">
                                {detail}
                            </p>
                        </article>
                    ))}
                </section>

                <section className="mt-5 grid gap-5 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.65fr)]">
                    <div className="overflow-hidden rounded-2xl border bg-card shadow-sm shadow-slate-900/[0.025]">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b px-5 py-4 sm:px-6">
                            <div>
                                <h2 className="font-semibold tracking-tight">
                                    New faces in the shop
                                </h2>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Most recently added customer records
                                </p>
                            </div>
                            <Link
                                className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                href="/customers"
                            >
                                All customers <ArrowRight size={15} />
                            </Link>
                        </div>
                        {analytics.recent_customers.length === 0 ? (
                            <div className="grid min-h-56 place-items-center px-6 py-8 text-center">
                                <div>
                                    <span className="mx-auto grid size-12 place-items-center rounded-2xl bg-muted text-muted-foreground">
                                        <UserRound size={21} />
                                    </span>
                                    <p className="mt-3 text-sm font-medium">
                                        Your customer list starts here
                                    </p>
                                    <p className="mx-auto mt-1 max-w-sm text-sm text-muted-foreground">
                                        Add a customer after their visit to keep
                                        their details and follow-ups in one
                                        place.
                                    </p>
                                    <Link
                                        className="mt-4 inline-flex items-center gap-2 rounded-lg bg-primary px-3 py-2 text-sm font-medium text-primary-foreground"
                                        href="/customers"
                                    >
                                        Go to customers <ArrowRight size={15} />
                                    </Link>
                                </div>
                            </div>
                        ) : (
                            <ul className="divide-y px-5 sm:px-6">
                                {analytics.recent_customers.map((customer) => (
                                    <li
                                        className="flex items-center gap-3 py-3.5"
                                        key={customer.id}
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center rounded-full bg-accent text-sm font-semibold text-accent-foreground">
                                            {customer.first_name
                                                .slice(0, 1)
                                                .toUpperCase()}
                                            {customer.last_name
                                                ?.slice(0, 1)
                                                .toUpperCase() ?? ''}
                                        </span>
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {customer.first_name}{' '}
                                                {customer.last_name ?? ''}
                                            </p>
                                            <p className="mt-0.5 truncate text-xs text-muted-foreground">
                                                {customer.phone}
                                            </p>
                                        </div>
                                        <time
                                            className="shrink-0 text-xs text-muted-foreground"
                                            dateTime={customer.created_at}
                                        >
                                            {new Date(
                                                customer.created_at,
                                            ).toLocaleDateString()}
                                        </time>
                                        <Link
                                            aria-label={`View ${customer.first_name}`}
                                            className="grid size-9 place-items-center rounded-lg text-muted-foreground hover:bg-muted hover:text-foreground"
                                            href={`/customers/${customer.id}`}
                                        >
                                            <ArrowUpRight size={16} />
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <aside className="rounded-2xl border bg-card p-5 shadow-sm shadow-slate-900/[0.025] sm:p-6">
                        <div className="flex items-start justify-between gap-3">
                            <div>
                                <p className="text-xs font-medium uppercase tracking-wider text-muted-foreground">
                                    Customer care
                                </p>
                                <h2 className="mt-1 text-lg font-semibold tracking-tight">
                                    Today at a glance
                                </h2>
                            </div>
                            <span className="grid size-10 place-items-center rounded-xl bg-accent text-accent-foreground">
                                <Bell size={18} />
                            </span>
                        </div>
                        <div className="mt-5 space-y-4">
                            <ProgressRow
                                label="WhatsApp consent"
                                value={analytics.consented_customer_count}
                                total={analytics.customer_count}
                                percent={consentRate}
                                icon={<ShieldCheck size={16} />}
                            />
                            <ProgressRow
                                label="Delivery success"
                                value={analytics.delivered_message_count}
                                total={analytics.sent_message_count}
                                percent={deliveryRate}
                                icon={<CheckCircle2 size={16} />}
                            />
                            <ProgressRow
                                label="Messages need review"
                                value={analytics.failed_message_count}
                                total={analytics.sent_message_count}
                                percent={0}
                                icon={<CircleAlert size={16} />}
                                emphasis={analytics.failed_message_count > 0}
                            />
                        </div>
                        <div className="mt-5 grid grid-cols-2 gap-3 border-t pt-4">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Customers opted out
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {analytics.opted_out_customer_count.toLocaleString()}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    Messages sent
                                </p>
                                <p className="mt-1 text-lg font-semibold">
                                    {analytics.sent_message_count.toLocaleString()}
                                </p>
                            </div>
                        </div>
                        {canViewOperations && (
                            <Link
                                className="mt-5 flex items-center justify-between rounded-xl bg-muted/70 px-4 py-3 text-sm transition hover:bg-muted"
                                href="/operations/health"
                            >
                                <span className="flex items-center gap-2">
                                    <Activity size={16} /> Automation system
                                    health
                                </span>
                                <ArrowRight size={15} />{' '}
                            </Link>
                        )}
                    </aside>
                </section>
            </div>
        </main>
    );
}

function ProgressRow({
    label,
    value,
    total,
    percent,
    icon,
    emphasis = false,
}: {
    label: string;
    value: number;
    total: number;
    percent: number;
    icon: ReactNode;
    emphasis?: boolean;
}) {
    return (
        <div>
            <div className="flex items-center justify-between gap-3 text-sm">
                <span
                    className={`flex items-center gap-2 ${emphasis ? 'text-rose-700 dark:text-rose-300' : 'text-muted-foreground'}`}
                >
                    {icon}
                    {label}
                </span>
                <strong className="font-semibold tabular-nums text-foreground">
                    {value.toLocaleString()}
                    {label === 'Messages need review' ? (
                        ''
                    ) : (
                        <span className="ml-1 font-normal text-muted-foreground">
                            / {total.toLocaleString()}
                        </span>
                    )}
                </strong>
            </div>
            <div
                aria-label={label}
                aria-valuemax={100}
                aria-valuemin={0}
                aria-valuenow={Math.min(percent, 100)}
                className="mt-2 h-1.5 overflow-hidden rounded-full bg-muted"
                role="progressbar"
            >
                <span
                    className={`block h-full rounded-full ${emphasis ? 'bg-rose-500' : 'bg-[#c65046]'}`}
                    style={{ width: `${Math.max(0, Math.min(percent, 100))}%` }}
                />
            </div>
        </div>
    );
}
