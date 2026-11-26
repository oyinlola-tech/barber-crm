import { Head, Link } from '@inertiajs/react';
import { MessageCircle, Scissors, Users } from 'lucide-react';
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
    const stats = [
        {
            label: 'Customers',
            value: analytics.customer_count,
            icon: <Users size={18} />,
        },
        {
            label: 'With WhatsApp consent',
            value: analytics.consented_customer_count,
            icon: <MessageCircle size={18} />,
        },
        {
            label: 'Visits recorded',
            value: analytics.visit_count,
            icon: <Scissors size={18} />,
        },
        {
            label: 'Returning customers',
            value: analytics.returning_customer_count,
            icon: <Users size={18} />,
        },
        {
            label: 'Follow-ups pending',
            value: analytics.pending_follow_up_count,
            icon: <MessageCircle size={18} />,
        },
        {
            label: 'Messages delivered',
            value: analytics.delivered_message_count,
            icon: <MessageCircle size={18} />,
        },
        {
            label: 'Reviews received',
            value: analytics.review_count,
            icon: <MessageCircle size={18} />,
        },
        {
            label: 'Customers opted out',
            value: analytics.opted_out_customer_count,
            icon: <Users size={18} />,
        },
    ];

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Dashboard" />
            <div className="mx-auto max-w-7xl">
                <header className="flex flex-wrap items-center justify-between gap-4 border-b pb-6">
                    <div className="flex items-center gap-3">
                        <div className="grid size-11 place-items-center rounded-xl bg-primary text-primary-foreground">
                            <Scissors aria-hidden="true" size={19} />
                        </div>
                        <div>
                            <p className="text-sm text-muted-foreground">
                                Barbershop WhatsApp CRM
                            </p>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {business.name}
                            </h1>
                        </div>
                    </div>
                    <div className="flex items-center gap-3">
                        <span
                            className={`rounded-full px-3 py-1.5 text-xs font-medium ${analytics.whatsapp_connected ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-900'}`}
                        >
                            WhatsApp{' '}
                            {analytics.whatsapp_connected
                                ? 'connected'
                                : 'not connected'}
                        </span>
                        {canViewOperations && (
                            <Link
                                className="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                href="/operations/health"
                            >
                                System health
                            </Link>
                        )}
                        {canViewAudit && (
                            <Link
                                className="rounded-md border px-3 py-2 text-sm hover:bg-muted"
                                href="/operations/audit"
                            >
                                Audit log
                            </Link>
                        )}
                    </div>
                </header>

                <section
                    aria-label="CRM statistics"
                    className="mt-7 grid gap-3 sm:grid-cols-2 xl:grid-cols-4"
                >
                    {stats.map((stat) => (
                        <StatCard
                            key={stat.label}
                            label={stat.label}
                            value={stat.value}
                            icon={stat.icon}
                        />
                    ))}
                </section>

                <section className="mt-7 grid gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(280px,0.8fr)]">
                    <div className="rounded-2xl border bg-card p-5 sm:p-6">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="font-semibold">
                                    Recently registered customers
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    The latest customer records in your CRM.
                                </p>
                            </div>
                            <span className="text-sm text-muted-foreground">
                                {analytics.customer_count} total
                            </span>
                        </div>
                        {analytics.recent_customers.length === 0 ? (
                            <div className="mt-6 rounded-xl border border-dashed p-8 text-center">
                                <Users
                                    aria-hidden="true"
                                    className="mx-auto text-muted-foreground"
                                    size={24}
                                />
                                <p className="mt-3 text-sm font-medium">
                                    No customers yet
                                </p>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Customer records will appear here when staff
                                    register them.
                                </p>
                            </div>
                        ) : (
                            <ul className="mt-5 divide-y">
                                {analytics.recent_customers.map((customer) => (
                                    <li
                                        className="flex items-center justify-between gap-3 py-3"
                                        key={customer.id}
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate text-sm font-medium">
                                                {customer.first_name}{' '}
                                                {customer.last_name ?? ''}
                                            </p>
                                            <p className="mt-0.5 text-sm text-muted-foreground">
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
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>

                    <aside className="rounded-2xl bg-primary p-6 text-primary-foreground">
                        <p className="text-sm text-primary-foreground/75">
                            Customer retention
                        </p>
                        <h2 className="mt-2 text-xl font-semibold">
                            Follow-up health
                        </h2>
                        <div className="mt-6 space-y-4 text-sm">
                            <p className="flex justify-between gap-4">
                                <span>Messages sent</span>
                                <strong>{analytics.sent_message_count}</strong>
                            </p>
                            <p className="flex justify-between gap-4">
                                <span>Messages failed</span>
                                <strong>
                                    {analytics.failed_message_count}
                                </strong>
                            </p>
                            <p className="flex justify-between gap-4">
                                <span>Explicit average rating</span>
                                <strong>
                                    {analytics.average_rating === null
                                        ? 'No ratings yet'
                                        : `${analytics.average_rating} / 5`}
                                </strong>
                            </p>
                        </div>
                        <p className="mt-7 border-t border-primary-foreground/20 pt-4 text-xs leading-5 text-primary-foreground/75">
                            Automated messages are counted from stored CRM
                            delivery records. Meta connection and approved
                            templates are configured separately.
                        </p>
                    </aside>
                </section>
            </div>
        </main>
    );
}

function StatCard({
    label,
    value,
    icon,
}: {
    label: string;
    value: number;
    icon: ReactNode;
}) {
    return (
        <article className="rounded-xl border bg-card p-4">
            <div className="flex items-center justify-between text-muted-foreground">
                <span className="text-sm">{label}</span>
                {icon}
            </div>
            <p className="mt-4 text-2xl font-semibold tracking-tight">
                {value.toLocaleString()}
            </p>
        </article>
    );
}
