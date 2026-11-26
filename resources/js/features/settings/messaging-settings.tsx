import { useForm } from '@inertiajs/react';
import { MessageSquareText } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

export function MessagingSettingsForm({
    keywords,
    canUpdate,
}: {
    keywords: string[];
    canUpdate: boolean;
}) {
    const form = useForm({ opt_out_keywords: keywords.join('\n') });
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
