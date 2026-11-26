import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, BarChart3, Download, Filter } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type AnalyticsReport = {
    customers_registered: number;
    visits_recorded: number;
    returning_customers: number;
    messages_sent: number;
    messages_delivered: number;
    messages_failed: number;
    follow_ups_scheduled: number;
    reviews_received: number;
    average_rating: number | null;
};

type AnalyticsFilters = { start_date: string; end_date: string };

const metrics: {
    key: keyof AnalyticsReport;
    label: string;
    description: string;
}[] = [
    {
        key: 'customers_registered',
        label: 'New customers',
        description: 'Customer records created during this period.',
    },
    {
        key: 'visits_recorded',
        label: 'Completed visits',
        description: 'Visits recorded by staff during this period.',
    },
    {
        key: 'returning_customers',
        label: 'Returning customers',
        description:
            'Customers with more than one recorded visit in this period.',
    },
    {
        key: 'messages_sent',
        label: 'Messages sent',
        description: 'Outbound WhatsApp messages with a recorded sent time.',
    },
    {
        key: 'messages_delivered',
        label: 'Messages delivered',
        description: 'Outbound messages with a provider delivery timestamp.',
    },
    {
        key: 'messages_failed',
        label: 'Messages failed',
        description: 'Outbound messages with a recorded provider failure.',
    },
    {
        key: 'follow_ups_scheduled',
        label: 'Follow-ups scheduled',
        description:
            'Stored follow-ups whose scheduled time is in this period.',
    },
    {
        key: 'reviews_received',
        label: 'Reviews received',
        description: 'Reviews by their recorded review date.',
    },
    {
        key: 'average_rating',
        label: 'Average explicit rating',
        description: 'Mean of customer ratings recorded during this period.',
    },
];

export default function AnalyticsPage({
    report,
    filters,
    canExport,
}: {
    report: AnalyticsReport;
    filters: AnalyticsFilters;
    canExport: boolean;
}) {
    const [startDate, setStartDate] = useState(filters.start_date);
    const [endDate, setEndDate] = useState(filters.end_date);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/analytics',
            { start_date: startDate, end_date: endDate },
            { preserveState: true, replace: true },
        );
    }

    const query = new URLSearchParams({
        start_date: filters.start_date,
        end_date: filters.end_date,
    });

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Analytics" />
            <div className="mx-auto max-w-6xl">
                <Link
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    href="/dashboard"
                >
                    <ArrowLeft aria-hidden="true" size={16} />
                    Dashboard
                </Link>
                <header className="mb-7 mt-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            BUSINESS REPORTING
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Analytics
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Operational totals calculated from recorded CRM
                            activity.
                        </p>
                    </div>
                    {canExport && (
                        <a
                            className="inline-flex h-10 items-center gap-2 rounded-lg border bg-card px-3 text-sm hover:bg-muted"
                            href={`/analytics/export?${query.toString()}`}
                        >
                            <Download aria-hidden="true" size={16} />
                            Export CSV
                        </a>
                    )}
                </header>

                <form
                    className="mb-6 flex flex-wrap items-end gap-3 rounded-2xl border bg-card p-4"
                    onSubmit={applyFilters}
                >
                    <label className="grid gap-1.5 text-sm">
                        <span className="font-medium">From</span>
                        <input
                            className="h-10 rounded-lg border bg-background px-3"
                            max={endDate}
                            onChange={(event) =>
                                setStartDate(event.target.value)
                            }
                            required
                            type="date"
                            value={startDate}
                        />
                    </label>
                    <label className="grid gap-1.5 text-sm">
                        <span className="font-medium">To</span>
                        <input
                            className="h-10 rounded-lg border bg-background px-3"
                            min={startDate}
                            onChange={(event) => setEndDate(event.target.value)}
                            required
                            type="date"
                            value={endDate}
                        />
                    </label>
                    <button
                        className="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-4 text-sm font-medium text-primary-foreground"
                        type="submit"
                    >
                        <Filter aria-hidden="true" size={16} />
                        Apply dates
                    </button>
                    <p className="basis-full text-xs text-muted-foreground">
                        Dates use the barbershop’s configured timezone. A range
                        can include at most 366 days.
                    </p>
                </form>

                <section
                    aria-label="Analytics metrics"
                    className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3"
                >
                    {metrics.map((metric) => {
                        const value = report[metric.key];
                        const formatted =
                            metric.key === 'average_rating'
                                ? typeof value === 'number'
                                    ? `${value.toFixed(1)} / 5`
                                    : '—'
                                : (value ?? 0).toLocaleString();

                        return (
                            <article
                                className="rounded-2xl border bg-card p-5"
                                key={metric.key}
                            >
                                <div className="flex items-center gap-2 text-sm text-muted-foreground">
                                    <BarChart3 aria-hidden="true" size={16} />
                                    {metric.label}
                                </div>
                                <p className="mt-4 text-3xl font-semibold tracking-tight">
                                    {formatted}
                                </p>
                                <p className="mt-2 text-xs leading-5 text-muted-foreground">
                                    {metric.description}
                                </p>
                            </article>
                        );
                    })}
                </section>
            </div>
        </main>
    );
}
