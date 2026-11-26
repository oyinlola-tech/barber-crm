import { Head, Link, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

export default function LoginPage() {
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/login');
    }

    return (
        <main className="flex min-h-screen items-center justify-center px-5 py-12">
            <Head title="Sign in" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-8 shadow-sm sm:p-10">
                <div className="mb-8 flex items-center gap-3">
                    <div className="grid size-11 place-items-center rounded-xl bg-primary text-sm font-bold text-primary-foreground">
                        B
                    </div>
                    <div>
                        <p className="font-semibold tracking-tight">
                            Barber CRM
                        </p>
                        <p className="text-sm text-muted-foreground">
                            Your shop, in good order.
                        </p>
                    </div>
                </div>
                <div className="mb-7 space-y-2">
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Welcome back
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Sign in to manage customer relationships.
                    </p>
                </div>
                <form className="space-y-5" onSubmit={submit}>
                    <label className="block space-y-2 text-sm font-medium">
                        Email address
                        <input
                            autoComplete="email"
                            autoFocus
                            className="h-11 w-full rounded-lg border bg-background px-3 font-normal outline-none transition focus:ring-2 focus:ring-ring"
                            name="email"
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
                    <label className="block space-y-2 text-sm font-medium">
                        Password
                        <input
                            autoComplete="current-password"
                            className="h-11 w-full rounded-lg border bg-background px-3 font-normal outline-none transition focus:ring-2 focus:ring-ring"
                            name="password"
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
                    <div className="-mt-2 flex justify-end">
                        <Link
                            className="text-xs text-muted-foreground hover:text-foreground"
                            href="/forgot-password"
                        >
                            Forgot password?
                        </Link>
                    </div>
                    <label className="flex items-center gap-2 text-sm text-muted-foreground">
                        <input
                            checked={form.data.remember}
                            className="size-4 rounded border-input accent-primary"
                            onChange={(event) =>
                                form.setData('remember', event.target.checked)
                            }
                            type="checkbox"
                        />
                        Keep me signed in
                    </label>
                    <Button
                        className="w-full"
                        disabled={form.processing}
                        type="submit"
                    >
                        {form.processing ? 'Signing in…' : 'Sign in'}
                    </Button>
                </form>
            </section>
        </main>
    );
}
