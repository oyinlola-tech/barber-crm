import { useEffect, useState, type ReactNode } from 'react';
import { ArrowUpRight, Moon, ShieldCheck, Sun } from 'lucide-react';
import { BrandMark } from '@/components/brand/brand-mark';

type AuthLayoutProps = {
    children: ReactNode;
};

export default function AuthLayout({ children }: AuthLayoutProps) {
    const [darkMode, setDarkMode] = useState(false);

    useEffect(() => {
        const saved = window.localStorage.getItem('barber-crm-theme');
        const enabled = saved
            ? saved === 'dark'
            : window.matchMedia('(prefers-color-scheme: dark)').matches;
        setDarkMode(enabled);
        document.documentElement.classList.toggle('dark', enabled);
    }, []);

    function toggleTheme(): void {
        const enabled = !darkMode;
        setDarkMode(enabled);
        document.documentElement.classList.toggle('dark', enabled);
        window.localStorage.setItem(
            'barber-crm-theme',
            enabled ? 'dark' : 'light',
        );
    }

    return (
        <div className="min-h-screen bg-background">
            <div className="grid min-h-screen lg:grid-cols-[minmax(0,1.05fr)_minmax(480px,0.95fr)]">
                <aside className="relative hidden min-h-screen overflow-hidden bg-[#172235] text-white lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-10 xl:px-16">
                    <div
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-0 overflow-hidden"
                    >
                        <div className="absolute -right-24 top-28 size-[28rem] rounded-full border border-white/10" />
                        <div className="absolute -right-10 top-44 size-[21rem] rounded-full border border-white/10" />
                        <div className="absolute bottom-0 left-0 h-72 w-full bg-gradient-to-t from-black/35 to-transparent" />
                    </div>

                    <div className="relative z-10">
                        <BrandMark inverse />
                    </div>

                    <div className="relative z-10 flex flex-1 flex-col justify-center py-12">
                        <div className="max-w-2xl">
                            <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-1.5 text-xs font-medium text-white/75">
                                <span className="size-1.5 rounded-full bg-[#f08378]" />
                                A calmer way to run the shop
                            </p>
                            <h1 className="max-w-xl text-5xl font-semibold leading-[1.06] tracking-[-0.045em] xl:text-6xl">
                                Keep the good conversations going.
                            </h1>
                            <p className="mt-6 max-w-md text-base leading-7 text-white/65">
                                Customer history, WhatsApp follow-ups and team
                                activity together in one clear workspace.
                            </p>
                            <div className="mt-8 grid gap-3 text-sm text-white/75 sm:grid-cols-3">
                                <p className="flex items-center gap-3">
                                    <span className="size-1.5 shrink-0 rounded-full bg-[#f08378]" />
                                    Remember the people behind each visit
                                </p>
                                <p className="flex items-center gap-3">
                                    <span className="size-1.5 shrink-0 rounded-full bg-[#a8bddc]" />
                                    Follow up with permission and care
                                </p>
                                <p className="flex items-center gap-3">
                                    <span className="size-1.5 shrink-0 rounded-full bg-[#eef2f8]" />
                                    Keep your team on the same page
                                </p>
                            </div>
                        </div>

                        <figure className="relative mt-10 overflow-hidden rounded-[2rem] border border-white/15 bg-white/5 shadow-2xl shadow-black/20">
                            <img
                                alt="Illustration of a quiet barbershop, ready for the next customer"
                                className="aspect-[1.9/1] w-full object-cover object-center opacity-90"
                                height={720}
                                src="/brand/barbershop-editorial.png"
                                width={1080}
                            />
                            <figcaption className="absolute bottom-4 left-4 rounded-full border border-white/15 bg-[#172235]/80 px-4 py-2 text-xs text-white/85 backdrop-blur">
                                Thoughtful service keeps customers coming back.
                            </figcaption>
                        </figure>
                    </div>
                </aside>

                <section className="relative flex min-h-screen flex-col bg-background">
                    <button
                        aria-label={
                            darkMode
                                ? 'Switch to light theme'
                                : 'Switch to dark theme'
                        }
                        className="absolute right-5 top-5 z-20 grid size-10 place-items-center rounded-xl border bg-card text-muted-foreground transition hover:bg-muted hover:text-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring"
                        onClick={toggleTheme}
                        type="button"
                    >
                        {darkMode ? (
                            <Sun aria-hidden="true" size={17} />
                        ) : (
                            <Moon aria-hidden="true" size={17} />
                        )}
                    </button>
                    <div className="absolute left-5 top-5 lg:hidden">
                        <BrandMark compact />
                    </div>
                    <div className="flex flex-1 items-center justify-center px-4 py-20 sm:px-8 lg:px-12">
                        <div className="w-full max-w-md">{children}</div>
                    </div>
                </section>
            </div>
            <footer className="relative isolate overflow-hidden bg-[#172235] px-5 py-10 text-white sm:px-8 sm:py-12 lg:px-12 lg:py-14 xl:px-16 xl:py-16">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-28 -top-44 z-0 size-[34rem] rounded-full border border-white/10"
                />
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute -right-12 -top-28 z-0 size-[26rem] rounded-full border border-white/[0.07]"
                />
                <div className="relative z-10 grid gap-12 xl:grid-cols-[minmax(0,1.2fr)_minmax(360px,0.8fr)] xl:items-end">
                    <div>
                        <p className="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-[#f08378]">
                            <span className="size-1.5 rounded-full bg-[#f08378]" />
                            Private workspace · Secure staff access
                        </p>
                        <p className="mt-6 max-w-5xl text-[clamp(3rem,9vw,8rem)] font-semibold leading-[0.86] tracking-[-0.075em]">
                            Good service deserves a return visit
                            <span className="text-[#f08378]">.</span>
                        </p>
                        <p className="mt-6 max-w-xl text-sm leading-6 text-white/60 sm:text-base sm:leading-7">
                            Keep every customer relationship thoughtful,
                            personal and easy for your team to follow through.
                        </p>
                    </div>
                    <div className="grid gap-8 border-t border-white/15 pt-7 sm:grid-cols-2 xl:border-l xl:border-t-0 xl:pl-9 xl:pt-0">
                        <div>
                            <h2 className="text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
                                Your workspace
                            </h2>
                            <div className="mt-4 flex items-center gap-3">
                                <img
                                    alt=""
                                    aria-hidden="true"
                                    className="size-10 rounded-xl"
                                    height={40}
                                    src="/brand/barber-crm-mark.svg"
                                    width={40}
                                />
                                <span>
                                    <span className="block text-sm font-semibold text-white">
                                        Barber CRM
                                    </span>
                                    <span className="block text-xs text-white/50">
                                        Customer relationships
                                    </span>
                                </span>
                            </div>
                        </div>
                        <div>
                            <h2 className="text-[10px] font-semibold uppercase tracking-[0.16em] text-white/40">
                                Built around care
                            </h2>
                            <ul className="mt-4 space-y-3 text-sm text-white/75">
                                <li className="flex items-center gap-2">
                                    <ShieldCheck
                                        aria-hidden="true"
                                        className="text-[#f08378]"
                                        size={16}
                                    />
                                    Authorized staff only
                                </li>
                                <li className="flex items-center gap-2">
                                    <ArrowUpRight
                                        aria-hidden="true"
                                        className="text-[#f08378]"
                                        size={16}
                                    />
                                    Thoughtful customer follow-up
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div className="relative z-10 mt-12 flex flex-col gap-3 border-t border-white/15 pt-5 text-xs text-white/45 sm:flex-row sm:items-center sm:justify-between">
                    <span>Customer information stays protected.</span>
                    <span>© {new Date().getFullYear()} Barber CRM</span>
                </div>
            </footer>
        </div>
    );
}
