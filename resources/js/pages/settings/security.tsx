import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

type AccountSession = {
    id: string;
    ip_address: string | null;
    user_agent: string | null;
    last_activity: number;
    is_current: boolean;
};

type SecurityPageProps = {
    twoFactor: { enabled: boolean; pendingConfirmation: boolean };
    passwordConfirmed: boolean;
    sessions: AccountSession[];
    securityNotice?: string;
};

type QrCodeResponse = { svg: string; url: string };

export default function AccountSecurityPage({
    twoFactor,
    passwordConfirmed,
    sessions,
    securityNotice,
}: SecurityPageProps) {
    const [qrCode, setQrCode] = useState<QrCodeResponse | null>(null);
    const [recoveryCodes, setRecoveryCodes] = useState<string[] | null>(null);
    const [loadingCodes, setLoadingCodes] = useState(false);
    const [securityError, setSecurityError] = useState<string | null>(null);
    const enableForm = useForm({});
    const confirmForm = useForm({ code: '' });
    const disableForm = useForm({});
    const revokeOthersForm = useForm({ password: '' });
    const passwordConfirmationForm = useForm({ password: '' });

    useEffect(() => {
        if (!twoFactor.pendingConfirmation) {
            return;
        }

        void fetch('/user/two-factor-qr-code', { credentials: 'same-origin' })
            .then(async (response) => {
                if (!response.ok) {
                    throw new Error(
                        'Could not load the authenticator QR code.',
                    );
                }

                setQrCode((await response.json()) as QrCodeResponse);
            })
            .catch((error: unknown) => {
                setSecurityError(
                    error instanceof Error
                        ? error.message
                        : 'Could not load the QR code.',
                );
            });
    }, [twoFactor.pendingConfirmation]);

    function enableTwoFactor(): void {
        if (!passwordConfirmed) {
            setSecurityError(
                'Confirm your password before setting up two-factor authentication.',
            );
            return;
        }

        setSecurityError(null);
        enableForm.post('/user/two-factor-authentication', {
            onSuccess: () => router.reload({ only: ['twoFactor'] }),
            onError: () =>
                setSecurityError(
                    'Confirm your password, then enable two-factor authentication again.',
                ),
        });
    }

    function confirmTwoFactor(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (!passwordConfirmed) {
            setSecurityError(
                'Confirm your password before finishing two-factor setup.',
            );
            return;
        }
        confirmForm.post('/user/confirmed-two-factor-authentication', {
            onSuccess: () => {
                setQrCode(null);
                router.reload({ only: ['twoFactor'] });
            },
        });
    }

    function disableTwoFactor(): void {
        if (!passwordConfirmed) {
            setSecurityError(
                'Confirm your password before disabling two-factor authentication.',
            );
            return;
        }

        setSecurityError(null);
        disableForm.delete('/user/two-factor-authentication', {
            onSuccess: () => {
                setRecoveryCodes(null);
                router.reload({ only: ['twoFactor'] });
            },
        });
    }

    async function showRecoveryCodes(): Promise<void> {
        if (!passwordConfirmed) {
            setSecurityError(
                'Confirm your password before viewing recovery codes.',
            );
            return;
        }

        setLoadingCodes(true);
        setSecurityError(null);

        try {
            const response = await fetch('/user/two-factor-recovery-codes', {
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error(
                    'Confirm your password before viewing recovery codes.',
                );
            }

            setRecoveryCodes((await response.json()) as string[]);
        } catch (error: unknown) {
            setSecurityError(
                error instanceof Error
                    ? error.message
                    : 'Could not load recovery codes.',
            );
        } finally {
            setLoadingCodes(false);
        }
    }

    function regenerateRecoveryCodes(): void {
        if (!passwordConfirmed) {
            setSecurityError(
                'Confirm your password before generating new recovery codes.',
            );
            return;
        }

        router.post(
            '/user/two-factor-recovery-codes',
            {},
            {
                onSuccess: () => void showRecoveryCodes(),
                onError: () =>
                    setSecurityError(
                        'Confirm your password before generating new recovery codes.',
                    ),
            },
        );
    }

    function revokeOtherSessions(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        revokeOthersForm.delete('/settings/security/sessions', {
            onSuccess: () => revokeOthersForm.reset('password'),
        });
    }

    return (
        <main className="space-y-6 p-5 sm:p-8">
            <Head title="Account security" />
            <header>
                <p className="text-sm font-medium text-muted-foreground">
                    Settings
                </p>
                <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                    Account security
                </h1>
                <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Protect your staff account with an authenticator app and
                    manage active browser sessions.
                </p>
            </header>

            {(securityNotice || securityError) && (
                <div
                    className="rounded-xl border border-amber-300 bg-amber-50 p-4 text-sm text-amber-950 dark:border-amber-900 dark:bg-amber-950/30 dark:text-amber-100"
                    role="status"
                >
                    {securityError ?? securityNotice}
                </div>
            )}

            <section className="rounded-2xl border bg-card p-5 sm:p-6">
                {!passwordConfirmed && (
                    <form
                        className="mb-6 rounded-xl border bg-muted/40 p-4"
                        onSubmit={(event: FormEvent<HTMLFormElement>) => {
                            event.preventDefault();
                            passwordConfirmationForm.post(
                                '/settings/security/confirm-password',
                                {
                                    onSuccess: () =>
                                        passwordConfirmationForm.reset(
                                            'password',
                                        ),
                                },
                            );
                        }}
                    >
                        <h2 className="font-medium">Confirm your password</h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Password confirmation is required before changing
                            two-factor settings.
                        </p>
                        <div className="mt-3 flex max-w-lg flex-wrap gap-2">
                            <label
                                className="sr-only"
                                htmlFor="security-password"
                            >
                                Current password
                            </label>
                            <input
                                autoComplete="current-password"
                                className="h-10 min-w-48 flex-1 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                                id="security-password"
                                onChange={(event) =>
                                    passwordConfirmationForm.setData(
                                        'password',
                                        event.target.value,
                                    )
                                }
                                type="password"
                                value={passwordConfirmationForm.data.password}
                            />
                            <Button
                                disabled={passwordConfirmationForm.processing}
                                type="submit"
                            >
                                {passwordConfirmationForm.processing
                                    ? 'Checking…'
                                    : 'Confirm password'}
                            </Button>
                        </div>
                        {passwordConfirmationForm.errors.password && (
                            <p className="mt-2 text-sm text-destructive">
                                {passwordConfirmationForm.errors.password}
                            </p>
                        )}
                    </form>
                )}
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Two-factor authentication
                        </h2>
                        <p className="mt-1 max-w-xl text-sm text-muted-foreground">
                            {twoFactor.enabled
                                ? 'Your account requires an authenticator code when you sign in.'
                                : 'Use a TOTP authenticator app and keep recovery codes somewhere private.'}
                        </p>
                    </div>
                    <span className="rounded-full border px-3 py-1 text-xs font-medium">
                        {twoFactor.enabled
                            ? 'Enabled'
                            : twoFactor.pendingConfirmation
                              ? 'Setup incomplete'
                              : 'Disabled'}
                    </span>
                </div>

                {!twoFactor.enabled && !twoFactor.pendingConfirmation && (
                    <Button
                        className="mt-5"
                        disabled={enableForm.processing}
                        onClick={enableTwoFactor}
                    >
                        {enableForm.processing
                            ? 'Starting setup…'
                            : 'Set up authenticator'}
                    </Button>
                )}

                {twoFactor.pendingConfirmation && (
                    <div className="mt-5 grid gap-6 md:grid-cols-[192px_1fr]">
                        {qrCode ? (
                            <img
                                alt="Authenticator setup QR code"
                                className="h-48 w-48 rounded-lg border bg-white p-2"
                                src={`data:image/svg+xml;base64,${window.btoa(qrCode.svg)}`}
                            />
                        ) : (
                            <div
                                aria-label="Loading QR code"
                                className="h-48 w-48 animate-pulse rounded-lg bg-muted"
                            />
                        )}
                        <div>
                            <h3 className="font-medium">Scan and confirm</h3>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Scan this code with your authenticator app, then
                                enter its current six-digit code to finish
                                setup.
                            </p>
                            <form
                                className="mt-4 flex max-w-sm flex-wrap gap-3"
                                onSubmit={confirmTwoFactor}
                            >
                                <label className="sr-only" htmlFor="mfa-code">
                                    Authenticator code
                                </label>
                                <input
                                    autoComplete="one-time-code"
                                    className="h-10 min-w-40 flex-1 rounded-lg border bg-background px-3 outline-none focus:ring-2 focus:ring-ring"
                                    id="mfa-code"
                                    inputMode="numeric"
                                    onChange={(event) =>
                                        confirmForm.setData(
                                            'code',
                                            event.target.value,
                                        )
                                    }
                                    value={confirmForm.data.code}
                                />
                                <Button
                                    disabled={confirmForm.processing}
                                    type="submit"
                                >
                                    Confirm
                                </Button>
                            </form>
                            {confirmForm.errors.code && (
                                <p className="mt-2 text-sm text-red-700">
                                    {confirmForm.errors.code}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                {twoFactor.enabled && (
                    <div className="mt-5 flex flex-wrap gap-3">
                        <Button
                            disabled={loadingCodes}
                            onClick={() => void showRecoveryCodes()}
                            variant="outline"
                        >
                            {loadingCodes
                                ? 'Loading codes…'
                                : 'View recovery codes'}
                        </Button>
                        <Button
                            onClick={regenerateRecoveryCodes}
                            variant="outline"
                        >
                            Generate new recovery codes
                        </Button>
                        <Button
                            className="border-destructive text-destructive hover:bg-destructive/10"
                            disabled={disableForm.processing}
                            onClick={disableTwoFactor}
                            variant="outline"
                        >
                            Disable two-factor authentication
                        </Button>
                    </div>
                )}

                {recoveryCodes !== null && (
                    <div className="mt-5 rounded-xl border bg-muted/40 p-4">
                        <h3 className="font-medium">Recovery codes</h3>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Each code can be used once. Generating a new set
                            invalidates the previous set.
                        </p>
                        <ul className="mt-3 grid gap-2 font-mono text-sm sm:grid-cols-2">
                            {recoveryCodes.map((code) => (
                                <li key={code}>{code}</li>
                            ))}
                        </ul>
                    </div>
                )}
            </section>

            <section className="rounded-2xl border bg-card p-5 sm:p-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="text-lg font-semibold">
                            Active sessions
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Sessions are limited to your own account. This
                            browser session cannot be revoked from this list.
                        </p>
                    </div>
                    <form
                        className="flex flex-wrap gap-2"
                        onSubmit={revokeOtherSessions}
                    >
                        <label
                            className="sr-only"
                            htmlFor="confirm-session-password"
                        >
                            Confirm password
                        </label>
                        <input
                            autoComplete="current-password"
                            className="h-10 w-44 rounded-lg border bg-background px-3 text-sm outline-none focus:ring-2 focus:ring-ring"
                            id="confirm-session-password"
                            onChange={(event) =>
                                revokeOthersForm.setData(
                                    'password',
                                    event.target.value,
                                )
                            }
                            placeholder="Confirm password"
                            type="password"
                            value={revokeOthersForm.data.password}
                        />
                        <Button
                            disabled={revokeOthersForm.processing}
                            type="submit"
                            variant="outline"
                        >
                            Revoke other sessions
                        </Button>
                    </form>
                </div>
                {revokeOthersForm.errors.password && (
                    <p className="mt-2 text-sm text-red-700">
                        {revokeOthersForm.errors.password}
                    </p>
                )}
                <ul className="mt-5 divide-y">
                    {sessions.map((session) => (
                        <li
                            className="flex flex-wrap items-center justify-between gap-4 py-4 first:pt-0 last:pb-0"
                            key={session.id}
                        >
                            <div className="min-w-0">
                                <div className="flex items-center gap-2 font-medium">
                                    <span>
                                        {session.is_current
                                            ? 'This browser'
                                            : 'Browser session'}
                                    </span>
                                    {session.is_current && (
                                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs">
                                            Current
                                        </span>
                                    )}
                                </div>
                                <p className="mt-1 break-all text-sm text-muted-foreground">
                                    {[session.user_agent, session.ip_address]
                                        .filter(Boolean)
                                        .join(' · ') ||
                                        'Device details unavailable'}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    Last active{' '}
                                    {new Date(
                                        session.last_activity * 1000,
                                    ).toLocaleString()}
                                </p>
                            </div>
                            {!session.is_current && (
                                <Button
                                    onClick={() =>
                                        router.delete(
                                            `/settings/security/sessions/${encodeURIComponent(session.id)}`,
                                        )
                                    }
                                    variant="outline"
                                >
                                    Revoke
                                </Button>
                            )}
                        </li>
                    ))}
                    {sessions.length === 0 && (
                        <li className="py-4 text-sm text-muted-foreground">
                            No database sessions were found for this account.
                        </li>
                    )}
                </ul>
            </section>
        </main>
    );
}
