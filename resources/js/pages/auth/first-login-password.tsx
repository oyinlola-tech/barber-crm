import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type FirstLoginPasswordProps = {
    expiresAt: string | null;
};

export default function FirstLoginPasswordPage({
    expiresAt,
}: FirstLoginPasswordProps) {
    const form = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put('/first-login/password');
    }

    const expiryLabel = expiresAt
        ? new Intl.DateTimeFormat(undefined, {
              dateStyle: 'medium',
              timeStyle: 'short',
          }).format(new Date(expiresAt))
        : null;

    return (
        <main className="flex items-center justify-center px-5 py-12">
            <Head title="Choose a new password" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm sm:p-10">
                <div className="mb-7 space-y-2">
                    <p className="text-xs font-semibold uppercase tracking-[0.16em] text-primary">
                        Secure your account
                    </p>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Choose a new password
                    </h1>
                    <p className="text-sm leading-6 text-muted-foreground">
                        Your temporary sign-in password can only be used to
                        reach this step. Set a private password to continue.
                    </p>
                    {expiryLabel && (
                        <p className="text-xs text-muted-foreground">
                            Temporary password expires {expiryLabel}.
                        </p>
                    )}
                </div>
                <form className="space-y-5" onSubmit={submit}>
                    <PasswordField
                        autoComplete="current-password"
                        error={form.errors.current_password}
                        label="Temporary password"
                        onChange={(value) =>
                            form.setData('current_password', value)
                        }
                        value={form.data.current_password}
                    />
                    <PasswordField
                        autoComplete="new-password"
                        error={form.errors.password}
                        label="New password"
                        onChange={(value) => form.setData('password', value)}
                        value={form.data.password}
                    />
                    <PasswordField
                        autoComplete="new-password"
                        error={form.errors.password_confirmation}
                        label="Confirm new password"
                        onChange={(value) =>
                            form.setData('password_confirmation', value)
                        }
                        value={form.data.password_confirmation}
                    />
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing
                            ? 'Updating password…'
                            : 'Set password'}
                    </Button>
                </form>
            </section>
        </main>
    );
}

type PasswordFieldProps = {
    autoComplete: 'current-password' | 'new-password';
    error?: string;
    label: string;
    onChange: (value: string) => void;
    value: string;
};

function PasswordField({
    autoComplete,
    error,
    label,
    onChange,
    value,
}: PasswordFieldProps) {
    return (
        <label className="block space-y-2 text-sm font-medium">
            {label}
            <input
                autoComplete={autoComplete}
                className="h-11 w-full rounded-lg border bg-background px-3 font-normal outline-none transition focus:ring-2 focus:ring-ring"
                minLength={12}
                onChange={(event) => onChange(event.target.value)}
                required
                type="password"
                value={value}
            />
            {error && (
                <span className="block text-xs font-normal text-destructive">
                    {error}
                </span>
            )}
        </label>
    );
}
