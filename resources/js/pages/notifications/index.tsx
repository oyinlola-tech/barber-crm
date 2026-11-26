import { Head, Link, router } from '@inertiajs/react';
import { Bell, CheckCheck, ExternalLink } from 'lucide-react';

type UserNotification = {
    id: string;
    title: string;
    message: string;
    url: string | null;
    created_at: string | null;
    read_at: string | null;
};

export default function NotificationsPage({
    notifications,
    unreadCount,
}: {
    notifications: UserNotification[];
    unreadCount: number;
}) {
    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Notifications" />
            <div className="mx-auto max-w-4xl">
                <Link
                    className="text-sm text-muted-foreground hover:text-foreground"
                    href="/dashboard"
                >
                    ← Dashboard
                </Link>
                <header className="mb-7 mt-5 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            ACTIVITY
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                            Notifications
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            {unreadCount.toLocaleString()} unread notification
                            {unreadCount === 1 ? '' : 's'}.
                        </p>
                    </div>
                    {unreadCount > 0 && (
                        <button
                            className="inline-flex h-10 items-center gap-2 rounded-lg border bg-card px-3 text-sm hover:bg-muted"
                            onClick={() =>
                                router.post(
                                    '/notifications/read-all',
                                    {},
                                    {
                                        preserveScroll: true,
                                    },
                                )
                            }
                            type="button"
                        >
                            <CheckCheck aria-hidden="true" size={16} />
                            Mark all as read
                        </button>
                    )}
                </header>
                <section
                    aria-label="Your notifications"
                    className="overflow-hidden rounded-2xl border bg-card"
                >
                    {notifications.length === 0 ? (
                        <div className="p-12 text-center">
                            <Bell
                                aria-hidden="true"
                                className="mx-auto text-muted-foreground"
                                size={28}
                            />
                            <p className="mt-3 font-medium">
                                You’re all caught up
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                New activity that needs your attention will
                                appear here.
                            </p>
                        </div>
                    ) : (
                        <ul className="divide-y">
                            {notifications.map((notification) => (
                                <li
                                    className={`flex flex-wrap items-start justify-between gap-4 p-4 sm:p-5 ${notification.read_at === null ? 'bg-primary/[0.035]' : ''}`}
                                    key={notification.id}
                                >
                                    <div className="flex min-w-0 gap-3">
                                        <span
                                            aria-hidden="true"
                                            className={`mt-1 size-2 shrink-0 rounded-full ${notification.read_at === null ? 'bg-primary' : 'bg-muted-foreground/30'}`}
                                        />
                                        <div className="min-w-0">
                                            <h2 className="font-medium">
                                                {notification.title}
                                            </h2>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {notification.message}
                                            </p>
                                            <time
                                                className="mt-2 block text-xs text-muted-foreground"
                                                dateTime={
                                                    notification.created_at ??
                                                    undefined
                                                }
                                            >
                                                {notification.created_at
                                                    ? new Intl.DateTimeFormat(
                                                          undefined,
                                                          {
                                                              dateStyle:
                                                                  'medium',
                                                              timeStyle:
                                                                  'short',
                                                          },
                                                      ).format(
                                                          new Date(
                                                              notification.created_at,
                                                          ),
                                                      )
                                                    : 'Time unavailable'}
                                            </time>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-3 pl-5 sm:pl-0">
                                        {notification.url !== null && (
                                            <Link
                                                className="inline-flex items-center gap-1.5 text-sm font-medium text-primary hover:underline"
                                                href={notification.url}
                                            >
                                                Open
                                                <ExternalLink
                                                    aria-hidden="true"
                                                    size={14}
                                                />
                                            </Link>
                                        )}
                                        {notification.read_at === null && (
                                            <button
                                                className="text-sm text-muted-foreground hover:text-foreground"
                                                onClick={() =>
                                                    router.post(
                                                        `/notifications/${notification.id}/read`,
                                                        {},
                                                        {
                                                            preserveScroll: true,
                                                        },
                                                    )
                                                }
                                                type="button"
                                            >
                                                Mark read
                                            </button>
                                        )}
                                    </div>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </main>
    );
}
