import { createInertiaApp } from '@inertiajs/react';
import type { ResolvedComponent } from '@inertiajs/react';
import { createRoot } from 'react-dom/client';
import type { ComponentType, ReactNode } from 'react';
import DashboardLayout from '@/layouts/dashboard-layout';
import AuthLayout from '@/layouts/auth-layout';

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx');

void createInertiaApp({
    title: (title) => (title ? `${title} · Barber CRM` : 'Barber CRM'),
    resolve: async (name) => {
        const loadPage = pages[`./pages/${name}.tsx`];

        if (!loadPage) {
            throw new Error(`Inertia page not found: ${name}`);
        }

        const page = await loadPage();
        const component = page.default as ResolvedComponent;
        const isAuthPage =
            name.startsWith('auth/') || name === 'staff/accept-invitation';
        if (isAuthPage && component.layout === undefined) {
            component.layout = (content: ReactNode): ReactNode => (
                <AuthLayout>{content}</AuthLayout>
            );
        } else if (component.layout === undefined) {
            component.layout = (content: ReactNode): ReactNode => (
                <DashboardLayout>{content}</DashboardLayout>
            );
        }

        return component;
    },
    setup({ el, App, props }) {
        if (!el) {
            throw new Error('Inertia could not find the application element.');
        }

        createRoot(el).render(<App {...props} />);
    },
    progress: {
        color: '#344c3b',
    },
    strictMode: true,
});
