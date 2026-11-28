import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    CalendarClock,
    ClipboardList,
    Activity,
    ArrowUpRight,
    Bell,
    ChevronDown,
    MessageCircle,
    MoreHorizontal,
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
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
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
    const mobilePrimary = links.filter((item) =>
        ['Dashboard', 'Customers', 'Inbox', 'Follow-ups'].includes(item.label),
    );
    const mobileMore = links.filter((item) => !mobilePrimary.includes(item));
    const activeLabel =
        links.find((item) => isActive(item.href))?.label ?? 'Your shop';
    const footerGroups = [
        {
            title: 'Keep in touch',
            labels: ['Customers', 'Inbox', 'Follow-ups', 'Reviews'],
        },
        {
            title: 'Run the shop',
            labels: ['Staff', 'Roles', 'Templates', 'Notifications'],
        },
        {
            title: 'Your workspace',
            labels: [
                'Analytics',
                'System health',
                'Audit log',
                'Business settings',
                'Account security',
                'WhatsApp',
            ],
        },
    ]
        .map((group) => ({
            ...group,
            links: group.labels.flatMap((label) => {
                const item = links.find((link) => link.label === label);
                return item ? [item] : [];
            }),
        }))
        .filter((group) => group.links.length > 0);
    const customerLink = links.find((item) => item.label === 'Customers');
    const inboxLink = links.find((item) => item.label === 'Inbox');

    function isActive(href: string): boolean {
        return (
            currentPath === href ||
            (href !== '/dashboard' && currentPath.startsWith(`${href}/`))
        );
    }

    return (
        <div className="min-h-screen bg-muted/30">
            <a
                className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-background focus:px-4 focus:py-3 focus:text-sm focus:font-medium focus:text-foreground focus:shadow-lg focus:ring-2 focus:ring-ring"
                href="#main-content"
            >
                Skip to main content
            </a>
            <aside className="fixed inset-y-0 left-0 z-30 hidden w-64 flex-col border-r bg-card lg:flex">
                <Link
                    className="flex h-16 items-center gap-3 border-b px-5"
                    href="/dashboard"
                >
                    <img
                        alt=""
                        aria-hidden="true"
                        className="size-9 rounded-xl"
                        height={36}
                        src="/brand/barber-crm-mark.svg"
                        width={36}
                    />
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
                            <img
                                alt=""
                                aria-hidden="true"
                                className="size-8 shrink-0 rounded-lg"
                                height={32}
                                src="/brand/barber-crm-mark.svg"
                                width={32}
                            />
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
                    <p className="mt-2 text-xs text-muted-foreground">
                        {activeLabel}
                    </p>
                </header>
                <div
                    id="main-content"
                    className="scroll-mt-20 pb-20 lg:pb-0"
                    tabIndex={-1}
                >
                    {children}
                </div>
                <footer className="relative isolate mb-24 mt-2 overflow-hidden bg-[#172235] px-5 py-9 text-white sm:px-8 sm:py-12 lg:mb-0 lg:px-10 lg:py-14 xl:px-14 xl:py-16">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute right-[-8rem] top-[-10rem] z-0 size-[34rem] rounded-full border border-white/10"
                    />
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute right-[-4rem] top-[-6rem] z-0 size-[26rem] rounded-full border border-white/[0.07]"
                    />
                    <div className="relative z-10 grid gap-12 xl:grid-cols-[minmax(0,1.25fr)_minmax(440px,0.75fr)] xl:items-end">
                        <div>
                            <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-[#f08378]">
                                <span className="size-1.5 rounded-full bg-[#f08378]" />
                                Good service stays with people
                            </div>
                            <p className="mt-5 max-w-5xl text-[clamp(3rem,8.6vw,8rem)] font-semibold leading-[0.88] tracking-[-0.075em]">
                                Good service deserves a return visit
                                <span className="text-[#f08378]">.</span>
                            </p>
                            <p className="mt-5 max-w-lg text-sm leading-6 text-white/60">
                                Keep the relationship going with a thoughtful
                                follow-up, a remembered visit, and a team that
                                stays in sync.
                            </p>
                            <div className="mt-7 flex flex-wrap gap-3">
                                {customerLink && (
                                    <Link
                                        className="inline-flex min-h-11 items-center gap-2 rounded-xl bg-[#f08378] px-4 text-sm font-semibold text-[#172235] transition hover:bg-[#ff9b90]"
                                        href={customerLink.href}
                                    >
                                        Open customers
                                        <ArrowUpRight
                                            aria-hidden="true"
                                            size={16}
                                        />
                                    </Link>
                                )}
                                {inboxLink && (
                                    <Link
                                        className="inline-flex min-h-11 items-center gap-2 rounded-xl border border-white/20 px-4 text-sm font-medium text-white transition hover:bg-white/10"
                                        href={inboxLink.href}
                                    >
                                        Go to inbox
                                        <MessageCircle
                                            aria-hidden="true"
                                            size={16}
                                        />
                                    </Link>
                                )}
                            </div>
                        </div>
                        <div className="grid grid-cols-2 gap-x-5 gap-y-8 border-t border-white/15 pt-7 sm:grid-cols-3 sm:gap-x-7 xl:border-l xl:border-t-0 xl:pl-9 xl:pt-0">
                            {footerGroups.map((group) => (
                                <div key={group.title}>
                                    <h2 className="text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
                                        {group.title}
                                    </h2>
                                    <ul className="mt-4 space-y-3">
                                        {group.links.map((item) => (
                                            <li key={item.href}>
                                                <Link
                                                    className="text-sm text-white/75 transition hover:text-white"
                                                    href={item.href}
                                                >
                                                    {item.label}
                                                </Link>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ))}
                        </div>
                    </div>
                    <div className="relative z-10 mt-12 flex flex-col gap-4 border-t border-white/15 pt-6 text-xs text-white/45 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2">
                            <img
                                alt=""
                                aria-hidden="true"
                                className="size-7 rounded-lg"
                                src="/brand/barber-crm-mark.svg"
                            />
                            <span>
                                {page.props.currentBusiness?.name ??
                                    'Barber CRM'}
                                {' · '}Private team workspace
                            </span>
                        </div>
                        <div className="flex flex-wrap items-center gap-x-5 gap-y-2">
                            <span>Customer information stays protected.</span>
                            <span>© {new Date().getFullYear()}</span>
                        </div>
                    </div>
                </footer>
                <div className="fixed inset-x-0 bottom-0 z-40 border-t bg-card/95 pb-[env(safe-area-inset-bottom)] shadow-[0_-6px_24px_rgba(15,23,42,.07)] backdrop-blur lg:hidden">
                    {mobileMenuOpen && mobileMore.length > 0 && (
                        <nav
                            aria-label="More navigation"
                            className="absolute bottom-full left-3 right-3 mb-2 grid max-h-[60vh] grid-cols-2 gap-2 overflow-y-auto rounded-2xl border bg-card p-3 shadow-2xl"
                        >
                            {mobileMore.map((item) => {
                                const Icon =
                                    navigationIcons[item.label] ?? Users;
                                const active = isActive(item.href);
                                return (
                                    <Link
                                        aria-current={
                                            active ? 'page' : undefined
                                        }
                                        className={`flex min-h-11 items-center gap-2 rounded-xl px-3 py-2 text-xs ${active ? 'bg-accent font-semibold text-accent-foreground' : 'text-muted-foreground hover:bg-muted'}`}
                                        href={item.href}
                                        key={item.href}
                                        onClick={() => setMobileMenuOpen(false)}
                                    >
                                        <Icon aria-hidden="true" size={16} />
                                        {item.label}
                                    </Link>
                                );
                            })}
                        </nav>
                    )}
                    <nav
                        aria-label="Mobile navigation"
                        className="mx-auto grid max-w-2xl grid-cols-5 px-2 pt-1"
                    >
                        {mobilePrimary.map((item) => {
                            const Icon = navigationIcons[item.label] ?? Users;
                            const active = isActive(item.href);
                            return (
                                <Link
                                    aria-current={active ? 'page' : undefined}
                                    className={`flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[10px] font-medium ${active ? 'text-[#b6453d] dark:text-[#f08378]' : 'text-muted-foreground'}`}
                                    href={item.href}
                                    key={item.href}
                                    onClick={() => setMobileMenuOpen(false)}
                                >
                                    <Icon
                                        aria-hidden="true"
                                        size={19}
                                        strokeWidth={active ? 2.5 : 1.8}
                                    />
                                    {item.label}
                                </Link>
                            );
                        })}
                        <button
                            aria-expanded={mobileMenuOpen}
                            aria-label={
                                mobileMenuOpen
                                    ? 'Close more navigation'
                                    : 'Open more navigation'
                            }
                            className={`flex min-h-14 flex-col items-center justify-center gap-1 rounded-xl px-1 text-[10px] font-medium ${mobileMenuOpen ? 'text-[#b6453d] dark:text-[#f08378]' : 'text-muted-foreground'}`}
                            onClick={() => setMobileMenuOpen((open) => !open)}
                            type="button"
                        >
                            {mobileMenuOpen ? (
                                <ChevronDown aria-hidden="true" size={19} />
                            ) : (
                                <MoreHorizontal aria-hidden="true" size={19} />
                            )}
                            More
                        </button>
                    </nav>
                </div>
            </div>
        </div>
    );
}
