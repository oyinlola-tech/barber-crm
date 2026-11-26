import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarClock,
    Scissors,
    ShieldCheck,
    UserRound,
} from 'lucide-react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { VisitHistoryItem } from '@/features/customers/visit-history-item';

type CustomerProfile = {
    id: number;
    status: string;
    first_name: string;
    last_name: string | null;
    phone: string;
    email: string | null;
    intended_return_date: string | null;
    notes: string | null;
    tags: Array<{ id: number; name: string }>;
    note_entries: Array<{
        id: number;
        body: string;
        created_at: string;
        author?: { name: string } | null;
    }>;
    created_at: string;
    visits: Array<{
        id: number;
        visited_at: string;
        notes: string | null;
        deleted_at: string | null;
        staff?: { name: string } | null;
        services: Array<{ service_id: number | null; service_name: string }>;
        staff_profile_id: number | null;
    }>;
    consents: Array<{
        id: number;
        channel: string;
        granted: boolean;
        source: string | null;
        recorded_at: string;
        revoked_at: string | null;
    }>;
};

type CustomerDetailsProps = {
    customer: CustomerProfile;
    businessTimezone: string;
    services: Array<{ id: number; name: string }>;
    staff: Array<{ id: number; name: string }>;
    permissions: {
        update: boolean;
        archive: boolean;
        restore_customer: boolean;
        edit_notes: boolean;
        record_consent: boolean;
        revoke_consent: boolean;
        view_consent: boolean;
        record_visit: boolean;
        view_visits: boolean;
        update_visit: boolean;
        void_visit: boolean;
        restore_visit: boolean;
        add_note: boolean;
        manage_tags: boolean;
        anonymize: boolean;
        permanently_delete: boolean;
    };
};

export default function CustomerDetailsPage({
    customer,
    businessTimezone,
    services,
    staff,
    permissions,
}: CustomerDetailsProps) {
    const update = useForm({
        first_name: customer.first_name,
        last_name: customer.last_name ?? '',
        phone: customer.phone,
        email: customer.email ?? '',
        intended_return_date: customer.intended_return_date ?? '',
        notes: customer.notes ?? '',
    });
    const latestWhatsAppConsent = customer.consents
        .filter((item) => item.channel === 'whatsapp')
        .sort((left, right) => right.id - left.id)[0];
    const consent = useForm({
        channel: 'whatsapp',
        granted:
            latestWhatsAppConsent?.granted === true &&
            latestWhatsAppConsent.revoked_at === null,
        source: 'verbal',
    });
    const visit = useForm({
        customer_id: customer.id,
        staff_profile_id: '',
        visited_at: toBusinessDateTime(new Date(), businessTimezone),
        service_ids: [] as number[],
        notes: '',
    });
    const customerNote = useForm({ body: '' });
    const tags = useForm({ tags: customer.tags.map((tag) => tag.name) });
    const [tagInput, setTagInput] = useState(
        customer.tags.map((tag) => tag.name).join(', '),
    );

    function saveCustomer(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        update.patch(`/customers/${customer.id}`);
    }

    function saveConsent(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        consent.post(`/customers/${customer.id}/consents`);
    }

    function recordVisit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        visit.post('/visits', { onSuccess: () => visit.reset('notes') });
    }

    function addCustomerNote(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        customerNote.post(`/customers/${customer.id}/notes`, {
            onSuccess: () => customerNote.reset(),
        });
    }

    function saveTags(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        tags.transform(() => ({
            tags: tagInput
                .split(',')
                .map((tag) => tag.trim())
                .filter(Boolean),
        }));
        tags.put(`/customers/${customer.id}/tags`);
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head
                title={`${customer.first_name} ${customer.last_name ?? ''}`}
            />
            <div className="mx-auto max-w-6xl">
                <Link
                    className="inline-flex items-center gap-2 text-sm text-muted-foreground hover:text-foreground"
                    href="/customers"
                >
                    <ArrowLeft size={16} /> Customers
                </Link>
                <header className="mt-5 flex flex-wrap items-start justify-between gap-4 border-b pb-6">
                    <div className="flex items-center gap-3">
                        <div className="grid size-12 place-items-center rounded-full bg-accent text-accent-foreground">
                            <UserRound size={21} />
                        </div>
                        <div>
                            <h1 className="text-2xl font-semibold tracking-tight">
                                {customer.first_name} {customer.last_name ?? ''}
                            </h1>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {customer.phone}
                                {customer.email ? ` · ${customer.email}` : ''}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {permissions.restore_customer && (
                            <Button
                                onClick={() =>
                                    router.post(
                                        `/customers/${customer.id}/restore`,
                                        {},
                                    )
                                }
                                variant="outline"
                            >
                                Restore customer
                            </Button>
                        )}
                        {permissions.archive &&
                            customer.status === 'active' && (
                                <Button
                                    onClick={() =>
                                        router.delete(
                                            `/customers/${customer.id}`,
                                            {
                                                onBefore: () =>
                                                    window.confirm(
                                                        'Archive this customer? Pending follow-ups may need review.',
                                                    ),
                                            },
                                        )
                                    }
                                    variant="outline"
                                >
                                    Archive customer
                                </Button>
                            )}
                        {permissions.anonymize &&
                            customer.status !== 'anonymized' && (
                                <Button
                                    onClick={() =>
                                        router.post(
                                            `/customers/${customer.id}/anonymize`,
                                            {},
                                            {
                                                onBefore: () =>
                                                    window.confirm(
                                                        'Remove the customer’s personal details and message contents? Consent, visit, and delivery history will remain linked to an anonymized record.',
                                                    ),
                                            },
                                        )
                                    }
                                    className="border-destructive text-destructive hover:bg-destructive/10"
                                    variant="outline"
                                >
                                    Anonymize customer
                                </Button>
                            )}
                        {permissions.permanently_delete && (
                            <Button
                                onClick={() =>
                                    router.delete(
                                        `/customers/${customer.id}/permanent`,
                                        {
                                            onBefore: () =>
                                                window.confirm(
                                                    'Permanently delete this customer record? This is available only when no retained consent or operational history exists.',
                                                ),
                                        },
                                    )
                                }
                                variant="outline"
                            >
                                Delete record
                            </Button>
                        )}
                    </div>
                </header>

                {customer.status === 'archived' && (
                    <section
                        className="mt-5 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                        role="status"
                    >
                        <p className="font-medium">Customer archived</p>
                        <p className="mt-1 text-muted-foreground">
                            Archived customers are excluded from active
                            follow-ups. Restore this record before recording new
                            visits or editing customer details.
                        </p>
                    </section>
                )}

                {customer.status === 'anonymized' && (
                    <section
                        className="mt-5 rounded-xl border border-amber-500/40 bg-amber-500/10 p-4 text-sm"
                        role="status"
                    >
                        <p className="font-medium">Personal data anonymized</p>
                        <p className="mt-1 text-muted-foreground">
                            This record is retained for consent, visit and
                            delivery history. Personal conversation content and
                            customer notes were removed. New customer details
                            and messaging actions are disabled.
                        </p>
                    </section>
                )}

                <div className="mt-6 grid gap-5 lg:grid-cols-2">
                    {permissions.manage_tags && (
                        <section className="rounded-2xl border bg-card p-5 sm:p-6">
                            <h2 className="font-semibold">Customer tags</h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Separate tags with commas. Tags are used for
                                staff organization only.
                            </p>
                            <form
                                className="mt-4 grid gap-3"
                                onSubmit={saveTags}
                            >
                                <InputField
                                    label="Tags"
                                    value={tagInput}
                                    error={tags.errors.tags}
                                    onChange={setTagInput}
                                />
                                <Button
                                    disabled={tags.processing}
                                    type="submit"
                                >
                                    Save tags
                                </Button>
                            </form>
                        </section>
                    )}

                    {permissions.add_note && (
                        <section className="rounded-2xl border bg-card p-5 sm:p-6">
                            <h2 className="font-semibold">Staff notes</h2>
                            <form
                                className="mt-4 grid gap-3"
                                onSubmit={addCustomerNote}
                            >
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Add a private note
                                    <textarea
                                        className="min-h-20 rounded-lg border bg-background px-3 py-2 font-normal"
                                        maxLength={5000}
                                        onChange={(event) =>
                                            customerNote.setData(
                                                'body',
                                                event.target.value,
                                            )
                                        }
                                        value={customerNote.data.body}
                                    />
                                    {customerNote.errors.body && (
                                        <span className="text-xs font-normal text-destructive">
                                            {customerNote.errors.body}
                                        </span>
                                    )}
                                </label>
                                <Button
                                    disabled={customerNote.processing}
                                    type="submit"
                                >
                                    Add note
                                </Button>
                            </form>
                            {customer.note_entries.length === 0 ? (
                                <p className="mt-4 text-sm text-muted-foreground">
                                    No staff notes yet.
                                </p>
                            ) : (
                                <ul className="mt-4 divide-y">
                                    {customer.note_entries.map((note) => (
                                        <li className="py-3" key={note.id}>
                                            <p className="whitespace-pre-wrap text-sm">
                                                {note.body}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {note.author?.name ?? 'Staff'} ·{' '}
                                                {new Date(
                                                    note.created_at,
                                                ).toLocaleString()}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}

                    <section className="rounded-2xl border bg-card p-5 sm:p-6">
                        <div className="flex items-center gap-2">
                            <UserRound size={18} />
                            <h2 className="font-semibold">Customer details</h2>
                        </div>
                        <form
                            className="mt-5 grid gap-3"
                            onSubmit={saveCustomer}
                        >
                            <InputField
                                label="First name"
                                value={update.data.first_name}
                                error={update.errors.first_name}
                                onChange={(value) =>
                                    update.setData('first_name', value)
                                }
                                disabled={!permissions.update}
                            />
                            <InputField
                                label="Last name"
                                value={update.data.last_name}
                                error={update.errors.last_name}
                                onChange={(value) =>
                                    update.setData('last_name', value)
                                }
                                disabled={!permissions.update}
                            />
                            <InputField
                                label="WhatsApp phone"
                                value={update.data.phone}
                                error={update.errors.phone}
                                onChange={(value) =>
                                    update.setData('phone', value)
                                }
                                disabled={!permissions.update}
                            />
                            <InputField
                                label="Email"
                                value={update.data.email}
                                error={update.errors.email}
                                onChange={(value) =>
                                    update.setData('email', value)
                                }
                                type="email"
                                disabled={!permissions.update}
                            />
                            <label className="grid gap-1.5 text-sm font-medium">
                                Stated return date
                                <input
                                    className="h-10 rounded-lg border bg-background px-3 font-normal disabled:opacity-60"
                                    disabled={!permissions.update}
                                    onChange={(event) =>
                                        update.setData(
                                            'intended_return_date',
                                            event.target.value,
                                        )
                                    }
                                    type="date"
                                    value={update.data.intended_return_date}
                                />
                                {update.errors.intended_return_date && (
                                    <span className="text-xs font-normal text-destructive">
                                        {update.errors.intended_return_date}
                                    </span>
                                )}
                                <span className="text-xs font-normal text-muted-foreground">
                                    Customer-reported information only. This
                                    does not reserve a visit time.
                                </span>
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Staff notes
                                <textarea
                                    className="min-h-24 rounded-lg border bg-background px-3 py-2 font-normal"
                                    disabled={
                                        !permissions.update ||
                                        !permissions.edit_notes
                                    }
                                    maxLength={5000}
                                    onChange={(event) =>
                                        update.setData(
                                            'notes',
                                            event.target.value,
                                        )
                                    }
                                    value={update.data.notes}
                                />
                                {update.errors.notes && (
                                    <span className="text-xs font-normal text-destructive">
                                        {update.errors.notes}
                                    </span>
                                )}
                            </label>
                            {permissions.update && (
                                <Button
                                    disabled={update.processing}
                                    type="submit"
                                >
                                    Save changes
                                </Button>
                            )}
                        </form>
                    </section>

                    {permissions.view_consent && (
                        <section className="rounded-2xl border bg-card p-5 sm:p-6">
                            <div className="flex items-center gap-2">
                                <ShieldCheck size={18} />
                                <h2 className="font-semibold">
                                    WhatsApp consent
                                </h2>
                            </div>
                            <div className="mt-4 rounded-lg bg-muted/60 p-3 text-sm">
                                <p className="font-medium">
                                    Current status:{' '}
                                    {latestWhatsAppConsent
                                        ? latestWhatsAppConsent.granted
                                            ? 'Opted in'
                                            : 'Opted out'
                                        : 'No consent recorded'}
                                </p>
                                {latestWhatsAppConsent && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Recorded{' '}
                                        {new Date(
                                            latestWhatsAppConsent.recorded_at,
                                        ).toLocaleString()}{' '}
                                        ·{' '}
                                        {latestWhatsAppConsent.source ??
                                            'source not recorded'}
                                    </p>
                                )}
                                <p className="mt-2 text-xs text-muted-foreground">
                                    Only record consent after the customer has
                                    clearly agreed to WhatsApp messages.
                                </p>
                            </div>
                            {(permissions.record_consent ||
                                permissions.revoke_consent) && (
                                <form
                                    className="mt-4 grid gap-3"
                                    onSubmit={saveConsent}
                                >
                                    <label className="grid gap-1.5 text-sm font-medium">
                                        Consent source
                                        <select
                                            className="h-10 rounded-lg border bg-background px-3"
                                            onChange={(event) =>
                                                consent.setData(
                                                    'source',
                                                    event.target.value,
                                                )
                                            }
                                            value={consent.data.source}
                                        >
                                            <option value="verbal">
                                                Verbal consent
                                            </option>
                                            <option value="written">
                                                Written consent
                                            </option>
                                            <option value="whatsapp">
                                                WhatsApp message
                                            </option>
                                        </select>
                                    </label>
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            checked={consent.data.granted}
                                            className="mt-1"
                                            disabled={
                                                consent.data.granted
                                                    ? !permissions.record_consent
                                                    : !permissions.revoke_consent
                                            }
                                            onChange={(event) =>
                                                consent.setData(
                                                    'granted',
                                                    event.target.checked,
                                                )
                                            }
                                            type="checkbox"
                                        />
                                        <span>
                                            Customer explicitly agreed to
                                            receive WhatsApp messages. Uncheck
                                            to record an opt-out.
                                        </span>
                                    </label>
                                    <Button
                                        disabled={
                                            consent.processing ||
                                            (consent.data.granted
                                                ? !permissions.record_consent
                                                : !permissions.revoke_consent)
                                        }
                                        type="submit"
                                    >
                                        {consent.data.granted
                                            ? 'Record consent'
                                            : 'Record opt-out'}
                                    </Button>
                                </form>
                            )}
                        </section>
                    )}

                    {permissions.record_visit && (
                        <section className="rounded-2xl border bg-card p-5 sm:p-6">
                            <div className="flex items-center gap-2">
                                <CalendarClock size={18} />
                                <h2 className="font-semibold">
                                    Record completed visit
                                </h2>
                            </div>
                            <form
                                className="mt-5 grid gap-3"
                                onSubmit={recordVisit}
                            >
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Visit date and time
                                    <input
                                        className="h-10 rounded-lg border bg-background px-3 font-normal"
                                        max={toBusinessDateTime(
                                            new Date(),
                                            businessTimezone,
                                        )}
                                        onChange={(event) =>
                                            visit.setData(
                                                'visited_at',
                                                event.target.value,
                                            )
                                        }
                                        required
                                        type="datetime-local"
                                        value={visit.data.visited_at}
                                    />
                                    {visit.errors.visited_at && (
                                        <span className="text-xs font-normal text-destructive">
                                            {visit.errors.visited_at}
                                        </span>
                                    )}
                                </label>
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Barber (optional)
                                    <select
                                        className="h-10 rounded-lg border bg-background px-3 font-normal"
                                        onChange={(event) =>
                                            visit.setData(
                                                'staff_profile_id',
                                                event.target.value,
                                            )
                                        }
                                        value={visit.data.staff_profile_id}
                                    >
                                        <option value="">Choose staff</option>
                                        {staff.map((person) => (
                                            <option
                                                key={person.id}
                                                value={person.id}
                                            >
                                                {person.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                {services.length > 0 && (
                                    <fieldset className="grid gap-2 text-sm">
                                        <legend className="font-medium">
                                            Services received (optional)
                                        </legend>
                                        {services.map((service) => (
                                            <label
                                                className="flex items-center gap-2"
                                                key={service.id}
                                            >
                                                <input
                                                    checked={visit.data.service_ids.includes(
                                                        service.id,
                                                    )}
                                                    onChange={(event) =>
                                                        visit.setData(
                                                            'service_ids',
                                                            event.target.checked
                                                                ? [
                                                                      ...visit
                                                                          .data
                                                                          .service_ids,
                                                                      service.id,
                                                                  ]
                                                                : visit.data.service_ids.filter(
                                                                      (id) =>
                                                                          id !==
                                                                          service.id,
                                                                  ),
                                                        )
                                                    }
                                                    type="checkbox"
                                                />
                                                {service.name}
                                            </label>
                                        ))}
                                        {visit.errors.service_ids && (
                                            <span className="text-xs text-destructive">
                                                {visit.errors.service_ids}
                                            </span>
                                        )}
                                    </fieldset>
                                )}
                                <label className="grid gap-1.5 text-sm font-medium">
                                    Visit note
                                    <textarea
                                        className="min-h-20 rounded-lg border bg-background px-3 py-2 font-normal"
                                        onChange={(event) =>
                                            visit.setData(
                                                'notes',
                                                event.target.value,
                                            )
                                        }
                                        value={visit.data.notes}
                                    />
                                    {visit.errors.notes && (
                                        <span className="text-xs font-normal text-destructive">
                                            {visit.errors.notes}
                                        </span>
                                    )}
                                </label>
                                <Button
                                    disabled={visit.processing}
                                    type="submit"
                                >
                                    Record visit
                                </Button>
                            </form>
                        </section>
                    )}

                    {permissions.view_visits && (
                        <section className="rounded-2xl border bg-card p-5 sm:p-6">
                            <div className="flex items-center gap-2">
                                <Scissors size={18} />
                                <h2 className="font-semibold">Visit history</h2>
                            </div>
                            {customer.visits.length === 0 ? (
                                <p className="mt-5 rounded-lg border border-dashed p-6 text-center text-sm text-muted-foreground">
                                    No completed visits recorded.
                                </p>
                            ) : (
                                <ul className="mt-4 divide-y">
                                    {customer.visits.map((item) => (
                                        <VisitHistoryItem
                                            canUpdate={permissions.update_visit}
                                            canVoid={permissions.void_visit}
                                            canRestore={
                                                permissions.restore_visit
                                            }
                                            timezone={businessTimezone}
                                            key={item.id}
                                            services={services}
                                            staff={staff}
                                            visit={item}
                                        />
                                    ))}
                                </ul>
                            )}
                        </section>
                    )}
                </div>
            </div>
        </main>
    );
}

function toBusinessDateTime(date: Date, timezone: string): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(date);
    const part = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((value) => value.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}

function InputField({
    label,
    value,
    error,
    onChange,
    type = 'text',
    disabled = false,
}: {
    label: string;
    value: string;
    error?: string;
    onChange: (value: string) => void;
    type?: string;
    disabled?: boolean;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            <input
                className="h-10 rounded-lg border bg-background px-3 font-normal"
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                type={type}
                value={value}
            />
            {error && (
                <span className="text-xs font-normal text-destructive">
                    {error}
                </span>
            )}
        </label>
    );
}
