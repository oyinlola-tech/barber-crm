import { router, useForm } from '@inertiajs/react';
import { FolderPlus, Pencil, Trash2 } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

export type ServiceCategory = {
    id: number;
    name: string;
    description: string | null;
    sort_order: number;
    services_count: number;
};

type CategoryData = {
    name: string;
    description: string;
    sort_order: string;
};

type CategoryPermissions = {
    createCategory: boolean;
    updateCategory: boolean;
    deleteCategory: boolean;
};

function CategoryRow({
    category,
    permissions,
}: {
    category: ServiceCategory;
    permissions: CategoryPermissions;
}) {
    const [editing, setEditing] = useState(false);
    const form = useForm<CategoryData>({
        name: category.name,
        description: category.description ?? '',
        sort_order: String(category.sort_order),
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            sort_order: Number(data.sort_order),
        }));
        form.put(`/service-categories/${category.id}`, {
            preserveScroll: true,
            onSuccess: () => setEditing(false),
        });
    }

    function destroy(): void {
        if (
            category.services_count > 0 ||
            !window.confirm(`Delete the ${category.name} category?`)
        ) {
            return;
        }
        router.delete(`/service-categories/${category.id}`, {
            preserveScroll: true,
        });
    }

    return (
        <li className="flex flex-wrap items-start justify-between gap-4 border-t px-5 py-4">
            {editing ? (
                <form
                    className="grid w-full gap-3 sm:grid-cols-2"
                    onSubmit={submit}
                >
                    <label className="grid gap-1 text-sm font-medium">
                        Category name
                        <input
                            className="h-10 rounded-lg border bg-background px-3 font-normal"
                            maxLength={120}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            value={form.data.name}
                        />
                        {form.errors.name && (
                            <span className="text-xs text-destructive">
                                {form.errors.name}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-1 text-sm font-medium">
                        Sort order
                        <input
                            className="h-10 rounded-lg border bg-background px-3 font-normal"
                            min={0}
                            onChange={(event) =>
                                form.setData('sort_order', event.target.value)
                            }
                            type="number"
                            value={form.data.sort_order}
                        />
                    </label>
                    <label className="grid gap-1 text-sm font-medium sm:col-span-2">
                        Description (optional)
                        <textarea
                            className="min-h-16 rounded-lg border bg-background px-3 py-2 font-normal"
                            maxLength={1000}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            value={form.data.description}
                        />
                    </label>
                    <div className="flex gap-2 sm:col-span-2">
                        <Button disabled={form.processing} type="submit">
                            Save category
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
                <>
                    <div className="min-w-0">
                        <h3 className="font-medium">{category.name}</h3>
                        {category.description && (
                            <p className="mt-1 text-sm text-muted-foreground">
                                {category.description}
                            </p>
                        )}
                        <p className="mt-1 text-xs text-muted-foreground">
                            {category.services_count}{' '}
                            {category.services_count === 1
                                ? 'service'
                                : 'services'}{' '}
                            · Order {category.sort_order}
                        </p>
                    </div>
                    {(permissions.updateCategory ||
                        permissions.deleteCategory) && (
                        <div className="flex gap-2">
                            {permissions.updateCategory && (
                                <Button
                                    aria-label={`Edit category ${category.name}`}
                                    onClick={() => setEditing(true)}
                                    size="sm"
                                    variant="outline"
                                >
                                    <Pencil aria-hidden="true" size={15} /> Edit
                                </Button>
                            )}
                            {permissions.deleteCategory &&
                                category.services_count === 0 && (
                                    <Button
                                        aria-label={`Delete category ${category.name}`}
                                        className="text-destructive"
                                        onClick={destroy}
                                        size="sm"
                                        variant="outline"
                                    >
                                        <Trash2 aria-hidden="true" size={15} />{' '}
                                        Delete
                                    </Button>
                                )}
                        </div>
                    )}
                </>
            )}
        </li>
    );
}

export function CategoryManager({
    categories,
    permissions,
}: {
    categories: ServiceCategory[];
    permissions: CategoryPermissions;
}) {
    const form = useForm<CategoryData>({
        name: '',
        description: '',
        sort_order: '0',
    });

    function submit(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        form.transform((data) => ({
            ...data,
            sort_order: Number(data.sort_order),
        }));
        form.post('/service-categories', {
            preserveScroll: true,
            onSuccess: () => form.reset(),
        });
    }

    return (
        <section className="mb-6 overflow-hidden rounded-2xl border bg-card">
            <div className="flex items-center gap-2 border-b px-5 py-4">
                <FolderPlus aria-hidden="true" size={18} />
                <h2 className="font-semibold">Service categories</h2>
                <span className="ml-auto text-sm text-muted-foreground">
                    {categories.length}
                </span>
            </div>
            {permissions.createCategory && (
                <form
                    className="grid gap-3 p-5 sm:grid-cols-2"
                    onSubmit={submit}
                >
                    <label className="grid gap-1 text-sm font-medium">
                        Category name
                        <input
                            className="h-10 rounded-lg border bg-background px-3 font-normal"
                            maxLength={120}
                            onChange={(event) =>
                                form.setData('name', event.target.value)
                            }
                            required
                            value={form.data.name}
                        />
                        {form.errors.name && (
                            <span className="text-xs text-destructive">
                                {form.errors.name}
                            </span>
                        )}
                    </label>
                    <label className="grid gap-1 text-sm font-medium">
                        Sort order
                        <input
                            className="h-10 rounded-lg border bg-background px-3 font-normal"
                            min={0}
                            onChange={(event) =>
                                form.setData('sort_order', event.target.value)
                            }
                            type="number"
                            value={form.data.sort_order}
                        />
                    </label>
                    <label className="grid gap-1 text-sm font-medium sm:col-span-2">
                        Description (optional)
                        <textarea
                            className="min-h-16 rounded-lg border bg-background px-3 py-2 font-normal"
                            maxLength={1000}
                            onChange={(event) =>
                                form.setData('description', event.target.value)
                            }
                            value={form.data.description}
                        />
                    </label>
                    <div className="sm:col-span-2">
                        <Button disabled={form.processing} type="submit">
                            Add category
                        </Button>
                    </div>
                </form>
            )}
            {categories.length === 0 ? (
                <p className="border-t p-6 text-center text-sm text-muted-foreground">
                    No service categories yet.
                </p>
            ) : (
                <ul>
                    {categories.map((category) => (
                        <CategoryRow
                            category={category}
                            key={category.id}
                            permissions={permissions}
                        />
                    ))}
                </ul>
            )}
        </section>
    );
}
