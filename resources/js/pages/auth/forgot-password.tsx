import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type ForgotPasswordProps = {
    status?: string;
};

export default function ForgotPasswordPage({ status }: ForgotPasswordProps) {
    const form = useForm({ email: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <main className="flex min-h-screen items-center justify-center px-5 py-12">
            <Head title="Reset your password" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm sm:p-10">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Reset your password
                </h1>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    Enter the email address on your account and we’ll send a
                    reset link.
                </p>
                {status && (
                    <p className="mt-4 rounded-lg bg-accent p-3 text-sm text-accent-foreground">
                        {status}
                    </p>
                )}
                <form className="mt-7 space-y-5" onSubmit={submit}>
                    <label className="block space-y-2 text-sm font-medium">
                        Email address
                        <input
                            autoComplete="email"
                            autoFocus
                            className="h-11 w-full rounded-lg border bg-background px-3 font-normal outline-none focus:ring-2 focus:ring-ring"
                            onChange={(event) =>
                                form.setData('email', event.target.value)
                            }
                            type="email"
                            value={form.data.email}
                        />
                        {form.errors.email && (
                            <span className="block text-xs font-normal text-red-700">
                                {form.errors.email}
                            </span>
                        )}
                    </label>
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        Send reset link
                    </Button>
                    <p className="text-center text-sm text-muted-foreground">
                        <Link className="hover:text-foreground" href="/login">
                            Back to sign in
                        </Link>
                    </p>
                </form>
            </section>
        </main>
    );
}
