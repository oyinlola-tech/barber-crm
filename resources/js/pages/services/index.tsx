import { Head, useForm } from '@inertiajs/react';
import { Clock3, Pencil, Plus, Scissors } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import {
    CategoryManager,
    type ServiceCategory,
} from '@/features/services/category-manager';

type Service = {
    id: number;
    category_id: number | null;
    category_name: string | null;
    name: string;
    description: string | null;
    duration_minutes: number;
    price: string;
    is_active: boolean;
};

type ServiceForm = {
    name: string;
    description: string;
    duration_minutes: string;
    price: string;
    is_active: boolean;
    service_category_id: string;
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

function ServiceCatalogRow({
    service,
    currency,
    canUpdate,
    categories,
}: {
    service: Service;
    currency: string;
    canUpdate: boolean;
    categories: ServiceCategory[];
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm<ServiceForm>({
        name: service.name,
        description: service.description ?? '',
        duration_minutes: String(service.duration_minutes),
        price: service.price,
        is_active: service.is_active,
        service_category_id:
            service.category_id === null ? '' : String(service.category_id),
    });

    function edit(): void {
        form.setData({
            name: service.name,
            description: service.description ?? '',
            duration_minutes: String(service.duration_minutes),
            price: service.price,
            is_active: service.is_active,
            service_category_id:
                service.category_id === null ? '' : String(service.category_id),
        });
        form.clearErrors();
        setEditing(true);
    }

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            duration_minutes: Number(data.duration_minutes),
            service_category_id:
                data.service_category_id === ''
                    ? null
                    : Number(data.service_category_id),
        }));
        form.put(`/services/${service.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    }

    return (
        <li className="p-5">
            {editing ? (
                <form className="grid gap-4 md:grid-cols-2" onSubmit={submit}>
                    <label className="grid gap-1.5 text-sm font-medium">
                        Service name
                        <input
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
                                form.setData('price', event.target.value)
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
                    <label className="grid gap-1.5 text-sm font-medium">
                        Category (optional)
                        <select
                            className="h-10 rounded-lg border bg-background px-3 font-normal"
                            onChange={(event) =>
                                form.setData(
                                    'service_category_id',
                                    event.target.value,
                                )
                            }
                            value={form.data.service_category_id}
                        >
                            <option value="">Uncategorized</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        {form.errors.service_category_id && (
                            <span className="text-xs font-normal text-destructive">
                                {form.errors.service_category_id}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-1.5 text-sm font-medium md:col-span-2">
                        Description (optional)
                        <textarea
                            className="min-h-20 rounded-lg border bg-background px-3 py-2 font-normal"
                            maxLength={2000}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            value={form.data.description}
                        />
                        {form.errors.description && (
                            <span className="text-xs font-normal text-destructive">
                                {form.errors.description}
                            </span>
                        )}
                    </label>
                    <label className="flex items-center gap-2 text-sm font-medium md:col-span-2">
                        <input
                            checked={form.data.is_active}
                            onChange={(event) =>
                                form.setData('is_active', event.target.checked)
                            }
                            type="checkbox"
                        />
                        Available for visit records
                    </label>
                    <div className="flex gap-2 md:col-span-2">
                        <Button disabled={form.processing} type="submit">
                            Save changes
                        </Button>
                        <Button
                            disabled={form.processing}
                            onClick={() => setEditing(false)}
                            type="button"
                            variant="outline"
                        >
                            Cancel
                        </Button>
                    </div>
                </form>
            ) : (
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h3 className="font-medium">{service.name}</h3>
                            <span
                                className={`rounded-full px-2 py-0.5 text-xs ${service.is_active ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}
                            >
                                {service.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </div>
                        {service.description && (
                            <p className="mt-1 max-w-2xl text-sm text-muted-foreground">
                                {service.description}
                            </p>
                        )}
                        {service.category_name && (
                            <p className="mt-1 text-xs text-muted-foreground">
                                {service.category_name}
                            </p>
                        )}
                        <p className="mt-2 flex items-center gap-1.5 text-sm text-muted-foreground">
                            <Clock3 aria-hidden="true" size={14} />
                            {service.duration_minutes} minutes
                        </p>
                    </div>
                    <div className="flex shrink-0 items-center gap-3">
                        <p className="font-medium">
                            {money(service.price, currency)}
                        </p>
                        {canUpdate && (
                            <Button
                                aria-label={`Edit ${service.name}`}
                                onClick={edit}
                                size="sm"
                                variant="outline"
                            >
                                <Pencil aria-hidden="true" size={15} />
                                Edit
                            </Button>
                        )}
                    </div>
                </div>
            )}
        </li>
    );
}

export default function ServicesIndex({
    services,
    categories,
    currency,
    permissions,
}: {
    services: Service[];
    categories: ServiceCategory[];
    currency: string;
    permissions: {
        create: boolean;
        update: boolean;
        createCategory: boolean;
        updateCategory: boolean;
        deleteCategory: boolean;
    };
}) {
    const form = useForm<ServiceForm>({
        name: '',
        description: '',
        duration_minutes: '30',
        price: '0.00',
        is_active: true,
        service_category_id: '',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            duration_minutes: Number(data.duration_minutes),
            service_category_id:
                data.service_category_id === ''
                    ? null
                    : Number(data.service_category_id),
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
                            <label className="grid gap-1.5 text-sm font-medium">
                                Category (optional)
                                <select
                                    className="h-10 rounded-lg border bg-background px-3 font-normal"
                                    onChange={(event) =>
                                        form.setData(
                                            'service_category_id',
                                            event.target.value,
                                        )
                                    }
                                    value={form.data.service_category_id}
                                >
                                    <option value="">Uncategorized</option>
                                    {categories.map((category) => (
                                        <option
                                            key={category.id}
                                            value={category.id}
                                        >
                                            {category.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.service_category_id && (
                                    <span className="text-xs font-normal text-destructive">
                                        {form.errors.service_category_id}
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

                {(permissions.createCategory ||
                    permissions.updateCategory ||
                    permissions.deleteCategory) && (
                    <CategoryManager
                        categories={categories}
                        permissions={permissions}
                    />
                )}

                <section className="overflow-hidden rounded-2xl border bg-card">
                    <div className="flex items-center gap-2 border-b px-5 py-4">
                        <Scissors aria-hidden="true" size={18} />
                        <h2 className="font-semibold">Service catalog</h2>
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
                                <ServiceCatalogRow
                                    canUpdate={permissions.update}
                                    categories={categories}
                                    currency={currency}
                                    key={service.id}
                                    service={service}
                                />
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </main>
    );
}
