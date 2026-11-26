import { Head, Link, router } from '@inertiajs/react';
import { ArrowLeft, ClipboardList, Search } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type AuditEntry = {
    id: number;
    action: string;
    resource: string | null;
    resource_id: number | null;
    actor: string | null;
    ip_address: string | null;
    created_at: string;
};
type AuditPaginator = {
    data: AuditEntry[];
    links: { url: string | null; label: string; active: boolean }[];
    current_page: number;
    last_page: number;
    total: number;
};

export default function AuditLogPage({
    logs,
    filters,
    canExport,
}: {
    logs: AuditPaginator;
    filters: { action: string };
    canExport: boolean;
}) {
    const [action, setAction] = useState(filters.action);
    function search(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get('/operations/audit', action ? { action } : {}, {
            preserveState: true,
            replace: true,
        });
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Audit log" />
            <div className="mx-auto max-w-6xl">
                <Link
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    href="/dashboard"
                >
                    <ArrowLeft size={16} />
                    Dashboard
                </Link>
                <header className="mb-7 mt-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            SECURITY
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Audit log
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {logs.total.toLocaleString()} recorded actions.
                            Sensitive before and after values are hidden.
                        </p>
                    </div>
                    <form className="flex flex-wrap gap-2" onSubmit={search}>
                        <label className="sr-only" htmlFor="audit-action">
                            Filter by exact action
                        </label>
                        <input
                            id="audit-action"
                            className="h-10 w-56 rounded-lg border bg-background px-3 text-sm"
                            maxLength={100}
                            onChange={(event) => setAction(event.target.value)}
                            placeholder="Filter action"
                            value={action}
                        />
                        <button
                            className="inline-flex h-10 items-center gap-2 rounded-lg bg-primary px-3 text-sm text-primary-foreground"
                            type="submit"
                        >
                            <Search size={16} />
                            Filter
                        </button>
                        {canExport && (
                            <a
                                className="inline-flex h-10 items-center rounded-lg border px-3 text-sm font-medium"
                                href={`/operations/audit/export${filters.action ? `?action=${encodeURIComponent(filters.action)}` : ''}`}
                            >
                                Export CSV
                            </a>
                        )}
                    </form>
                </header>
                <section
                    className="overflow-hidden rounded-2xl border bg-card"
                    aria-label="Audit records"
                >
                    {logs.data.length === 0 ? (
                        <div className="p-12 text-center">
                            <ClipboardList
                                className="mx-auto text-muted-foreground"
                                size={28}
                            />
                            <p className="mt-3 font-medium">
                                No matching audit records
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Recorded staff and customer actions will appear
                                here.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-left text-sm">
                                <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-4 py-3">Action</th>
                                        <th className="px-4 py-3">Actor</th>
                                        <th className="px-4 py-3">Resource</th>
                                        <th className="px-4 py-3">
                                            IP address
                                        </th>
                                        <th className="px-4 py-3">Time</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {logs.data.map((entry) => (
                                        <tr key={entry.id}>
                                            <td className="px-4 py-3 font-medium">
                                                {entry.action}
                                            </td>
                                            <td className="px-4 py-3">
                                                {entry.actor ?? 'System'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {entry.resource
                                                    ? `${entry.resource}${entry.resource_id ? ` #${entry.resource_id}` : ''}`
                                                    : '—'}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                {entry.ip_address ?? '—'}
                                            </td>
                                            <td className="px-4 py-3 text-muted-foreground">
                                                <time
                                                    dateTime={entry.created_at}
                                                >
                                                    {new Intl.DateTimeFormat(
                                                        undefined,
                                                        {
                                                            dateStyle: 'medium',
                                                            timeStyle: 'short',
                                                        },
                                                    ).format(
                                                        new Date(
                                                            entry.created_at,
                                                        ),
                                                    )}
                                                </time>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    <nav
                        aria-label="Audit log pages"
                        className="flex flex-wrap justify-end gap-1 border-t p-3"
                    >
                        {logs.links.map((link, index) => (
                            <button
                                aria-current={link.active ? 'page' : undefined}
                                className={`rounded px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'} disabled:cursor-not-allowed disabled:opacity-40`}
                                disabled={link.url === null}
                                key={`${index}-${link.label}`}
                                onClick={() =>
                                    link.url && router.visit(link.url)
                                }
                                type="button"
                            >
                                {link.label
                                    .replace('&laquo;', '‹')
                                    .replace('&raquo;', '›')}
                            </button>
                        ))}
                    </nav>
                </section>
            </div>
        </main>
    );
}
