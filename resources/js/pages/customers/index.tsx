import { Head, Link, router, useForm } from '@inertiajs/react';
import { Download, Search, UserRoundPlus, Users } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type CustomerRow = {
    id: number;
    first_name: string;
    last_name: string | null;
    phone: string;
    email: string | null;
    status: string;
    visits_count: number;
};

type CustomersPageProps = {
    customers: {
        data: CustomerRow[];
        current_page: number;
        last_page: number;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: { search: string; status: 'active' | 'archived' };
    canCreate: boolean;
    canAddNotes: boolean;
    canExport: boolean;
    canImport: boolean;
    importResult: {
        created: number;
        skipped: number;
        issues: Array<{ row: number; reason: string }>;
    } | null;
};

export default function CustomersPage({
    customers,
    filters,
    canCreate,
    canAddNotes,
    canExport,
    canImport,
    importResult,
}: CustomersPageProps) {
    const [search, setSearch] = useState(filters.search);
    const [status, setStatus] = useState<'active' | 'archived'>(filters.status);
    const form = useForm({
        first_name: '',
        last_name: '',
        phone: '',
        email: '',
        notes: '',
    });
    const importForm = useForm<{ file: File | null }>({ file: null });

    useEffect(() => {
        setSearch(filters.search);
        setStatus(filters.status);
    }, [filters.search, filters.status]);

    function searchCustomers(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        router.get(
            '/customers',
            { search, status },
            { preserveState: true, replace: true },
        );
    }

    function addCustomer(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.post('/customers', { onSuccess: () => form.reset() });
    }

    function importCustomers(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        importForm.post('/customers/import', {
            forceFormData: true,
            onSuccess: () => importForm.reset(),
        });
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Customers" />
            <div className="mx-auto max-w-7xl">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Customer relationships
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                            Customers
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Manage customer contact details, consent and visit
                            history.
                        </p>
                    </div>
                    {canCreate && filters.status === 'active' && (
                        <details className="group w-full rounded-xl border bg-card p-4 sm:max-w-md">
                            <summary className="flex cursor-pointer list-none items-center justify-between gap-3 font-medium">
                                <span className="flex items-center gap-2">
                                    <UserRoundPlus size={18} /> Add a customer
                                </span>
                                <span
                                    aria-hidden="true"
                                    className="text-muted-foreground group-open:rotate-180"
                                >
                                    ⌄
                                </span>
                            </summary>
                            <form
                                className="mt-4 grid gap-3"
                                onSubmit={addCustomer}
                            >
                                <Field
                                    label="First name"
                                    value={form.data.first_name}
                                    onChange={(value) =>
                                        form.setData('first_name', value)
                                    }
                                    error={form.errors.first_name}
                                    required
                                />
                                <Field
                                    label="Last name"
                                    value={form.data.last_name}
                                    onChange={(value) =>
                                        form.setData('last_name', value)
                                    }
                                    error={form.errors.last_name}
                                />
                                <Field
                                    label="WhatsApp phone"
                                    value={form.data.phone}
                                    onChange={(value) =>
                                        form.setData('phone', value)
                                    }
                                    error={form.errors.phone}
                                    required
                                    type="tel"
                                />
                                <Field
                                    label="Email (optional)"
                                    value={form.data.email}
                                    onChange={(value) =>
                                        form.setData('email', value)
                                    }
                                    error={form.errors.email}
                                    type="email"
                                />
                                {canAddNotes && (
                                    <label className="grid gap-1.5 text-sm font-medium">
                                        Staff notes
                                        <textarea
                                            className="min-h-20 rounded-lg border bg-background px-3 py-2 font-normal"
                                            maxLength={5000}
                                            onChange={(event) =>
                                                form.setData(
                                                    'notes',
                                                    event.target.value,
                                                )
                                            }
                                            value={form.data.notes}
                                        />
                                        {form.errors.notes && (
                                            <span className="text-xs font-normal text-destructive">
                                                {form.errors.notes}
                                            </span>
                                        )}
                                    </label>
                                )}
                                <Button
                                    disabled={form.processing}
                                    type="submit"
                                >
                                    {form.processing
                                        ? 'Saving…'
                                        : 'Save customer'}
                                </Button>
                            </form>
                        </details>
                    )}
                </header>

                {canImport && (
                    <section className="mt-5 rounded-xl border bg-card p-4 sm:p-5">
                        <h2 className="font-medium">
                            Import customers from CSV
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Add new records only. Existing or duplicate
                            normalized phone numbers are skipped and reported;
                            no records are merged or overwritten. Maximum 500
                            rows, 2 MB.
                        </p>
                        <form
                            className="mt-3 flex flex-wrap items-end gap-3"
                            onSubmit={importCustomers}
                        >
                            <label className="grid gap-1.5 text-sm font-medium">
                                CSV file
                                <input
                                    accept=".csv,text/csv"
                                    className="max-w-full rounded-md border bg-background px-3 py-2 text-sm"
                                    onChange={(event) =>
                                        importForm.setData(
                                            'file',
                                            event.target.files?.[0] ?? null,
                                        )
                                    }
                                    required
                                    type="file"
                                />
                                {importForm.errors.file && (
                                    <span className="text-xs font-normal text-destructive">
                                        {importForm.errors.file}
                                    </span>
                                )}
                            </label>
                            <Button
                                disabled={
                                    importForm.processing ||
                                    importForm.data.file === null
                                }
                                type="submit"
                            >
                                {importForm.processing
                                    ? 'Importing…'
                                    : 'Import CSV'}
                            </Button>
                            <a
                                className="text-sm text-primary underline-offset-4 hover:underline"
                                href="data:text/csv;charset=utf-8,first_name%2Clast_name%2Cphone%2Cemail%2Cdate_of_birth%2Cnotes%0AAda%2COkafor%2C%2B2348012345678%2Cada%40example.com%2C%2C"
                            >
                                Download CSV example
                            </a>
                        </form>
                        {importResult && (
                            <div
                                className="mt-4 rounded-lg bg-muted/60 p-3 text-sm"
                                role="status"
                            >
                                <p>
                                    Created {importResult.created} customer
                                    {importResult.created === 1 ? '' : 's'};
                                    skipped {importResult.skipped} row
                                    {importResult.skipped === 1 ? '' : 's'}.
                                </p>
                                {importResult.issues.length > 0 && (
                                    <ul className="mt-2 max-h-40 list-inside list-disc overflow-auto text-muted-foreground">
                                        {importResult.issues.map((issue) => (
                                            <li
                                                key={`${issue.row}-${issue.reason}`}
                                            >
                                                Row {issue.row}: {issue.reason}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                    </section>
                )}

                <section className="mt-7 rounded-2xl border bg-card">
                    <div className="flex flex-wrap items-center justify-between gap-4 border-b p-4 sm:p-5">
                        <div className="flex items-center gap-2 text-sm font-medium">
                            <Users size={18} /> {customers.total} customers
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            {canExport && (
                                <a
                                    className="inline-flex h-10 items-center justify-center gap-2 rounded-md border bg-background px-4 text-sm font-medium hover:bg-accent"
                                    href="/customers/export"
                                >
                                    <Download aria-hidden="true" size={16} />
                                    Export CSV
                                </a>
                            )}
                            <form
                                className="flex w-full gap-2 sm:w-auto"
                                onSubmit={searchCustomers}
                                role="search"
                            >
                                <label className="grid min-w-0 flex-1 gap-1 text-xs font-medium sm:w-72">
                                    <span className="sr-only">
                                        Search customers
                                    </span>
                                    <Search
                                        aria-hidden="true"
                                        className="absolute left-3 top-1/2 -translate-y-1/2 text-muted-foreground"
                                        size={16}
                                    />
                                    <input
                                        className="h-10 w-full rounded-lg border bg-background pl-9 pr-3 text-sm"
                                        onChange={(event) =>
                                            setSearch(event.target.value)
                                        }
                                        placeholder="Name, phone or email"
                                        value={search}
                                    />
                                </label>
                                <label className="grid gap-1 text-xs font-medium">
                                    Customer status
                                    <select
                                        className="h-10 rounded-lg border bg-background px-3 text-sm font-normal"
                                        onChange={(event) =>
                                            setStatus(
                                                event.target.value as
                                                    'active' | 'archived',
                                            )
                                        }
                                        value={status}
                                    >
                                        <option value="active">Active</option>
                                        <option value="archived">
                                            Archived
                                        </option>
                                    </select>
                                </label>
                                <Button type="submit" variant="outline">
                                    Search
                                </Button>
                            </form>
                        </div>
                    </div>
                    {customers.data.length === 0 ? (
                        <div className="p-10 text-center">
                            <Users
                                aria-hidden="true"
                                className="mx-auto text-muted-foreground"
                                size={26}
                            />
                            <p className="mt-3 font-medium">
                                {filters.search
                                    ? `No matching ${filters.status} customers`
                                    : filters.status === 'archived'
                                      ? 'No archived customers'
                                      : 'Your customer list is empty'}
                            </p>
                            {canCreate && filters.status === 'active' && (
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Add a customer above to start recording
                                    visits and preferences.
                                </p>
                            )}
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[600px] text-left text-sm">
                                <thead className="bg-muted/50 text-xs uppercase tracking-wide text-muted-foreground">
                                    <tr>
                                        <th className="px-5 py-3 font-medium">
                                            Customer
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            WhatsApp
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            Visits
                                        </th>
                                        <th className="px-5 py-3 font-medium">
                                            <span className="sr-only">
                                                Actions
                                            </span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {customers.data.map((customer) => (
                                        <tr
                                            className="hover:bg-muted/30"
                                            key={customer.id}
                                        >
                                            <td className="px-5 py-4">
                                                <Link
                                                    className="font-medium hover:underline"
                                                    href={`/customers/${customer.id}`}
                                                >
                                                    {customer.first_name}{' '}
                                                    {customer.last_name ?? ''}
                                                </Link>
                                                <p className="mt-0.5 text-xs text-muted-foreground">
                                                    {customer.email ??
                                                        'No email recorded'}
                                                </p>
                                                {customer.status ===
                                                    'archived' && (
                                                    <span className="mt-1 inline-block rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground">
                                                        Archived
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-5 py-4">
                                                {customer.phone}
                                            </td>
                                            <td className="px-5 py-4">
                                                {customer.visits_count}
                                            </td>
                                            <td className="px-5 py-4 text-right">
                                                <Link
                                                    className="text-primary hover:underline"
                                                    href={`/customers/${customer.id}`}
                                                >
                                                    View profile
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                    {customers.last_page > 1 && (
                        <nav
                            aria-label="Customer pages"
                            className="flex flex-wrap gap-2 border-t p-4"
                        >
                            {customers.links.map((link) => (
                                <Link
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className={`rounded-md border px-3 py-1.5 text-sm ${link.active ? 'bg-primary text-primary-foreground' : 'bg-background'} ${link.url ? '' : 'pointer-events-none opacity-50'}`}
                                    href={link.url ?? '#'}
                                    key={link.label}
                                    preserveScroll
                                >
                                    {link.label
                                        .replace(/&laquo;|&raquo;/g, '')
                                        .trim()}
                                </Link>
                            ))}
                        </nav>
                    )}
                </section>
            </div>
        </main>
    );
}

function Field({
    label,
    value,
    onChange,
    error,
    required = false,
    type = 'text',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    required?: boolean;
    type?: string;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            <input
                autoComplete="off"
                className="h-10 rounded-lg border bg-background px-3 font-normal"
                onChange={(event) => onChange(event.target.value)}
                required={required}
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
