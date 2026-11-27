import { Head, useForm } from '@inertiajs/react';
import { Store } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { MessagingSettingsForm } from '@/features/settings/messaging-settings';

type BusinessProfile = {
    id: number;
    name: string;
    slug: string;
    timezone: string;
    currency: string;
    phone: string | null;
    email: string | null;
    description: string | null;
    address: string | null;
    public_review_url: string | null;
    logo_url: string | null;
};

export default function BusinessSettings({
    business,
    canUpdate,
    optOutKeywords,
}: {
    business: BusinessProfile;
    canUpdate: boolean;
    optOutKeywords: string[];
}) {
    const form = useForm({
        name: business.name,
        phone: business.phone ?? '',
        email: business.email ?? '',
        description: business.description ?? '',
        address: business.address ?? '',
        public_review_url: business.public_review_url ?? '',
        timezone: business.timezone,
        currency: business.currency,
        logo: null as File | null,
        remove_logo: false,
    });

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.put('/settings/business');
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Business settings" />
            <div className="mx-auto max-w-4xl">
                <header className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        CONFIGURATION
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        Business profile
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        These details identify the barbershop in staff and
                        customer communications.
                    </p>
                </header>
                <section className="rounded-2xl border bg-card p-5 sm:p-7">
                    <div className="flex items-center gap-2">
                        <Store size={18} />
                        <h2 className="font-semibold">Business details</h2>
                    </div>
                    <form
                        className="mt-5 grid gap-4 sm:grid-cols-2"
                        onSubmit={submit}
                    >
                        {form.recentlySuccessful && (
                            <p
                                aria-live="polite"
                                className="text-sm text-emerald-700 sm:col-span-2 dark:text-emerald-300"
                                role="status"
                            >
                                Business details saved.
                            </p>
                        )}
                        <Field
                            label="Business name"
                            value={form.data.name}
                            error={form.errors.name}
                            disabled={!canUpdate}
                            onChange={(value) => form.setData('name', value)}
                        />
                        <Field
                            label="Business phone"
                            value={form.data.phone}
                            error={form.errors.phone}
                            disabled={!canUpdate}
                            onChange={(value) => form.setData('phone', value)}
                        />
                        <Field
                            label="Business email"
                            value={form.data.email}
                            error={form.errors.email}
                            disabled={!canUpdate}
                            onChange={(value) => form.setData('email', value)}
                            type="email"
                        />
                        <Field
                            label="Business address"
                            value={form.data.address}
                            error={form.errors.address}
                            disabled={!canUpdate}
                            onChange={(value) => form.setData('address', value)}
                        />
                        <Field
                            label="Public review link"
                            value={form.data.public_review_url}
                            error={form.errors.public_review_url}
                            disabled={!canUpdate}
                            onChange={(value) =>
                                form.setData('public_review_url', value)
                            }
                            type="url"
                        />
                        <label className="grid gap-1.5 text-sm font-medium sm:col-span-2">
                            Business description
                            <textarea
                                className="min-h-24 rounded-lg border bg-background px-3 py-2 font-normal disabled:opacity-60"
                                disabled={!canUpdate}
                                maxLength={2000}
                                onChange={(event) =>
                                    form.setData(
                                        'description',
                                        event.target.value,
                                    )
                                }
                                value={form.data.description}
                            />
                            {form.errors.description && (
                                <span className="text-xs font-normal text-destructive">
                                    {form.errors.description}
                                </span>
                            )}
                        </label>
                        <div className="grid gap-2 text-sm sm:col-span-2">
                            <label className="grid gap-1.5 font-medium">
                                Business logo
                                <input
                                    accept="image/jpeg,image/png,image/webp"
                                    className="rounded-lg border bg-background p-2 font-normal disabled:opacity-60"
                                    disabled={!canUpdate}
                                    onChange={(event) => {
                                        form.setData(
                                            'logo',
                                            event.target.files?.[0] ?? null,
                                        );
                                        if (event.target.files?.[0]) {
                                            form.setData('remove_logo', false);
                                        }
                                    }}
                                    type="file"
                                />
                                <span className="text-xs font-normal text-muted-foreground">
                                    JPG, PNG or WebP, up to 2 MB.
                                </span>
                                {form.errors.logo && (
                                    <span className="text-xs font-normal text-destructive">
                                        {form.errors.logo}
                                    </span>
                                )}
                            </label>
                            {business.logo_url && (
                                <div className="flex flex-wrap items-center gap-3">
                                    <img
                                        alt="Current business logo"
                                        className="size-16 rounded-lg border object-contain"
                                        src={business.logo_url}
                                    />
                                    {canUpdate && (
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                checked={form.data.remove_logo}
                                                onChange={(event) => {
                                                    form.setData(
                                                        'remove_logo',
                                                        event.target.checked,
                                                    );
                                                    if (event.target.checked) {
                                                        form.setData(
                                                            'logo',
                                                            null,
                                                        );
                                                    }
                                                }}
                                                type="checkbox"
                                            />
                                            Remove current logo
                                        </label>
                                    )}
                                </div>
                            )}
                        </div>
                        <Field
                            label="Time zone"
                            value={form.data.timezone}
                            error={form.errors.timezone}
                            disabled={!canUpdate}
                            onChange={(value) =>
                                form.setData('timezone', value)
                            }
                        />
                        <label className="grid gap-1.5 text-sm font-medium">
                            Currency
                            <select
                                className="h-10 rounded-lg border bg-background px-3 font-normal disabled:opacity-60"
                                disabled={!canUpdate}
                                onChange={(event) =>
                                    form.setData('currency', event.target.value)
                                }
                                value={form.data.currency}
                            >
                                <option value="NGN">NGN</option>
                                <option value="USD">USD</option>
                                <option value="GBP">GBP</option>
                                <option value="EUR">EUR</option>
                            </select>
                            {form.errors.currency && (
                                <span className="text-xs font-normal text-destructive">
                                    {form.errors.currency}
                                </span>
                            )}
                        </label>
                        <div className="flex items-end sm:col-span-2">
                            {canUpdate ? (
                                <Button
                                    disabled={form.processing}
                                    type="submit"
                                >
                                    Save business details
                                </Button>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    You have read-only access to business
                                    settings.
                                </p>
                            )}
                        </div>
                    </form>
                </section>
                <MessagingSettingsForm
                    canUpdate={canUpdate}
                    keywords={optOutKeywords}
                />
            </div>
        </main>
    );
}

function Field({
    label,
    value,
    error,
    disabled,
    onChange,
    type = 'text',
}: {
    label: string;
    value: string;
    error?: string;
    disabled: boolean;
    onChange: (value: string) => void;
    type?: string;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            <input
                className="h-10 rounded-lg border bg-background px-3 font-normal disabled:opacity-60"
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                type={type}
                value={value}
            />
            {error && (
                <span className="text-xs font-normal text-destructive">
                    {error}
                </span>
            )}
        </label>
    );
}
