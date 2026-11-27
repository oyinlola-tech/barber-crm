import { Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCheck, MessageCircle, Send } from 'lucide-react';
import { useEffect, useRef, type FormEvent } from 'react';

type MessageItem = {
    id: number;
    direction: 'inbound' | 'outbound';
    type: string;
    status: string;
    body: string | null;
    sent_at: string | null;
    created_at: string;
};

type ConversationDetail = {
    id: number;
    status: 'open' | 'closed';
    assigned_to: number | null;
    unread_count: number;
    customer: {
        id: number;
        first_name: string;
        last_name: string | null;
        phone: string;
        status: string;
        archived_at: string | null;
    };
    messages: MessageItem[];
    notes: Array<{
        id: number;
        body: string;
        created_at: string;
        creator: { name: string } | null;
    }>;
};

type InboxConversationProps = {
    conversation: ConversationDetail;
    assignees: Array<{ id: number; name: string }>;
    canReply: boolean;
    canAssign: boolean;
    canChangeStatus: boolean;
    canAddNote: boolean;
    canReplyPermission: boolean;
    consentGranted: boolean;
    serviceWindowOpen: boolean;
    canSendTemplate: boolean;
    templates: Array<{
        id: number;
        name: string;
        language: string;
        purpose: string | null;
    }>;
    status?: string;
    templateSendBlocked: boolean;
    freeFormReplyBlocked: boolean;
};

export default function InboxConversationPage({
    conversation,
    assignees,
    canReply,
    canAssign,
    canChangeStatus,
    canAddNote,
    canReplyPermission,
    consentGranted,
    serviceWindowOpen,
    canSendTemplate,
    templates,
    status,
    templateSendBlocked,
    freeFormReplyBlocked,
}: InboxConversationProps) {
    const reply = useForm({ body: '' });
    const note = useForm({ body: '' });
    const assignment = useForm({
        assignee_id: conversation.assigned_to?.toString() ?? '',
    });
    const templateReply = useForm({ template_id: '', idempotency_key: '' });
    const templateRequestKey = useRef<string | null>(null);

    useEffect(() => {
        const interval = window.setInterval(() => {
            if (document.visibilityState === 'visible') {
                router.reload({
                    only: [
                        'conversation',
                        'canReply',
                        'canAssign',
                        'canChangeStatus',
                        'canAddNote',
                        'canReplyPermission',
                        'consentGranted',
                        'serviceWindowOpen',
                        'canSendTemplate',
                        'templates',
                        'templateSendBlocked',
                        'freeFormReplyBlocked',
                    ],
                });
            }
        }, 15000);

        return () => window.clearInterval(interval);
    }, []);

    function sendReply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        reply.post(`/inbox/${conversation.id}/reply`, {
            onSuccess: () => reply.reset(),
        });
    }

    function sendTemplateReply(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        templateRequestKey.current ??= crypto.randomUUID();
        templateReply.setData('idempotency_key', templateRequestKey.current);
        templateReply.transform((data) => ({
            ...data,
            idempotency_key: templateRequestKey.current ?? data.idempotency_key,
        }));
        templateReply.post(`/inbox/${conversation.id}/template-reply`, {
            onSuccess: () => {
                templateReply.reset();
                templateRequestKey.current = null;
            },
        });
    }

    function addNote(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        note.post(`/inbox/${conversation.id}/notes`, {
            onSuccess: () => note.reset(),
        });
    }

    function saveAssignment(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        assignment.post(`/inbox/${conversation.id}/assignment`);
    }

    function toggleStatus() {
        router.patch(`/inbox/${conversation.id}/status`, {
            status: conversation.status === 'open' ? 'closed' : 'open',
        });
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head
                title={`${conversation.customer.first_name} ${conversation.customer.last_name ?? ''} conversation`}
            />
            <div className="mx-auto max-w-6xl">
                {status && (
                    <p
                        className="mb-4 rounded-lg border bg-card px-4 py-3 text-sm"
                        role="status"
                    >
                        {status}
                    </p>
                )}
                <Link
                    className="inline-flex items-center gap-2 text-sm font-medium text-muted-foreground hover:text-foreground"
                    href="/inbox"
                >
                    <ArrowLeft aria-hidden="true" size={16} />
                    Back to inbox
                </Link>

                <div className="mt-5 grid gap-5 lg:grid-cols-[minmax(0,1fr)_300px]">
                    <section className="flex min-h-[70vh] flex-col overflow-hidden rounded-2xl border bg-card">
                        <header className="flex flex-wrap items-center justify-between gap-4 border-b px-5 py-4">
                            <div className="flex min-w-0 items-center gap-3">
                                <span className="grid size-10 shrink-0 place-items-center rounded-full bg-primary/10 text-sm font-semibold text-primary">
                                    {conversation.customer.first_name
                                        .slice(0, 1)
                                        .toUpperCase()}
                                </span>
                                <div className="min-w-0">
                                    <h1 className="truncate font-semibold">
                                        {conversation.customer.first_name}{' '}
                                        {conversation.customer.last_name ?? ''}
                                    </h1>
                                    <p className="text-sm text-muted-foreground">
                                        {conversation.customer.phone}
                                    </p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                {conversation.unread_count > 0 && (
                                    <button
                                        className="inline-flex h-9 items-center gap-2 rounded-md border px-3 text-xs font-medium hover:bg-muted"
                                        onClick={() =>
                                            router.post(
                                                `/inbox/${conversation.id}/read`,
                                            )
                                        }
                                        type="button"
                                    >
                                        <CheckCheck
                                            aria-hidden="true"
                                            size={14}
                                        />
                                        Mark read
                                    </button>
                                )}
                                <span className="rounded-full bg-muted px-2.5 py-1 text-xs font-medium capitalize">
                                    {conversation.status}
                                </span>
                            </div>
                        </header>

                        <div className="flex-1 space-y-4 overflow-y-auto bg-muted/20 px-4 py-5 sm:px-6">
                            {conversation.messages.length === 0 ? (
                                <div className="py-14 text-center text-sm text-muted-foreground">
                                    No messages in this conversation.
                                </div>
                            ) : (
                                conversation.messages.map((message) => (
                                    <article
                                        className={`flex ${message.direction === 'outbound' ? 'justify-end' : 'justify-start'}`}
                                        key={message.id}
                                    >
                                        <div
                                            className={`max-w-[88%] rounded-2xl px-4 py-3 sm:max-w-[75%] ${message.direction === 'outbound' ? 'rounded-br-sm bg-primary text-primary-foreground' : 'rounded-bl-sm border bg-card'}`}
                                        >
                                            <p className="whitespace-pre-wrap break-words text-sm">
                                                {message.body ??
                                                    `(${message.type} message received)`}
                                            </p>
                                            <div className="mt-2 flex items-center justify-end gap-2 text-[10px] opacity-70">
                                                <time
                                                    dateTime={
                                                        message.sent_at ??
                                                        message.created_at
                                                    }
                                                >
                                                    {new Date(
                                                        message.sent_at ??
                                                            message.created_at,
                                                    ).toLocaleString()}
                                                </time>
                                                {message.direction ===
                                                    'outbound' && (
                                                    <span>
                                                        {message.status}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                    </article>
                                ))
                            )}
                        </div>

                        <footer className="border-t p-4 sm:p-5">
                            {canReply ? (
                                <form
                                    className="space-y-2"
                                    onSubmit={sendReply}
                                >
                                    <label
                                        className="sr-only"
                                        htmlFor="reply-body"
                                    >
                                        Write a WhatsApp reply
                                    </label>
                                    <textarea
                                        className="min-h-24 w-full resize-y rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        id="reply-body"
                                        maxLength={4096}
                                        onChange={(event) =>
                                            reply.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Write a reply..."
                                        value={reply.data.body}
                                    />
                                    {reply.errors.body && (
                                        <p className="text-xs text-destructive">
                                            {reply.errors.body}
                                        </p>
                                    )}
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs text-muted-foreground">
                                            Replies require recorded consent and
                                            an open 24-hour customer service
                                            window.
                                        </p>
                                        <button
                                            className="inline-flex h-10 shrink-0 items-center gap-2 rounded-md bg-primary px-4 text-sm font-medium text-primary-foreground disabled:opacity-50"
                                            disabled={
                                                reply.processing ||
                                                reply.data.body.trim() === ''
                                            }
                                            type="submit"
                                        >
                                            <Send
                                                aria-hidden="true"
                                                size={15}
                                            />
                                            Send reply
                                        </button>
                                    </div>
                                </form>
                            ) : freeFormReplyBlocked ? (
                                <p
                                    className="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                                    role="alert"
                                >
                                    A previous reply is still processing or has
                                    uncertain delivery. Wait for it to be
                                    reconciled or for a new customer message
                                    before sending again.
                                </p>
                            ) : !canReply &&
                              (!canReplyPermission ||
                                  !consentGranted ||
                                  conversation.customer.status !== 'active' ||
                                  conversation.customer.archived_at !==
                                      null) ? (
                                <div className="flex items-start gap-3 rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    <MessageCircle
                                        aria-hidden="true"
                                        className="mt-0.5 shrink-0"
                                        size={17}
                                    />
                                    <p>
                                        {!canReplyPermission
                                            ? 'Your staff role does not permit sending WhatsApp replies.'
                                            : !consentGranted
                                              ? 'Messaging is paused because this customer has not granted WhatsApp consent.'
                                              : 'This customer is inactive or archived, so sending is disabled.'}
                                    </p>
                                </div>
                            ) : null}
                            {canSendTemplate && templateSendBlocked && (
                                <p
                                    className="mt-4 rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950"
                                    role="alert"
                                >
                                    A previous template send has uncertain
                                    delivery. Do not resend until it is
                                    reconciled or the customer sends a new
                                    message.
                                </p>
                            )}
                            {canSendTemplate && !templateSendBlocked && (
                                <form
                                    className="mt-4 space-y-2 border-t pt-4"
                                    onSubmit={sendTemplateReply}
                                >
                                    <label
                                        className="text-sm font-medium"
                                        htmlFor="template-reply"
                                    >
                                        Send an approved template
                                    </label>
                                    <select
                                        className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                        id="template-reply"
                                        onChange={(event) =>
                                            templateReply.setData(
                                                'template_id',
                                                event.target.value,
                                            )
                                        }
                                        value={templateReply.data.template_id}
                                    >
                                        <option value="">
                                            Choose a template
                                        </option>
                                        {templates.map((template) => (
                                            <option
                                                key={template.id}
                                                value={template.id}
                                            >
                                                {template.name} (
                                                {template.language})
                                            </option>
                                        ))}
                                    </select>
                                    {templateReply.errors.template_id && (
                                        <p className="text-xs text-destructive">
                                            {templateReply.errors.template_id}
                                        </p>
                                    )}
                                    {templateReply.errors.idempotency_key && (
                                        <p className="text-xs text-destructive">
                                            {
                                                templateReply.errors
                                                    .idempotency_key
                                            }
                                        </p>
                                    )}
                                    {templates.length === 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            No enabled, WhatsApp-approved
                                            templates are available.
                                        </p>
                                    ) : (
                                        <div className="flex items-center justify-between gap-3">
                                            <p className="text-xs text-muted-foreground">
                                                {serviceWindowOpen
                                                    ? 'Templates may also be used during the customer service window.'
                                                    : 'An approved template can be used after the customer service window closes.'}
                                            </p>
                                            <button
                                                className="inline-flex h-10 shrink-0 items-center gap-2 rounded-md border px-4 text-sm font-medium disabled:opacity-50"
                                                disabled={
                                                    templateReply.processing ||
                                                    templateReply.data
                                                        .template_id === ''
                                                }
                                                type="submit"
                                            >
                                                <Send
                                                    aria-hidden="true"
                                                    size={15}
                                                />
                                                Send template
                                            </button>
                                        </div>
                                    )}
                                </form>
                            )}
                            {!canReply &&
                                canReplyPermission &&
                                consentGranted &&
                                conversation.customer.status === 'active' &&
                                conversation.customer.archived_at === null &&
                                !serviceWindowOpen &&
                                !canSendTemplate && (
                                    <p className="mt-3 text-sm text-muted-foreground">
                                        An approved WhatsApp template is
                                        required, but the WhatsApp connection is
                                        not ready.
                                    </p>
                                )}
                        </footer>
                    </section>

                    <aside className="space-y-5">
                        {(canAssign || canChangeStatus) && (
                            <section className="rounded-2xl border bg-card p-5">
                                <h2 className="font-semibold">
                                    Conversation controls
                                </h2>
                                {canAssign && (
                                    <form
                                        className="mt-4 space-y-2"
                                        onSubmit={saveAssignment}
                                    >
                                        <label
                                            className="text-xs font-medium text-muted-foreground"
                                            htmlFor="assignee"
                                        >
                                            Assigned staff member
                                        </label>
                                        <select
                                            className="h-10 w-full rounded-md border bg-background px-3 text-sm"
                                            id="assignee"
                                            onChange={(event) =>
                                                assignment.setData(
                                                    'assignee_id',
                                                    event.target.value,
                                                )
                                            }
                                            value={assignment.data.assignee_id}
                                        >
                                            <option value="">Unassigned</option>
                                            {assignees.map((assignee) => (
                                                <option
                                                    key={assignee.id}
                                                    value={assignee.id}
                                                >
                                                    {assignee.name}
                                                </option>
                                            ))}
                                        </select>
                                        {assignment.errors.assignee_id && (
                                            <p className="text-xs text-destructive">
                                                {assignment.errors.assignee_id}
                                            </p>
                                        )}
                                        <button
                                            className="h-9 w-full rounded-md border text-sm font-medium hover:bg-muted disabled:opacity-50"
                                            disabled={assignment.processing}
                                            type="submit"
                                        >
                                            Save assignment
                                        </button>
                                    </form>
                                )}
                                {canChangeStatus && (
                                    <button
                                        className="mt-3 h-9 w-full rounded-md border text-sm font-medium hover:bg-muted disabled:opacity-50"
                                        onClick={toggleStatus}
                                        type="button"
                                    >
                                        {conversation.status === 'open'
                                            ? 'Close conversation'
                                            : 'Reopen conversation'}
                                    </button>
                                )}
                            </section>
                        )}

                        {canAddNote && (
                            <section className="rounded-2xl border bg-card p-5">
                                <h2 className="font-semibold">
                                    Internal notes
                                </h2>
                                <form
                                    className="mt-4 space-y-2"
                                    onSubmit={addNote}
                                >
                                    <label
                                        className="sr-only"
                                        htmlFor="conversation-note"
                                    >
                                        Add an internal note
                                    </label>
                                    <textarea
                                        className="min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm outline-none focus-visible:ring-2 focus-visible:ring-ring"
                                        id="conversation-note"
                                        maxLength={4000}
                                        onChange={(event) =>
                                            note.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        placeholder="Add a note for staff only"
                                        value={note.data.body}
                                    />
                                    {note.errors.body && (
                                        <p className="text-xs text-destructive">
                                            {note.errors.body}
                                        </p>
                                    )}
                                    <button
                                        className="h-9 w-full rounded-md bg-secondary text-sm font-medium text-secondary-foreground hover:opacity-90 disabled:opacity-50"
                                        disabled={
                                            note.processing ||
                                            note.data.body.trim() === ''
                                        }
                                        type="submit"
                                    >
                                        Add internal note
                                    </button>
                                </form>
                                <ul className="mt-4 space-y-3">
                                    {conversation.notes.map((item) => (
                                        <li
                                            className="rounded-lg bg-muted/60 p-3"
                                            key={item.id}
                                        >
                                            <p className="whitespace-pre-wrap break-words text-sm">
                                                {item.body}
                                            </p>
                                            <p className="mt-2 text-[10px] text-muted-foreground">
                                                {item.creator?.name ??
                                                    'Former staff'}{' '}
                                                ·{' '}
                                                {new Date(
                                                    item.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        </li>
                                    ))}
                                    {conversation.notes.length === 0 && (
                                        <li className="text-sm text-muted-foreground">
                                            No internal notes yet.
                                        </li>
                                    )}
                                </ul>
                            </section>
                        )}

                        <section className="rounded-2xl border bg-card p-5">
                            <h2 className="font-semibold">Customer status</h2>
                            <dl className="mt-4 space-y-3 text-sm">
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        WhatsApp consent
                                    </dt>
                                    <dd
                                        className={
                                            consentGranted
                                                ? 'font-medium text-emerald-700'
                                                : 'font-medium text-amber-700'
                                        }
                                    >
                                        {consentGranted
                                            ? 'Granted'
                                            : 'Not active'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Service window
                                    </dt>
                                    <dd>
                                        {serviceWindowOpen ? 'Open' : 'Closed'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Customer record
                                    </dt>
                                    <dd className="capitalize">
                                        {conversation.customer.status}
                                    </dd>
                                </div>
                            </dl>
                        </section>
                    </aside>
                </div>
            </div>
        </main>
    );
}
