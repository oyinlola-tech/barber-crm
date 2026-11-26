import { Head, router } from '@inertiajs/react';
import { ConnectionCredentialsForm } from '@/features/whatsapp/connection-credentials-form';
import { ConnectionStatusCard } from '@/features/whatsapp/connection-status-card';
import type { WhatsAppConnection } from '@/features/whatsapp/connection-types';

export default function WhatsAppConnectionPage({
    connection,
    canConfigure,
    canDisconnect,
    apiVersionConfigured,
}: {
    connection: WhatsAppConnection | null;
    canConfigure: boolean;
    canDisconnect: boolean;
    apiVersionConfigured: boolean;
}) {
    function disconnect(): void {
        if (
            !window.confirm(
                'Disconnect the barbershop WhatsApp account? Automated messages will stop.',
            )
        ) {
            return;
        }

        router.delete('/settings/whatsapp', { preserveScroll: true });
    }

    return (
        <main className="space-y-6 p-5 sm:p-8">
            <Head title="WhatsApp integration" />
            <header>
                <p className="text-sm font-medium text-muted-foreground">
                    Settings
                </p>
                <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                    WhatsApp integration
                </h1>
                <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                    Connect the barbershop’s WhatsApp Business Platform account
                    for customer conversations and consent-based follow-ups.
                </p>
            </header>

            <ConnectionStatusCard
                connection={connection}
                canDisconnect={canDisconnect}
                onDisconnect={disconnect}
            />

            {canConfigure ? (
                <ConnectionCredentialsForm
                    connection={connection}
                    apiVersionConfigured={apiVersionConfigured}
                />
            ) : (
                <p className="rounded-xl border bg-card p-4 text-sm text-muted-foreground">
                    You can view the connection status, but do not have
                    permission to change integration credentials.
                </p>
            )}
        </main>
    );
}
