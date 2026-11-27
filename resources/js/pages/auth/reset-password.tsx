import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type ResetPasswordProps = {
    email: string;
    token: string;
};

export default function ResetPasswordPage({
    email,
    token,
}: ResetPasswordProps) {
    const form = useForm({
        email,
        password: '',
        password_confirmation: '',
        token,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/reset-password');
    }

    return (
        <main className="flex items-center justify-center px-5 py-12">
            <Head title="Choose a new password" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm sm:p-10">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Choose a new password
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Set a new password for {email}.
                </p>
                <form className="mt-7 space-y-5" onSubmit={submit}>
                    <label className="block space-y-2 text-sm font-medium">
                        New password
                        <input
                            autoComplete="new-password"
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
                    <label className="block space-y-2 text-sm font-medium">
                        Confirm password
                        <input
                            autoComplete="new-password"
                            className="h-11 w-full rounded-lg border bg-background px-3 font-normal outline-none focus:ring-2 focus:ring-ring"
                            onChange={(event) =>
                                form.setData(
                                    'password_confirmation',
                                    event.target.value,
                                )
                            }
                            type="password"
                            value={form.data.password_confirmation}
                        />
                    </label>
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        Update password
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
