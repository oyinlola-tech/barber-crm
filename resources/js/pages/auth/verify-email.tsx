import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type VerifyEmailPageProps = { status?: string };

export default function VerifyEmailPage({ status }: VerifyEmailPageProps) {
    const form = useForm<{ email: string }>({ email: '' });

    function resend(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.post('/email/verification-notification');
    }

    return (
        <main className="flex items-center justify-center px-5 py-12">
            <Head title="Verify your email" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm sm:p-10">
                <p className="text-sm font-medium text-primary">
                    ACCOUNT SECURITY
                </p>
                <h1 className="mt-2 text-2xl font-semibold tracking-tight">
                    Verify your email
                </h1>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    Follow the verification link sent to your mailbox before
                    using the barbershop CRM.
                </p>
                {status && (
                    <p
                        className="mt-4 rounded-lg bg-accent p-3 text-sm text-accent-foreground"
                        role="status"
                    >
                        {status}
                    </p>
                )}
                <form className="mt-7" onSubmit={resend}>
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing
                            ? 'Sending…'
                            : 'Resend verification email'}
                    </Button>
                    {form.errors.email && (
                        <p className="mt-3 text-sm text-destructive">
                            {form.errors.email}
                        </p>
                    )}
                </form>
                <button
                    className="mt-4 w-full text-center text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
                    onClick={() => router.post('/logout')}
                    type="button"
                >
                    Sign out
                </button>
            </section>
        </main>
    );
}
