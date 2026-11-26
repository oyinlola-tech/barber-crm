import { Head, router } from '@inertiajs/react';
import type { FormEvent } from 'react';

type ScheduledMessage = {
    id: number;
    status: string;
    scheduled_at: string;
    last_error: string | null;
    attempts: number;
    manual_retry_count: number;
    customer: {
        first_name: string;
        last_name: string | null;
        phone: string;
    } | null;
};

type Paginator<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

const statuses = [
    'pending',
    'queued',
    'processing',
    'sending',
    'sent',
    'failed',
    'cancelled',
    'reconciliation_required',
] as const;

export default function ScheduledMessagesPage({
    messages,
    filters,
    permissions,
    manualRetryLimit,
}: {
    messages: Paginator<ScheduledMessage>;
    filters: { status?: string | null };
    permissions: { cancel: boolean; retry: boolean };
    manualRetryLimit: number;
}) {
    function filterMessages(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const form = new FormData(event.currentTarget);
        const status = String(form.get('status') ?? '');
        router.get('/messages/scheduled', status ? { status } : {}, {
            preserveState: true,
            replace: true,
        });
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Scheduled messages" />
            <div className="mx-auto max-w-7xl">
                <header className="mb-7 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            WHATSAPP
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            Scheduled messages
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Review delivery state and safely manage queued
                            follow-ups.
                        </p>
                    </div>
                    <form
                        onSubmit={filterMessages}
                        className="flex flex-wrap items-center gap-2"
                    >
                        <label className="sr-only" htmlFor="message-status">
                            Filter by status
                        </label>
                        <select
                            id="message-status"
                            name="status"
                            defaultValue={filters.status ?? ''}
                            className="h-10 rounded-lg border bg-background px-3 text-sm"
                        >
                            <option value="">All statuses</option>
                            {statuses.map((status) => (
                                <option key={status} value={status}>
                                    {status.replaceAll('_', ' ')}
                                </option>
                            ))}
                        </select>
                        <button className="h-10 rounded-lg border bg-background px-4 text-sm font-medium">
                            Apply filter
                        </button>
                    </form>
                </header>

                <section className="overflow-hidden rounded-2xl border bg-card">
                    {messages.data.length === 0 ? (
                        <p className="p-8 text-sm text-muted-foreground">
                            No scheduled messages match this filter.
                        </p>
                    ) : (
                        <>
                            <div className="hidden overflow-x-auto md:block">
                                <table className="w-full text-left text-sm">
                                    <thead className="border-b bg-muted/50 text-muted-foreground">
                                        <tr>
                                            <th className="px-5 py-3 font-medium">
                                                Customer
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Status
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Scheduled
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                Attempts
                                            </th>
                                            <th className="px-5 py-3 font-medium">
                                                <span className="sr-only">
                                                    Actions
                                                </span>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y">
                                        {messages.data.map((message) => (
                                            <tr key={message.id}>
                                                <td className="px-5 py-4">
                                                    <p className="font-medium">
                                                        {
                                                            message.customer
                                                                ?.first_name
                                                        }{' '}
                                                        {
                                                            message.customer
                                                                ?.last_name
                                                        }
                                                    </p>
                                                    <p className="text-muted-foreground">
                                                        {message.customer
                                                            ?.phone ??
                                                            'Customer record unavailable'}
                                                    </p>
                                                </td>
                                                <td className="px-5 py-4">
                                                    <span className="rounded-full border px-2.5 py-1 text-xs capitalize">
                                                        {message.status.replaceAll(
                                                            '_',
                                                            ' ',
                                                        )}
                                                    </span>
                                                    {message.last_error && (
                                                        <p className="mt-1 max-w-sm text-xs text-destructive">
                                                            {message.last_error.replaceAll(
                                                                '_',
                                                                ' ',
                                                            )}
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-5 py-4">
                                                    {new Date(
                                                        message.scheduled_at,
                                                    ).toLocaleString()}
                                                </td>
                                                <td className="px-5 py-4">
                                                    {message.attempts}
                                                </td>
                                                <td className="px-5 py-4">
                                                    <MessageActions
                                                        message={message}
                                                        permissions={
                                                            permissions
                                                        }
                                                        manualRetryLimit={
                                                            manualRetryLimit
                                                        }
                                                    />
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="divide-y md:hidden">
                                {messages.data.map((message) => (
                                    <article
                                        className="space-y-3 p-4"
                                        key={message.id}
                                    >
                                        <div className="flex items-start justify-between gap-3">
                                            <div>
                                                <h2 className="font-medium">
                                                    {
                                                        message.customer
                                                            ?.first_name
                                                    }{' '}
                                                    {
                                                        message.customer
                                                            ?.last_name
                                                    }
                                                </h2>
                                                <p className="text-sm text-muted-foreground">
                                                    {message.customer?.phone ??
                                                        'Customer record unavailable'}
                                                </p>
                                            </div>
                                            <span className="rounded-full border px-2 py-1 text-xs capitalize">
                                                {message.status.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </span>
                                        </div>
                                        <p className="text-sm text-muted-foreground">
                                            Scheduled{' '}
                                            {new Date(
                                                message.scheduled_at,
                                            ).toLocaleString()}{' '}
                                            · {message.attempts} attempts
                                        </p>
                                        {message.last_error && (
                                            <p className="text-sm text-destructive">
                                                {message.last_error.replaceAll(
                                                    '_',
                                                    ' ',
                                                )}
                                            </p>
                                        )}
                                        <MessageActions
                                            message={message}
                                            permissions={permissions}
                                            manualRetryLimit={manualRetryLimit}
                                        />
                                    </article>
                                ))}
                            </div>
                        </>
                    )}
                    {messages.last_page > 1 && (
                        <nav
                            aria-label="Scheduled message pages"
                            className="flex items-center justify-between border-t p-4 text-sm"
                        >
                            <button
                                type="button"
                                disabled={!messages.prev_page_url}
                                onClick={() =>
                                    messages.prev_page_url &&
                                    router.visit(messages.prev_page_url)
                                }
                                className="rounded-lg border px-3 py-2 disabled:opacity-50"
                            >
                                Previous
                            </button>
                            <span className="text-muted-foreground">
                                Page {messages.current_page} of{' '}
                                {messages.last_page}
                            </span>
                            <button
                                type="button"
                                disabled={!messages.next_page_url}
                                onClick={() =>
                                    messages.next_page_url &&
                                    router.visit(messages.next_page_url)
                                }
                                className="rounded-lg border px-3 py-2 disabled:opacity-50"
                            >
                                Next
                            </button>
                        </nav>
                    )}
                </section>
            </div>
        </main>
    );
}

function MessageActions({
    message,
    permissions,
    manualRetryLimit,
}: {
    message: ScheduledMessage;
    permissions: { cancel: boolean; retry: boolean };
    manualRetryLimit: number;
}) {
    const canCancel =
        permissions.cancel && ['pending', 'queued'].includes(message.status);
    const canRetry =
        permissions.retry &&
        message.status === 'failed' &&
        message.manual_retry_count < manualRetryLimit;
    if (!canCancel && !canRetry) return null;

    return (
        <div className="flex flex-wrap gap-2">
            {canCancel && (
                <button
                    type="button"
                    onClick={() => {
                        if (window.confirm('Cancel this scheduled message?')) {
                            router.post(
                                `/messages/scheduled/${message.id}/cancel`,
                                {},
                                { preserveScroll: true },
                            );
                        }
                    }}
                    className="rounded-lg border px-3 py-2 text-sm"
                >
                    Cancel message
                </button>
            )}
            {canRetry && (
                <button
                    type="button"
                    onClick={() => {
                        if (
                            window.confirm(
                                'Queue this definitively failed message for a safe retry?',
                            )
                        ) {
                            router.post(
                                `/messages/scheduled/${message.id}/retry`,
                                {},
                                { preserveScroll: true },
                            );
                        }
                    }}
                    className="rounded-lg border px-3 py-2 text-sm"
                >
                    Retry message
                </button>
            )}
        </div>
    );
}
