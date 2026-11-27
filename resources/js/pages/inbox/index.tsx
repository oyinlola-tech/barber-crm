import { Head, Link, router } from '@inertiajs/react';
import { Inbox, Search } from 'lucide-react';
import { useEffect, useState, type FormEvent } from 'react';

type ConversationSummary = {
    id: number;
    status: 'open' | 'closed';
    unread_count: number;
    last_message_at: string | null;
    customer: {
        first_name: string;
        last_name: string | null;
        phone: string;
    };
    latest_message: {
        direction: 'inbound' | 'outbound';
        type: string;
        status: string;
        body: string | null;
        created_at: string;
    } | null;
};

type PaginationLink = { url: string | null; label: string; active: boolean };

type InboxProps = {
    conversations: {
        data: ConversationSummary[];
        links: PaginationLink[];
        total: number;
    };
    filters: { status?: string; search?: string };
};

export default function InboxIndexPage({ conversations, filters }: InboxProps) {
    const [search, setSearch] = useState(filters.search ?? '');

    useEffect(() => {
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({
                    only: ['conversations'],
                });
            }
        }, 20000);

        return () => window.clearInterval(interval);
    }, []);

    function applyFilters(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/inbox',
            {
                search: search || undefined,
                status: filters.status || undefined,
            },
            { preserveState: true, replace: true },
        );
    }

    function setStatus(status: string) {
        router.get(
            '/inbox',
            { search: search || undefined, status: status || undefined },
            { preserveState: true, replace: true },
        );
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="WhatsApp inbox" />
            <div className="mx-auto max-w-6xl">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Customer communication
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                            WhatsApp inbox
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {conversations.total.toLocaleString()} conversations
                        </p>
                    </div>
                    <Link
                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                        href="/dashboard"
                    >
                        Dashboard
                    </Link>
                </header>

                <section className="mt-7 rounded-2xl border bg-card p-4 sm:p-6">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <form
                            className="flex min-w-0 flex-1 gap-2 sm:max-w-lg"
                            onSubmit={applyFilters}
                        >
                            <label
                                className="sr-only"
                                htmlFor="conversation-search"
                            >
                                Search conversations
                            </label>
                            <input
                                className="h-10 min-w-0 flex-1 rounded-md border bg-background px-3 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                id="conversation-search"
                                maxLength={100}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search customer name or WhatsApp number"
                                value={search}
                            />
                            <button
                                aria-label="Search conversations"
                                className="inline-flex h-10 items-center gap-2 rounded-md bg-primary px-3 text-sm font-medium text-primary-foreground hover:opacity-90"
                                type="submit"
                            >
                                <Search aria-hidden="true" size={16} />
                                <span className="hidden sm:inline">Search</span>
                            </button>
                        </form>
                        <label className="flex items-center gap-2 text-sm">
                            <span className="text-muted-foreground">
                                Status
                            </span>
                            <select
                                className="h-10 rounded-md border bg-background px-3"
                                onChange={(event) =>
                                    setStatus(event.target.value)
                                }
                                value={filters.status ?? ''}
                            >
                                <option value="">All conversations</option>
                                <option value="open">Open</option>
                                <option value="closed">Closed</option>
                            </select>
                        </label>
                    </div>

                    {conversations.data.length === 0 ? (
                        <div className="mt-6 rounded-xl border border-dashed px-6 py-14 text-center">
                            <Inbox
                                aria-hidden="true"
                                className="mx-auto text-muted-foreground"
                                size={28}
                            />
                            <h2 className="mt-4 font-medium">
                                No conversations found
                            </h2>
                            <p className="mx-auto mt-1 max-w-md text-sm text-muted-foreground">
                                Incoming customer messages will appear here
                                after the WhatsApp webhook is configured.
                            </p>
                        </div>
                    ) : (
                        <ul className="mt-5 divide-y">
                            {conversations.data.map((conversation) => (
                                <li key={conversation.id}>
                                    <Link
                                        className="flex items-start gap-4 rounded-lg px-3 py-4 transition-colors hover:bg-muted/60 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        href={`/inbox/${conversation.id}`}
                                    >
                                        <span className="grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                            {conversation.customer.first_name
                                                .slice(0, 1)
                                                .toUpperCase()}
                                        </span>
                                        <span className="min-w-0 flex-1">
                                            <span className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1">
                                                <span className="truncate text-sm font-semibold">
                                                    {
                                                        conversation.customer
                                                            .first_name
                                                    }{' '}
                                                    {conversation.customer
                                                        .last_name ?? ''}
                                                </span>
                                                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    {conversation.last_message_at
                                                        ? new Date(
                                                              conversation.last_message_at,
                                                          ).toLocaleString()
                                                        : 'No messages'}
                                                    {conversation.unread_count >
                                                        0 && (
                                                        <span className="grid min-w-5 place-items-center rounded-full bg-primary px-1.5 py-1 text-[10px] font-semibold text-primary-foreground">
                                                            <span
                                                                className="sr-only"
                                                                role="status"
                                                            >
                                                                {
                                                                    conversation.unread_count
                                                                }{' '}
                                                                unread message
                                                                {conversation.unread_count ===
                                                                1
                                                                    ? ''
                                                                    : 's'}
                                                            </span>
                                                            <span aria-hidden="true">
                                                                {
                                                                    conversation.unread_count
                                                                }
                                                            </span>
                                                        </span>
                                                    )}
                                                </span>
                                            </span>
                                            <span className="mt-1 block text-xs text-muted-foreground">
                                                {conversation.customer.phone}
                                            </span>
                                            <span className="mt-2 flex items-center justify-between gap-3">
                                                <span className="truncate text-sm text-muted-foreground">
                                                    {conversation.latest_message
                                                        ? `${conversation.latest_message.direction === 'outbound' ? 'You: ' : ''}${conversation.latest_message.body ?? `(${conversation.latest_message.type} message)`}`
                                                        : 'No messages yet'}
                                                </span>
                                                <span
                                                    className={`shrink-0 rounded-full px-2 py-1 text-[10px] font-medium ${conversation.status === 'open' ? 'bg-emerald-100 text-emerald-800' : 'bg-muted text-muted-foreground'}`}
                                                >
                                                    {conversation.status}
                                                </span>
                                            </span>
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}

                    {conversations.links.length > 3 && (
                        <nav
                            aria-label="Conversation pages"
                            className="mt-5 flex flex-wrap justify-end gap-2 border-t pt-4"
                        >
                            {conversations.links.map((link, index) => (
                                <button
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className={`min-w-9 rounded-md border px-3 py-2 text-sm ${link.active ? 'border-primary bg-primary text-primary-foreground' : 'bg-background text-foreground'} disabled:cursor-not-allowed disabled:opacity-50`}
                                    disabled={link.url === null}
                                    key={`${link.label}-${index}`}
                                    onClick={() =>
                                        link.url && router.get(link.url)
                                    }
                                    type="button"
                                >
                                    {link.label
                                        .replace('&laquo;', '‹')
                                        .replace('&raquo;', '›')}
                                </button>
                            ))}
                        </nav>
                    )}
                </section>
            </div>
        </main>
    );
}
