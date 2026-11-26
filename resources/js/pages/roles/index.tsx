import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type Role = {
    id: number;
    name: string;
    slug: string;
    permissions: string[];
    protected: boolean;
};
type Props = {
    roles: Role[];
    permissions: Record<string, string>;
    capabilities: { create: boolean; update: boolean; delete: boolean };
};

export default function RolesPage({ roles, permissions, capabilities }: Props) {
    const [editing, setEditing] = useState<Role | null>(null);
    const createForm = useForm({ name: '', permissions: [] as string[] });
    const editForm = useForm({ name: '', permissions: [] as string[] });
    const permissionGroups = Object.entries(permissions).reduce<
        Record<string, Array<{ key: string; label: string }>>
    >((groups, [key, label]) => {
        const category = key.split('.')[0] ?? 'other';
        groups[category] ??= [];
        groups[category].push({ key, label });

        return groups;
    }, {});

    function createRole(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        createForm.post('/roles', { onSuccess: () => createForm.reset() });
    }
    function saveRole(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!editing) return;
        editForm.put(`/roles/${editing.id}`, {
            onSuccess: () => setEditing(null),
        });
    }
    function beginEdit(role: Role) {
        setEditing(role);
        editForm.setData({ name: role.name, permissions: role.permissions });
        editForm.clearErrors();
    }
    function removeRole(role: Role) {
        if (
            window.confirm(
                `Delete the ${role.name} role? It must not be assigned to any staff account.`,
            )
        ) {
            router.delete(`/roles/${role.id}`);
        }
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Roles and permissions" />
            <div className="mx-auto max-w-6xl">
                <header>
                    <p className="text-sm text-muted-foreground">
                        Access control
                    </p>
                    <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                        Roles and permissions
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Permissions are checked on the server. Staff can only
                        delegate permissions they already hold.
                    </p>
                </header>
                {capabilities.create && (
                    <section className="mt-7 rounded-2xl border bg-card p-5 sm:p-6">
                        <h2 className="text-lg font-semibold">
                            Create custom role
                        </h2>
                        <form className="mt-4 grid gap-4" onSubmit={createRole}>
                            <TextField
                                label="Role name"
                                value={createForm.data.name}
                                onChange={(value) =>
                                    createForm.setData('name', value)
                                }
                                error={createForm.errors.name}
                            />
                            <PermissionPicker
                                permissions={permissions}
                                selected={createForm.data.permissions}
                                onChange={(selected) =>
                                    createForm.setData('permissions', selected)
                                }
                            />
                            {createForm.errors.permissions && (
                                <ErrorText
                                    text={createForm.errors.permissions}
                                />
                            )}
                            <Button
                                disabled={createForm.processing}
                                type="submit"
                            >
                                Create role
                            </Button>
                        </form>
                    </section>
                )}
                {editing && (
                    <section className="mt-5 rounded-2xl border border-primary/30 bg-card p-5 sm:p-6">
                        <div className="flex items-center justify-between gap-3">
                            <h2 className="text-lg font-semibold">
                                Edit {editing.name}
                            </h2>
                            <Button
                                onClick={() => setEditing(null)}
                                type="button"
                                variant="ghost"
                            >
                                Close
                            </Button>
                        </div>
                        <form className="mt-4 grid gap-4" onSubmit={saveRole}>
                            <TextField
                                label="Role name"
                                value={editForm.data.name}
                                onChange={(value) =>
                                    editForm.setData('name', value)
                                }
                                error={editForm.errors.name}
                            />
                            <PermissionPicker
                                permissions={permissions}
                                selected={editForm.data.permissions}
                                onChange={(selected) =>
                                    editForm.setData('permissions', selected)
                                }
                            />
                            {editForm.errors.permissions && (
                                <ErrorText text={editForm.errors.permissions} />
                            )}
                            <Button
                                disabled={editForm.processing}
                                type="submit"
                            >
                                Save changes
                            </Button>
                        </form>
                    </section>
                )}
                <section className="mt-6 grid gap-4 md:grid-cols-2">
                    {roles.map((role) => (
                        <article
                            className="rounded-2xl border bg-card p-5"
                            key={role.id}
                        >
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h2 className="font-semibold">
                                        {role.name}
                                    </h2>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {role.slug}
                                        {role.protected &&
                                            ' · protected system role'}
                                    </p>
                                </div>
                                <span className="rounded-full bg-muted px-2 py-1 text-xs">
                                    {role.permissions.includes('*')
                                        ? 'All permissions'
                                        : `${role.permissions.length} permissions`}
                                </span>
                            </div>
                            {!role.permissions.includes('*') && (
                                <ul className="mt-4 flex flex-wrap gap-1.5">
                                    {role.permissions.map((permission) => (
                                        <li
                                            className="rounded-md bg-muted px-2 py-1 text-xs text-muted-foreground"
                                            key={permission}
                                        >
                                            {permission}
                                        </li>
                                    ))}
                                </ul>
                            )}
                            {!role.protected &&
                                (capabilities.update ||
                                    capabilities.delete) && (
                                    <div className="mt-4 flex gap-2 border-t pt-4">
                                        {capabilities.update && (
                                            <Button
                                                onClick={() => beginEdit(role)}
                                                type="button"
                                                variant="outline"
                                            >
                                                Edit permissions
                                            </Button>
                                        )}
                                        {capabilities.delete && (
                                            <Button
                                                onClick={() => removeRole(role)}
                                                type="button"
                                                variant="ghost"
                                            >
                                                Delete
                                            </Button>
                                        )}
                                    </div>
                                )}
                        </article>
                    ))}
                </section>
                <details className="mt-6 rounded-2xl border bg-card">
                    <summary className="cursor-pointer p-5 font-semibold">
                        Permission catalogue{' '}
                        <span className="ml-2 text-sm font-normal text-muted-foreground">
                            {Object.keys(permissions).length} available
                            permissions
                        </span>
                    </summary>
                    <div className="grid gap-5 border-t p-5 md:grid-cols-2 xl:grid-cols-3">
                        {Object.entries(permissionGroups)
                            .sort(([left], [right]) =>
                                left.localeCompare(right),
                            )
                            .map(([category, entries]) => (
                                <section key={category}>
                                    <h3 className="mb-2 text-sm font-semibold capitalize">
                                        {category}
                                    </h3>
                                    <ul className="space-y-2">
                                        {entries.map(({ key, label }) => (
                                            <li
                                                className="rounded-lg bg-muted/50 px-3 py-2"
                                                key={key}
                                            >
                                                <span className="block text-sm">
                                                    {label}
                                                </span>
                                                <code className="text-xs text-muted-foreground">
                                                    {key}
                                                </code>
                                            </li>
                                        ))}
                                    </ul>
                                </section>
                            ))}
                    </div>
                </details>
            </div>
        </main>
    );
}

function PermissionPicker({
    permissions,
    selected,
    onChange,
}: {
    permissions: Record<string, string>;
    selected: string[];
    onChange: (selected: string[]) => void;
}) {
    return (
        <fieldset className="grid gap-3">
            <legend className="text-sm font-medium">Permissions</legend>
            <div className="grid gap-2 rounded-xl border p-4 sm:grid-cols-2 lg:grid-cols-3">
                {Object.entries(permissions).map(([permission, label]) => (
                    <label
                        className="flex items-start gap-2 text-sm"
                        key={permission}
                    >
                        <input
                            checked={selected.includes(permission)}
                            className="mt-0.5"
                            onChange={(event) =>
                                onChange(
                                    event.target.checked
                                        ? [...selected, permission]
                                        : selected.filter(
                                              (value) => value !== permission,
                                          ),
                                )
                            }
                            type="checkbox"
                        />
                        <span>
                            <span className="block font-medium">{label}</span>
                            <span className="text-xs text-muted-foreground">
                                {permission}
                            </span>
                        </span>
                    </label>
                ))}
            </div>
        </fieldset>
    );
}
function TextField({
    label,
    value,
    onChange,
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            <input
                className="h-10 rounded-lg border bg-background px-3 font-normal"
                maxLength={80}
                onChange={(event) => onChange(event.target.value)}
                value={value}
            />
            {error && <ErrorText text={error} />}
        </label>
    );
}
function ErrorText({ text }: { text: string }) {
    return <span className="text-xs font-normal text-destructive">{text}</span>;
}
