import { Head, useForm } from '@inertiajs/react';
import { Clock3, Plus, Scissors } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type Service = {
    id: number;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string;
};

type ServiceForm = {
    name: string;
    description: string;
    duration_minutes: string;
    price: string;
    is_active: boolean;
};

function money(amount: string, currency: string): string {
    try {
        return new Intl.NumberFormat(undefined, {
            style: 'currency',
            currency,
        }).format(Number(amount));
    } catch {
        return `${currency} ${amount}`;
    }
}

export default function ServicesIndex({
    services,
    currency,
    permissions,
}: {
    services: Service[];
    currency: string;
    permissions: { create: boolean };
}) {
    const form = useForm<ServiceForm>({
        name: '',
        description: '',
        duration_minutes: '30',
        price: '0.00',
        is_active: true,
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            duration_minutes: Number(data.duration_minutes),
        }));
        form.post('/services', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Service catalog" />
            <div className="mx-auto max-w-6xl">
                <header className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        BARBERSHOP DETAILS
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold tracking-tight">
                        Service catalog
                    </h1>
                    <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                        Keep the services customers have received available for
                        staff visit records.
                    </p>
                </header>

                {permissions.create && (
                    <section className="mb-6 rounded-2xl border bg-card p-5 sm:p-6">
                        <div className="flex items-center gap-2">
                            <Plus aria-hidden="true" size={18} />
                            <h2 className="font-semibold">Add a service</h2>
                        </div>
                        <form
                            className="mt-5 grid gap-4 md:grid-cols-2"
                            onSubmit={submit}
                        >
                            <label className="grid gap-1.5 text-sm font-medium">
                                Service name
                                <input
                                    autoComplete="off"
                                    className="h-10 rounded-lg border bg-background px-3 font-normal"
                                    maxLength={160}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                    value={form.data.name}
                                />
                                {form.errors.name && (
                                    <span className="text-xs font-normal text-destructive">
                                        {form.errors.name}
                                    </span>
                                )}
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Duration in minutes
                                <input
                                    className="h-10 rounded-lg border bg-background px-3 font-normal"
                                    max={480}
                                    min={5}
                                    onChange={(event) =>
                                        form.setData(
                                            'duration_minutes',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    type="number"
                                    value={form.data.duration_minutes}
                                />
                                {form.errors.duration_minutes && (
                                    <span className="text-xs font-normal text-destructive">
                                        {form.errors.duration_minutes}
                                    </span>
                                )}
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium">
                                Price ({currency})
                                <input
                                    className="h-10 rounded-lg border bg-background px-3 font-normal"
                                    min={0}
                                    onChange={(event) =>
                                        form.setData(
                                            'price',
                                            event.target.value,
                                        )
                                    }
                                    required
                                    step="0.01"
                                    type="number"
                                    value={form.data.price}
                                />
                                {form.errors.price && (
                                    <span className="text-xs font-normal text-destructive">
                                        {form.errors.price}
                                    </span>
                                )}
                            </label>
                            <label className="grid gap-1.5 text-sm font-medium md:col-span-2">
                                Description (optional)
                                <textarea
                                    className="min-h-20 rounded-lg border bg-background px-3 py-2 font-normal"
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
                            <div className="md:col-span-2">
                                <Button
                                    disabled={form.processing}
                                    type="submit"
                                >
                                    Add to catalog
                                </Button>
                            </div>
                        </form>
                    </section>
                )}

                <section className="overflow-hidden rounded-2xl border bg-card">
                    <div className="flex items-center gap-2 border-b px-5 py-4">
                        <Scissors aria-hidden="true" size={18} />
                        <h2 className="font-semibold">Active services</h2>
                        <span className="ml-auto text-sm text-muted-foreground">
                            {services.length}
                        </span>
                    </div>
                    {services.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No services have been added yet.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {services.map((service) => (
                                <li
                                    className="flex flex-wrap items-start justify-between gap-4 p-5"
                                    key={service.id}
                                >
                                    <div className="min-w-0">
                                        <h3 className="font-medium">
                                            {service.name}
                                        </h3>
                                        {service.description && (
                                            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                                {service.description}
                                            </p>
                                        )}
                                        <p className="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <Clock3
                                                aria-hidden="true"
                                                size={14}
                                            />
                                            {service.duration_minutes} minutes
                                        </p>
                                    </div>
                                    <p className="shrink-0 font-medium">
                                        {money(service.price, currency)}
                                    </p>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </main>
    );
}
