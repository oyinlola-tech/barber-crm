import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';

export default function TwoFactorChallengePage() {
    const [useRecoveryCode, setUseRecoveryCode] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/two-factor-challenge');
    }

    return (
        <main className="flex min-h-screen items-center justify-center px-5 py-12">
            <Head title="Two-factor verification" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm sm:p-10">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Verify your sign-in
                </h1>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    {useRecoveryCode
                        ? 'Enter one of your unused recovery codes.'
                        : 'Enter the six-digit code from your authenticator app.'}
                </p>
                <form className="mt-7 space-y-5" onSubmit={submit}>
                    <label className="block space-y-2 text-sm font-medium">
                        {useRecoveryCode
                            ? 'Recovery code'
                            : 'Authentication code'}
                        <input
                            autoComplete={
                                useRecoveryCode
                                    ? 'one-time-code'
                                    : 'one-time-code'
                            }
                            autoFocus
                            className="h-11 w-full rounded-lg border bg-background px-3 font-normal outline-none focus:ring-2 focus:ring-ring"
                            inputMode={useRecoveryCode ? 'text' : 'numeric'}
                            onChange={(event) =>
                                form.setData(
                                    useRecoveryCode ? 'recovery_code' : 'code',
                                    event.target.value,
                                )
                            }
                            value={
                                useRecoveryCode
                                    ? form.data.recovery_code
                                    : form.data.code
                            }
                        />
                        {(form.errors.code || form.errors.recovery_code) && (
                            <span className="block text-xs font-normal text-red-700">
                                {form.errors.code || form.errors.recovery_code}
                            </span>
                        )}
                    </label>
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Verifying…' : 'Continue'}
                    </Button>
                </form>
                <button
                    className="mt-5 w-full text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    onClick={() => {
                        setUseRecoveryCode(!useRecoveryCode);
                        form.clearErrors();
                    }}
                    type="button"
                >
                    {useRecoveryCode
                        ? 'Use an authenticator code instead'
                        : 'Use a recovery code instead'}
                </button>
                <p className="mt-5 text-center text-sm text-muted-foreground">
                    <Link className="hover:text-foreground" href="/login">
                        Back to sign in
                    </Link>
                </p>
            </section>
        </main>
    );
}
