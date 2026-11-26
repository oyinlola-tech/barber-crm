import { CheckCircle2, CircleAlert, MessageCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import type { WhatsAppConnection } from './connection-types';

export function ConnectionStatusCard({
    connection,
    canDisconnect,
    onDisconnect,
}: {
    connection: WhatsAppConnection | null;
    canDisconnect: boolean;
    onDisconnect: () => void;
}) {
    const connected = connection?.status === 'connected';

    return (
        <section
            className="rounded-2xl border bg-card p-5 sm:p-6"
            aria-labelledby="connection-status"
        >
            <div className="flex flex-wrap items-start justify-between gap-4">
                <div className="flex items-start gap-3">
                    <span className="grid size-10 shrink-0 place-items-center rounded-xl bg-primary/10 text-primary">
                        <MessageCircle aria-hidden="true" size={20} />
                    </span>
                    <div>
                        <h2 id="connection-status" className="font-semibold">
                            Connection status
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {connected
                                ? 'Meta verified access to the configured WhatsApp account.'
                                : 'No verified WhatsApp account is connected.'}
                        </p>
                    </div>
                </div>
                <span
                    className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium ${connected ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-200' : 'bg-muted text-muted-foreground'}`}
                >
                    {connected ? (
                        <CheckCircle2 aria-hidden="true" size={15} />
                    ) : (
                        <CircleAlert aria-hidden="true" size={15} />
                    )}
                    {connected ? 'Connected' : 'Not connected'}
                </span>
            </div>

            {connection && (
                <dl className="mt-5 grid gap-4 border-t pt-5 text-sm sm:grid-cols-2">
                    <div>
                        <dt className="text-muted-foreground">
                            Phone number ID
                        </dt>
                        <dd className="mt-1 font-medium">
                            {connection.phone_number_id}
                        </dd>
                    </div>
                    <div>
                        <dt className="text-muted-foreground">
                            WhatsApp Business Account ID
                        </dt>
                        <dd className="mt-1 font-medium">
                            {connection.business_account_id}
                        </dd>
                    </div>
                    {connection.connected_at && (
                        <div>
                            <dt className="text-muted-foreground">
                                Verified on
                            </dt>
                            <dd className="mt-1 font-medium">
                                {new Date(
                                    connection.connected_at,
                                ).toLocaleString()}
                            </dd>
                        </div>
                    )}
                </dl>
            )}

            {canDisconnect && connected && (
                <div className="mt-5 border-t pt-5">
                    <Button
                        onClick={onDisconnect}
                        type="button"
                        variant="outline"
                    >
                        Disconnect WhatsApp
                    </Button>
                </div>
            )}
        </section>
    );
}
