import { useForm } from '@inertiajs/react';
import { ShieldCheck } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import type {
    WhatsAppConnection,
    WhatsAppCredentials,
} from './connection-types';

export function ConnectionCredentialsForm({
    connection,
    apiVersionConfigured,
}: {
    connection: WhatsAppConnection | null;
    apiVersionConfigured: boolean;
}) {
    const form = useForm<WhatsAppCredentials>({
        phone_number_id: connection?.phone_number_id ?? '',
        business_account_id: connection?.business_account_id ?? '',
        access_token: '',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.put('/settings/whatsapp', {
            preserveScroll: true,
            onSuccess: () => form.reset('access_token'),
        });
    }

    return (
        <section className="rounded-2xl border bg-card p-5 sm:p-6">
            <div className="flex items-start gap-3">
                <ShieldCheck
                    aria-hidden="true"
                    className="mt-0.5 text-primary"
                    size={19}
                />
                <div>
                    <h2 className="font-semibold">
                        {connection
                            ? 'Update credentials'
                            : 'Connect an account'}
                    </h2>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Credentials are checked with Meta before they are saved.
                        The access token is encrypted at rest and is never shown
                        again.
                    </p>
                </div>
            </div>

            {!apiVersionConfigured && (
                <p
                    className="mt-5 rounded-xl border border-amber-300 bg-amber-50 p-3 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
                    role="alert"
                >
                    Configure <code>WHATSAPP_API_VERSION</code> on the server
                    before connecting an account.
                </p>
            )}

            <form className="mt-5 grid gap-4 sm:grid-cols-2" onSubmit={submit}>
                <CredentialField
                    label="Phone number ID"
                    value={form.data.phone_number_id}
                    error={form.errors.phone_number_id}
                    disabled={form.processing || !apiVersionConfigured}
                    onChange={(value) => form.setData('phone_number_id', value)}
                    inputMode="numeric"
                />
                <CredentialField
                    label="WhatsApp Business Account ID"
                    value={form.data.business_account_id}
                    error={form.errors.business_account_id}
                    disabled={form.processing || !apiVersionConfigured}
                    onChange={(value) =>
                        form.setData('business_account_id', value)
                    }
                    inputMode="numeric"
                />
                <CredentialField
                    label="System user access token"
                    value={form.data.access_token}
                    error={form.errors.access_token}
                    disabled={form.processing || !apiVersionConfigured}
                    onChange={(value) => form.setData('access_token', value)}
                    type="password"
                    autoComplete="new-password"
                    className="sm:col-span-2"
                />
                <div className="flex items-center gap-3 sm:col-span-2">
                    <Button
                        disabled={form.processing || !apiVersionConfigured}
                        type="submit"
                    >
                        {form.processing
                            ? 'Verifying with Meta…'
                            : 'Verify and save connection'}
                    </Button>
                    {form.hasErrors && (
                        <p className="text-sm text-destructive" role="alert">
                            Correct the fields above and try again.
                        </p>
                    )}
                </div>
            </form>
        </section>
    );
}

function CredentialField({
    label,
    value,
    error,
    disabled,
    onChange,
    type = 'text',
    autoComplete,
    inputMode,
    className = '',
}: {
    label: string;
    value: string;
    error?: string;
    disabled: boolean;
    onChange: (value: string) => void;
    type?: string;
    autoComplete?: string;
    inputMode?: 'numeric';
    className?: string;
}) {
    return (
        <label className={`grid gap-1.5 text-sm font-medium ${className}`}>
            {label}
            <input
                autoComplete={autoComplete}
                className="h-10 rounded-lg border bg-background px-3 font-normal disabled:opacity-60"
                disabled={disabled}
                inputMode={inputMode}
                onChange={(event) => onChange(event.target.value)}
                type={type}
                value={value}
            />
            {error && (
                <span
                    className="text-xs font-normal text-destructive"
                    role="alert"
                >
                    {error}
                </span>
            )}
        </label>
    );
}
