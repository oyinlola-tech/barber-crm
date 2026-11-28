import { useForm } from '@inertiajs/react';
import { MessageSquareText } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

export function MessagingSettingsForm({
    keywords,
    features,
    canUpdate,
}: {
    keywords: string[];
    features: Record<string, boolean>;
    canUpdate: boolean;
}) {
    const form = useForm({
        opt_out_keywords: keywords.join('\n'),
        features,
    });
    const featureLabels: Record<
        string,
        { label: string; description: string }
    > = {
        outbound: {
            label: 'All outgoing WhatsApp messages',
            description: 'Master switch for every message this CRM sends.',
        },
        manual_replies: {
            label: 'Staff replies and manual templates',
            description:
                'Pause one-to-one staff replies and manually selected templates.',
        },
        thank_you: {
            label: 'Visit thank-you messages',
            description: 'Messages sent after a completed visit.',
        },
        experience_follow_up: {
            label: 'Experience follow-ups',
            description: 'Messages asking how the customer’s visit went.',
        },
        review_requests: {
            label: 'Review requests',
            description: 'Messages asking for customer feedback or a review.',
        },
        return_reminders: {
            label: 'Return reminders',
            description: 'Messages about a customer’s next intended visit.',
        },
        inactive_reengagement: {
            label: 'Inactive customer follow-ups',
            description: 'Messages inviting inactive customers to return.',
        },
        missed_visit_follow_up: {
            label: 'Missed return follow-ups',
            description:
                'Follow-ups after staff mark a customer-stated return date as missed.',
        },
    };
    const indexedError = (form.errors as Record<string, string | undefined>)[
        'opt_out_keywords.0'
    ];

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({
            opt_out_keywords: data.opt_out_keywords
                .split(/\r?\n/)
                .map((keyword) => keyword.trim())
                .filter((keyword) => keyword.length > 0),
            features: data.features,
        }));
        form.put('/settings/messaging', {
            preserveScroll: true,
        });
    }

    return (
        <section className="mt-6 rounded-2xl border bg-card p-5 sm:p-7">
            <div className="flex items-center gap-2">
                <MessageSquareText aria-hidden="true" size={18} />
                <h2 className="font-semibold">WhatsApp messaging rules</h2>
            </div>
            <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                A customer message that matches one of these phrases will
                withdraw WhatsApp consent and cancel eligible pending
                follow-ups. Standard opt-out words remain enabled; add other
                phrases the barbershop recognizes. Matching ignores letter case
                and surrounding punctuation.
            </p>
            <form className="mt-5 grid gap-4" onSubmit={submit}>
                <fieldset className="grid gap-2">
                    <legend className="mb-1 text-sm font-medium">
                        Outgoing message controls
                    </legend>
                    {Object.entries(featureLabels).map(([key, feature]) => (
                        <div
                            className="flex items-start justify-between gap-4 rounded-xl border p-3 sm:p-4"
                            key={key}
                        >
                            <div className="min-w-0">
                                <p className="text-sm font-medium">
                                    {feature.label}
                                </p>
                                <p className="mt-1 text-xs leading-5 text-muted-foreground">
                                    {feature.description}
                                </p>
                            </div>
                            <button
                                aria-checked={form.data.features[key] ?? false}
                                aria-label={feature.label}
                                className={`relative mt-0.5 inline-flex h-6 w-11 shrink-0 items-center rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 ${form.data.features[key] ? 'bg-primary' : 'bg-muted-foreground/35'}`}
                                disabled={!canUpdate || form.processing}
                                onClick={() =>
                                    form.setData('features', {
                                        ...form.data.features,
                                        [key]: !form.data.features[key],
                                    })
                                }
                                role="switch"
                                type="button"
                            >
                                <span
                                    className={`inline-block size-5 rounded-full bg-white shadow transition-transform ${form.data.features[key] ? 'translate-x-5' : 'translate-x-0.5'}`}
                                />
                            </button>
                        </div>
                    ))}
                    <p className="text-xs leading-5 text-muted-foreground">
                        Turning a switch off prevents that type of message from
                        being scheduled or sent. The master switch overrides
                        every outgoing WhatsApp feature. Incoming customer
                        messages remain visible in the inbox.
                    </p>
                    {form.errors.features && (
                        <p className="text-xs text-destructive">
                            {form.errors.features}
                        </p>
                    )}
                </fieldset>
                <label className="grid gap-1.5 text-sm font-medium">
                    Additional customer opt-out phrases
                    <textarea
                        aria-describedby="opt-out-keywords-help"
                        className="min-h-32 rounded-lg border bg-background px-3 py-2 font-normal disabled:opacity-60"
                        disabled={!canUpdate}
                        onChange={(event) =>
                            form.setData('opt_out_keywords', event.target.value)
                        }
                        value={form.data.opt_out_keywords}
                    />
                    <span
                        className="text-xs font-normal text-muted-foreground"
                        id="opt-out-keywords-help"
                    >
                        Enter one phrase per line, with up to 20 additional
                        phrases.
                    </span>
                    {form.errors.opt_out_keywords && (
                        <span className="text-xs font-normal text-destructive">
                            {form.errors.opt_out_keywords}
                        </span>
                    )}
                    {indexedError && (
                        <span className="text-xs font-normal text-destructive">
                            {indexedError}
                        </span>
                    )}
                </label>
                {canUpdate ? (
                    <div>
                        <Button disabled={form.processing} type="submit">
                            Save messaging rules
                        </Button>
                        {form.recentlySuccessful && (
                            <p
                                aria-live="polite"
                                className="mt-2 text-sm text-emerald-700 dark:text-emerald-300"
                                role="status"
                            >
                                WhatsApp messaging rules saved.
                            </p>
                        )}
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        You have read-only access to messaging settings.
                    </p>
                )}
            </form>
        </section>
    );
}
