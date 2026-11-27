import { Head, Link } from '@inertiajs/react';
import {
    Activity,
    ArrowLeft,
    Database,
    MessageCircle,
    Server,
} from 'lucide-react';

type ComponentHealth = { status: string };
type OperationalHealth = {
    overall: string;
    checked_at: string;
    database: ComponentHealth;
    queue: {
        driver: string;
        status: string;
        pending: number;
        failed: number;
        worker_heartbeat: string | null;
    };
    scheduler: { status: string; last_heartbeat: string | null };
    whatsapp: ComponentHealth;
    webhooks: {
        status: string;
        unprocessed: number;
        stale_callbacks: number;
        failed_recent: number;
        last_received_at: string | null;
    };
};

export default function OperationalHealthPage({
    health,
}: {
    health: OperationalHealth;
}) {
    const components = [
        {
            name: 'Database',
            icon: <Database size={18} />,
            status: health.database.status,
            details: 'PostgreSQL connectivity',
        },
        {
            name: 'Queue worker',
            icon: <Server size={18} />,
            status: health.queue.status,
            details: `${health.queue.pending} pending · ${health.queue.failed} failed · ${health.queue.driver}`,
        },
        {
            name: 'Scheduler',
            icon: <Activity size={18} />,
            status: health.scheduler.status,
            details: health.scheduler.last_heartbeat
                ? `Last heartbeat ${formatDate(health.scheduler.last_heartbeat)}`
                : 'No scheduler heartbeat recorded yet',
        },
        {
            name: 'WhatsApp',
            icon: <MessageCircle size={18} />,
            status: health.whatsapp.status,
            details:
                'Configured connection state; live Meta delivery is not probed here',
        },
        {
            name: 'Webhook processing',
            icon: <Activity size={18} />,
            status: health.webhooks.status,
            details: `${health.webhooks.unprocessed} pending · ${health.webhooks.stale_callbacks} stale callbacks · ${health.webhooks.failed_recent} failed in 24 hours`,
        },
    ];

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="System health" />
            <div className="mx-auto max-w-5xl">
                <Link
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    href="/dashboard"
                >
                    <ArrowLeft size={16} />
                    Dashboard
                </Link>
                <header className="mb-7 mt-5">
                    <p className="text-sm font-medium text-primary">
                        OPERATIONS
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        System health
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Current application checks, observed from stored
                        operational signals.
                    </p>
                </header>
                <section
                    className="rounded-2xl border bg-card p-5 sm:p-7"
                    aria-label="Operational status"
                >
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b pb-4">
                        <div>
                            <h2 className="font-semibold">Overall status</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Last checked {formatDate(health.checked_at)}
                            </p>
                        </div>
                        <Status status={health.overall} />
                    </div>
                    <ul className="divide-y">
                        {components.map((component) => (
                            <li
                                className="flex items-start gap-4 py-4"
                                key={component.name}
                            >
                                <span className="mt-0.5 text-muted-foreground">
                                    {component.icon}
                                </span>
                                <div className="min-w-0 flex-1">
                                    <p className="font-medium">
                                        {component.name}
                                    </p>
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {component.details}
                                    </p>
                                    {component.name === 'Queue worker' &&
                                        health.queue.worker_heartbeat && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Worker heartbeat{' '}
                                                {formatDate(
                                                    health.queue
                                                        .worker_heartbeat,
                                                )}
                                            </p>
                                        )}
                                    {component.name === 'Webhook processing' &&
                                        health.webhooks.last_received_at && (
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                Most recent event{' '}
                                                {formatDate(
                                                    health.webhooks
                                                        .last_received_at,
                                                )}
                                            </p>
                                        )}
                                </div>
                                <Status status={component.status} />
                            </li>
                        ))}
                    </ul>
                    <p className="mt-3 border-t pt-4 text-xs leading-5 text-muted-foreground">
                        A healthy WhatsApp status reflects the saved connection
                        state only. Provider connectivity, template approval and
                        actual delivery require separate verification.
                    </p>
                </section>
            </div>
        </main>
    );
}

function Status({ status }: { status: string }) {
    const style =
        status === 'healthy'
            ? 'bg-emerald-100 text-emerald-800'
            : status === 'unhealthy'
              ? 'bg-red-100 text-red-800'
              : 'bg-amber-100 text-amber-900';
    return (
        <span
            className={`shrink-0 rounded-full px-2.5 py-1 text-xs font-medium ${style}`}
        >
            {status}
        </span>
    );
}

function formatDate(value: string): string {
    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}
