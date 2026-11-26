import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarClock,
    ClipboardList,
    Activity,
    Bell,
    ChevronDown,
    MessageCircle,
    Moon,
    Scissors,
    Settings2,
    ShieldCheck,
    Sun,
    Users,
    UserRoundCog,
    type LucideIcon,
} from 'lucide-react';
import { useEffect, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';

type NavigationItem = { label: string; href: string };

type DashboardLayoutProps = { children: ReactNode };

type SharedProps = {
    auth?: { user?: { name: string; email: string } | null };
    currentBusiness?: { name: string } | null;
    navigation?: NavigationItem[];
};

const navigationIcons: Record<string, LucideIcon> = {
    Dashboard: BarChart3,
    Customers: Users,
    Services: Scissors,
    Inbox: MessageCircle,
    'Follow-ups': CalendarClock,
    Templates: MessageCircle,
    Notifications: Bell,
    Reviews: ShieldCheck,
    Analytics: BarChart3,
    'Audit log': ClipboardList,
    'System health': Activity,
    Staff: UserRoundCog,
    Roles: ShieldCheck,
    WhatsApp: MessageCircle,
    'Business settings': Settings2,
    'Account security': ShieldCheck,
};

export default function DashboardLayout({ children }: DashboardLayoutProps) {
    const page = usePage<SharedProps>();
    const [darkMode, setDarkMode] = useState(false);
    const currentPath = page.url.split('?')[0];

    useEffect(() => {
        const saved = window.localStorage.getItem('barber-crm-theme');
        const prefersDark = window.matchMedia(
            '(prefers-color-scheme: dark)',
        ).matches;
        const enabled = saved ? saved === 'dark' : prefersDark;
        setDarkMode(enabled);
        document.documentElement.classList.toggle('dark', enabled);
    }, []);

    function toggleTheme() {
        const enabled = !darkMode;
        setDarkMode(enabled);
        document.documentElement.classList.toggle('dark', enabled);
        window.localStorage.setItem(
            'barber-crm-theme',
            enabled ? 'dark' : 'light',
        );
    }

    const links = page.props.navigation ?? [];

    return (
        <div className="min-h-screen bg-muted/30">
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r bg-card lg:flex">
                <Link
                    className="flex h-16 items-center gap-3 border-b px-5"
                    href="/dashboard"
                >
                    <span className="grid size-9 place-items-center rounded-xl bg-primary text-primary-foreground">
                        <Scissors aria-hidden="true" size={18} />
                    </span>
                    <span className="min-w-0">
                        <span className="block truncate text-sm font-semibold">
                            {page.props.currentBusiness?.name ?? 'Barber CRM'}
                        </span>
                        <span className="block text-xs text-muted-foreground">
                            Customer relationships
                        </span>
                    </span>
                </Link>
                <nav
                    aria-label="Main navigation"
                    className="flex-1 space-y-1 p-3"
                >
                    {links.map((item) => {
                        const Icon = navigationIcons[item.label] ?? Users;
                        const active =
                            currentPath === item.href ||
                            (item.href !== '/dashboard' &&
                                currentPath.startsWith(`${item.href}/`));

                        return (
                            <Link
                                aria-current={active ? 'page' : undefined}
                                className={`flex items-center gap-3 rounded-lg px-3 py-2.5 text-sm transition-colors ${active ? 'bg-accent font-medium text-accent-foreground' : 'text-muted-foreground hover:bg-muted hover:text-foreground'}`}
                                href={item.href}
                                key={item.href}
                            >
                                <Icon aria-hidden="true" size={17} />
                                {item.label}
                            </Link>
                        );
                    })}
                </nav>
                <div className="border-t p-3">
                    <p className="truncate px-3 pb-2 text-xs text-muted-foreground">
                        {page.props.auth?.user?.name ?? ''}
                    </p>
                    <div className="flex gap-2">
                        <Button
                            aria-label={
                                darkMode
                                    ? 'Switch to light theme'
                                    : 'Switch to dark theme'
                            }
                            className="flex-1"
                            onClick={toggleTheme}
                            size="sm"
                            variant="outline"
                        >
                            {darkMode ? <Sun size={16} /> : <Moon size={16} />}
                            {darkMode ? 'Light theme' : 'Dark theme'}
                        </Button>
                        <Button
                            onClick={() => router.post('/logout')}
                            size="sm"
                            variant="outline"
                        >
                            Sign out
                        </Button>
                    </div>
                </div>
            </aside>

            <div className="lg:pl-64">
                <header className="sticky top-0 z-20 border-b bg-card/95 px-4 py-3 backdrop-blur lg:hidden">
                    <div className="flex items-center justify-between gap-3">
                        <Link
                            className="flex min-w-0 items-center gap-2 font-semibold"
                            href="/dashboard"
                        >
                            <Scissors aria-hidden="true" size={18} />
                            <span className="truncate">
                                {page.props.currentBusiness?.name ??
                                    'Barber CRM'}
                            </span>
                        </Link>
                        <div className="flex shrink-0 items-center gap-2">
                            <Button
                                aria-label={
                                    darkMode
                                        ? 'Switch to light theme'
                                        : 'Switch to dark theme'
                                }
                                onClick={toggleTheme}
                                size="icon"
                                variant="outline"
                            >
                                {darkMode ? (
                                    <Sun aria-hidden="true" size={16} />
                                ) : (
                                    <Moon aria-hidden="true" size={16} />
                                )}
                            </Button>
                            <Button
                                onClick={() => router.post('/logout')}
                                size="sm"
                                variant="outline"
                            >
                                Sign out
                            </Button>
                        </div>
                    </div>
                    <details className="group relative mt-3">
                        <summary className="flex cursor-pointer list-none items-center justify-between rounded-lg border bg-background px-3 py-2 text-sm font-medium">
                            <span>Open navigation</span>
                            <ChevronDown
                                aria-hidden="true"
                                className="text-muted-foreground transition-transform group-open:rotate-180"
                                size={16}
                            />
                        </summary>
                        <nav
                            aria-label="Main navigation"
                            className="absolute left-0 right-0 top-full z-30 mt-2 hidden max-h-[70vh] grid-cols-2 gap-2 overflow-y-auto rounded-xl border bg-card p-3 shadow-xl group-open:grid"
                        >
                            {links.map((item) => {
                                const Icon =
                                    navigationIcons[item.label] ?? Users;
                                const active =
                                    currentPath === item.href ||
                                    (item.href !== '/dashboard' &&
                                        currentPath.startsWith(
                                            `${item.href}/`,
                                        ));

                                return (
                                    <Link
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={`inline-flex min-h-10 items-center gap-2 rounded-lg border px-3 py-2 text-xs ${active ? 'bg-accent font-medium text-accent-foreground' : 'bg-background text-muted-foreground hover:bg-muted'}`}
                                        href={item.href}
                                        key={item.href}
                                    >
                                        <Icon aria-hidden="true" size={15} />
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </nav>
                    </details>
                </header>
                {children}
            </div>
        </div>
    );
}
