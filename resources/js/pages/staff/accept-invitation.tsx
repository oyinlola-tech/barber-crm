import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

export default function AcceptStaffInvitationPage() {
    const [token, setToken] = useState('');
    const [invalid, setInvalid] = useState(false);
    const form = useForm({
        token: '',
    });

    useEffect(() => {
        const invitationToken = window.location.hash.slice(1);
        window.history.replaceState(null, '', window.location.pathname);
        if (/^[A-Za-z0-9]{64}$/.test(invitationToken)) {
            setToken(invitationToken);
            form.setData('token', invitationToken);
        } else {
            setInvalid(true);
        }
        // The invitation fragment is deliberately consumed once and never sent in the GET request.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    function acceptInvitation(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/staff/invitations/accept');
    }

    return (
        <main className="grid place-items-center p-4">
            <Head title="Accept staff invitation" />
            <section className="w-full max-w-md rounded-2xl border bg-card p-6 shadow-sm sm:p-8">
                <p className="text-sm text-muted-foreground">
                    Barber CRM · Staff access
                </p>
                <h1 className="mt-2 text-2xl font-semibold">
                    Activate your account
                </h1>
                <p className="mt-2 text-sm text-muted-foreground">
                    Confirm this one-time link to activate your account. Then
                    sign in with the temporary password from your invitation
                    email and choose a new password.
                </p>
                {invalid ? (
                    <p className="mt-6 rounded-lg bg-destructive/10 p-3 text-sm text-destructive">
                        This invitation link is missing or invalid. Ask the
                        owner to send a new invitation.
                    </p>
                ) : (
                    <form
                        className="mt-6 grid gap-4"
                        onSubmit={acceptInvitation}
                    >
                        {form.errors.token && (
                            <ErrorText text={form.errors.token} />
                        )}
                        <Button
                            disabled={!token || form.processing}
                            type="submit"
                        >
                            {form.processing
                                ? 'Creating account…'
                                : 'Activate account'}
                        </Button>
                    </form>
                )}
            </section>
        </main>
    );
}
function ErrorText({ text }: { text: string }) {
    return <span className="text-xs font-normal text-destructive">{text}</span>;
}
