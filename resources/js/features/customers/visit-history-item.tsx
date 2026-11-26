import { router, useForm } from '@inertiajs/react';
import { useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type VisitRecord = {
    id: number;
    visited_at: string;
    notes: string | null;
    deleted_at: string | null;
    staff_profile_id: number | null;
    staff?: { name: string } | null;
    services: Array<{ service_id: number | null; service_name: string }>;
};

type VisitOption = { id: number; name: string };

export function VisitHistoryItem({
    visit,
    staff,
    services,
    canUpdate,
    canVoid,
    canRestore,
    timezone,
}: {
    visit: VisitRecord;
    staff: VisitOption[];
    services: VisitOption[];
    canUpdate: boolean;
    canVoid: boolean;
    canRestore: boolean;
    timezone: string;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm({
        staff_profile_id: visit.staff_profile_id?.toString() ?? '',
        visited_at: toBusinessDateTime(visit.visited_at, timezone),
        service_ids: visit.services.flatMap((service) =>
            service.service_id === null ? [] : [service.service_id],
        ),
        notes: visit.notes ?? '',
    });

    function updateVisit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            staff_profile_id: data.staff_profile_id || null,
        }));
        form.put(`/visits/${visit.id}`, {
            onSuccess: () => setEditing(false),
        });
    }

    return (
        <li className="py-4">
            <p className="text-sm font-medium">
                {new Date(visit.visited_at).toLocaleString(undefined, {
                    timeZone: timezone,
                })}
            </p>
            <p className="mt-1 text-sm text-muted-foreground">
                {visit.services
                    .map((service) => service.service_name)
                    .join(', ')}
                {visit.staff ? ` · ${visit.staff.name}` : ''}
            </p>
            {visit.deleted_at && (
                <p className="mt-2 inline-flex rounded-full bg-muted px-2.5 py-1 text-xs font-medium text-muted-foreground">
                    Voided · {new Date(visit.deleted_at).toLocaleDateString()}
                </p>
            )}
            {visit.notes && (
                <p className="mt-2 whitespace-pre-wrap text-sm">
                    {visit.notes}
                </p>
            )}

            {editing && !visit.deleted_at && (
                <form
                    className="mt-4 grid gap-3 rounded-xl border bg-muted/30 p-4"
                    onSubmit={updateVisit}
                >
                    <label className="grid gap-1.5 text-sm font-medium">
                        Completed date and time
                        <input
                            className="h-10 rounded-lg border bg-background px-3 font-normal"
                            max={toBusinessDateTime(new Date(), timezone)}
                            onChange={(event) =>
                                form.setData('visited_at', event.target.value)
                            }
                            required
                            type="datetime-local"
                            value={form.data.visited_at}
                        />
                        {form.errors.visited_at && (
                            <span className="text-xs text-destructive">
                                {form.errors.visited_at}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium">
                        Barber (optional)
                        <select
                            className="h-10 rounded-lg border bg-background px-3 font-normal"
                            onChange={(event) =>
                                form.setData(
                                    'staff_profile_id',
                                    event.target.value,
                                )
                            }
                            value={form.data.staff_profile_id}
                        >
                            <option value="">Choose staff</option>
                            {staff.map((person) => (
                                <option key={person.id} value={person.id}>
                                    {person.name}
                                </option>
                            ))}
                        </select>
                    </label>
                    {services.length > 0 && (
                        <fieldset className="grid gap-2 text-sm">
                            <legend className="font-medium">
                                Services received
                            </legend>
                            {services.map((service) => (
                                <label
                                    className="flex items-center gap-2"
                                    key={service.id}
                                >
                                    <input
                                        checked={form.data.service_ids.includes(
                                            service.id,
                                        )}
                                        onChange={(event) =>
                                            form.setData(
                                                'service_ids',
                                                event.target.checked
                                                    ? [
                                                          ...form.data
                                                              .service_ids,
                                                          service.id,
                                                      ]
                                                    : form.data.service_ids.filter(
                                                          (id) =>
                                                              id !== service.id,
                                                      ),
                                            )
                                        }
                                        type="checkbox"
                                    />
                                    {service.name}
                                </label>
                            ))}
                            {form.errors.service_ids && (
                                <span className="text-xs text-destructive">
                                    {form.errors.service_ids}
                                </span>
                            )}
                        </fieldset>
                    )}
                    <label className="grid gap-1.5 text-sm font-medium">
                        Visit note
                        <textarea
                            className="min-h-20 rounded-lg border bg-background px-3 py-2 font-normal"
                            maxLength={2000}
                            onChange={(event) =>
                                form.setData('notes', event.target.value)
                            }
                            value={form.data.notes}
                        />
                        {form.errors.notes && (
                            <span className="text-xs text-destructive">
                                {form.errors.notes}
                            </span>
                        )}
                    </label>
                    <p className="text-xs text-muted-foreground">
                        Saving a correction cancels pending visit follow-ups and
                        schedules them again using the corrected details.
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button disabled={form.processing} type="submit">
                            Save correction
                        </Button>
                        <Button
                            onClick={() => setEditing(false)}
                            type="button"
                            variant="outline"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            )}

            {(canUpdate || canVoid || canRestore) && (
                <div className="mt-3 flex gap-2">
                    {canUpdate && !editing && !visit.deleted_at && (
                        <Button
                            onClick={() => setEditing(true)}
                            size="sm"
                            type="button"
                            variant="outline"
                        >
                            Correct visit
                        </Button>
                    )}
                    {canVoid && !visit.deleted_at && (
                        <Button
                            className="text-destructive"
                            onClick={() =>
                                router.delete(`/visits/${visit.id}`, {
                                    onBefore: () =>
                                        window.confirm(
                                            'Void this visit? It will be removed from active visit history and its pending follow-ups will be cancelled. The audit record will remain.',
                                        ),
                                })
                            }
                            size="sm"
                            type="button"
                            variant="outline"
                        >
                            Void visit
                        </Button>
                    )}
                    {canRestore && visit.deleted_at && (
                        <Button
                            onClick={() =>
                                router.post(`/visits/${visit.id}/restore`)
                            }
                            size="sm"
                            type="button"
                            variant="outline"
                        >
                            Restore visit
                        </Button>
                    )}
                </div>
            )}
        </li>
    );
}

function toBusinessDateTime(value: string | Date, timezone: string): string {
    const parts = new Intl.DateTimeFormat('en-CA', {
        timeZone: timezone,
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        hourCycle: 'h23',
    }).formatToParts(new Date(value));
    const part = (type: Intl.DateTimeFormatPartTypes) =>
        parts.find((item) => item.type === type)?.value ?? '';

    return `${part('year')}-${part('month')}-${part('day')}T${part('hour')}:${part('minute')}`;
}
