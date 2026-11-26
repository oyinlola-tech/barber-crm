import { Head, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

export default function ConfirmPasswordPage() {
    const form = useForm({ password: '' });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/user/confirm-password', {
            onSuccess: () => form.reset('password'),
        });
    }

    return (
        <main className="flex min-h-screen items-center justify-center px-5 py-12">
            <Head title="Confirm your password" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm sm:p-10">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Confirm your password
                </h1>
                <p className="mt-2 text-sm leading-6 text-muted-foreground">
                    For your security, confirm your password before changing
                    authentication settings.
                </p>
                <form className="mt-7 space-y-5" onSubmit={submit}>
                    <label className="block space-y-2 text-sm font-medium">
                        Password
                        <input
                            autoComplete="current-password"
                            autoFocus
                            className="h-11 w-full rounded-lg border bg-background px-3 font-normal outline-none focus:ring-2 focus:ring-ring"
                            onChange={(event) =>
                                form.setData('password', event.target.value)
                            }
                            type="password"
                            value={form.data.password}
                        />
                        {form.errors.password && (
                            <span className="block text-xs font-normal text-red-700">
                                {form.errors.password}
                            </span>
                        )}
                    </label>
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Checking…' : 'Confirm password'}
                    </Button>
                </form>
            </section>
        </main>
    );
}
