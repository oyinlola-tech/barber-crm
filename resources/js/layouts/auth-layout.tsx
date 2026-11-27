import type { ReactNode } from 'react';
import { BrandMark } from '@/components/brand/brand-mark';

type AuthLayoutProps = {
    children: ReactNode;
};

export default function AuthLayout({ children }: AuthLayoutProps) {
    return (
        <div className="grid min-h-screen bg-background lg:grid-cols-[minmax(0,1.05fr)_minmax(480px,0.95fr)]">
            <aside className="relative hidden min-h-screen overflow-hidden bg-[#202522] text-white lg:flex lg:flex-col lg:justify-between lg:px-12 lg:py-10 xl:px-16">
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 overflow-hidden"
                >
                    <div className="absolute -right-24 top-28 size-[28rem] rounded-full border border-white/10" />
                    <div className="absolute -right-10 top-44 size-[21rem] rounded-full border border-white/10" />
                    <div className="absolute bottom-0 left-0 h-72 w-full bg-gradient-to-t from-black/25 to-transparent" />
                </div>

                <div className="relative z-10">
                    <BrandMark inverse />
                </div>

                <div className="relative z-10 grid flex-1 grid-cols-[minmax(0,1fr)_210px] items-center gap-5 xl:grid-cols-[minmax(0,1fr)_260px]">
                    <div className="max-w-xl py-14">
                        <p className="mb-5 inline-flex items-center gap-2 rounded-full border border-white/15 px-3 py-1.5 text-xs font-medium text-white/75">
                            <span className="size-1.5 rounded-full bg-[#9fd06e]" />
                            A calmer way to run the shop
                        </p>
                        <h1 className="max-w-lg text-5xl font-semibold leading-[1.06] tracking-[-0.045em] xl:text-6xl">
                            Keep the good conversations going.
                        </h1>
                        <p className="mt-6 max-w-md text-base leading-7 text-white/65">
                            Customer history, WhatsApp follow-ups and team
                            activity together in one clear workspace.
                        </p>
                        <div className="mt-10 grid gap-3 text-sm text-white/75">
                            <p className="flex items-center gap-3">
                                <span className="size-1.5 rounded-full bg-[#315CEB]" />
                                Remember the people behind each visit
                            </p>
                            <p className="flex items-center gap-3">
                                <span className="size-1.5 rounded-full bg-[#E46651]" />
                                Follow up with permission and care
                            </p>
                            <p className="flex items-center gap-3">
                                <span className="size-1.5 rounded-full bg-[#9fd06e]" />
                                Keep your team on the same page
                            </p>
                        </div>
                    </div>

                    <div
                        aria-hidden="true"
                        className="relative mx-auto grid size-44 place-items-center rounded-[2rem] border border-white/10 bg-white/[0.035] shadow-2xl shadow-black/20 xl:size-56"
                    >
                        <div className="absolute inset-4 rounded-[1.5rem] border border-white/10" />
                        <div className="relative h-36 w-12 overflow-hidden rounded-full border-2 border-[#e9e4d8] bg-[#e9e4d8] shadow-[0_18px_50px_rgba(0,0,0,.35)] xl:h-44 xl:w-14">
                            <div className="absolute inset-0 -rotate-[38deg] scale-150">
                                <div className="auth-pole-stripes absolute inset-0 bg-[repeating-linear-gradient(0deg,#315CEB_0px,#315CEB_13px,#F4F1E9_13px,#F4F1E9_26px,#E46651_26px,#E46651_39px,#F4F1E9_39px,#F4F1E9_52px)]" />
                            </div>
                            <div className="absolute inset-x-0 top-0 h-3 rounded-t-full bg-[#202522]" />
                            <div className="absolute inset-x-0 bottom-0 h-3 rounded-b-full bg-[#202522]" />
                        </div>
                        <div className="absolute -bottom-5 -left-6 rounded-xl border border-white/10 bg-[#292f2b] px-4 py-3 shadow-xl">
                            <p className="text-[10px] font-medium text-white/45">
                                THE NEXT VISIT
                            </p>
                            <p className="mt-1 text-xs font-medium text-white/90">
                                Starts with a hello.
                            </p>
                        </div>
                    </div>
                </div>

                <footer className="relative z-10 flex items-center justify-between gap-4 border-t border-white/10 pt-5 text-xs text-white/45">
                    <span>Private workspace for your barbershop</span>
                    <span>Secure staff access</span>
                </footer>
            </aside>

            <section className="relative flex min-h-screen flex-col bg-background">
                <div className="absolute left-5 top-5 lg:hidden">
                    <BrandMark compact />
                </div>
                <div className="flex flex-1 items-center justify-center px-4 py-20 sm:px-8 lg:px-12">
                    <div className="w-full max-w-md">{children}</div>
                </div>
                <footer className="flex items-center justify-center px-5 pb-5 text-center text-xs text-muted-foreground">
                    Authorized staff only · Your customer records stay private
                </footer>
            </section>
        </div>
    );
}
