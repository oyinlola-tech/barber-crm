import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';

type StaffMember = {
    id: number;
    name: string;
    email: string | null;
    phone: string | null;
    status: string;
    role: string;
};
type RoleOption = { slug: string; name: string };
type Invitation = {
    id: number;
    name: string;
    email: string;
    role: string;
    expires_at: string;
};
type StaffPageProps = {
    staff: StaffMember[];
    roles: RoleOption[];
    invitations: Invitation[];
    capabilities: {
        invite: boolean;
        assign: boolean;
        suspend: boolean;
        activate: boolean;
        update: boolean;
        revokeInvitation: boolean;
        viewRoles: boolean;
    };
};

export default function StaffPage({
    staff,
    roles,
    invitations,
    capabilities,
}: StaffPageProps) {
    const invitationForm = useForm({ name: '', email: '', role_slug: '' });
    const profileForm = useForm({ name: '', phone: '' });
    const [statusErrors, setStatusErrors] = useState<Record<number, string>>(
        {},
    );
    const [roleErrors, setRoleErrors] = useState<Record<number, string>>({});
    const [editingProfile, setEditingProfile] = useState<number | null>(null);
    const [profileErrors, setProfileErrors] = useState<Record<number, string>>(
        {},
    );
    const [invitationErrors, setInvitationErrors] = useState<
        Record<number, string>
    >({});

    function inviteStaff(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        invitationForm.post('/staff/invitations', {
            onSuccess: () => invitationForm.reset(),
        });
    }

    function updateStatus(member: StaffMember) {
        setStatusErrors((errors) => ({ ...errors, [member.id]: '' }));
        router.patch(
            `/staff/${member.id}/status`,
            { status: member.status === 'active' ? 'suspended' : 'active' },
            {
                onError: (errors) =>
                    setStatusErrors((current) => ({
                        ...current,
                        [member.id]:
                            Object.values(errors)[0] ??
                            'Could not update status.',
                    })),
            },
        );
    }

    function updateRole(member: StaffMember, role_slug: string) {
        setRoleErrors((errors) => ({ ...errors, [member.id]: '' }));
        router.patch(
            `/staff/${member.id}/role`,
            { role_slug },
            {
                onError: (errors) =>
                    setRoleErrors((current) => ({
                        ...current,
                        [member.id]:
                            Object.values(errors)[0] ??
                            'Could not update role.',
                    })),
            },
        );
    }

    function beginProfileEdit(member: StaffMember) {
        setEditingProfile(member.id);
        setProfileErrors((errors) => ({ ...errors, [member.id]: '' }));
        profileForm.setData({ name: member.name, phone: member.phone ?? '' });
        profileForm.clearErrors();
    }

    function saveProfile(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (editingProfile === null) return;

        profileForm.patch(`/staff/${editingProfile}/profile`, {
            preserveScroll: true,
            onSuccess: () => {
                setEditingProfile(null);
                setProfileErrors((errors) => ({
                    ...errors,
                    [editingProfile]: '',
                }));
            },
            onError: (errors) =>
                setProfileErrors((current) => ({
                    ...current,
                    [editingProfile]:
                        Object.values(errors)[0] ??
                        'Could not update the staff profile.',
                })),
        });
    }

    function revokeInvitation(invitation: Invitation) {
        if (
            !window.confirm(
                `Revoke the invitation for ${invitation.email}? Its link will stop working.`,
            )
        )
            return;

        setInvitationErrors((errors) => ({ ...errors, [invitation.id]: '' }));
        router.delete(`/staff/invitations/${invitation.id}`, {
            preserveScroll: true,
            onError: (errors) =>
                setInvitationErrors((current) => ({
                    ...current,
                    [invitation.id]:
                        Object.values(errors)[0] ??
                        'Could not revoke the invitation.',
                })),
        });
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Staff" />
            <div className="mx-auto max-w-6xl">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm text-muted-foreground">
                            Team access
                        </p>
                        <h1 className="mt-1 text-3xl font-semibold tracking-tight">
                            Staff
                        </h1>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Invite employees and manage their access to the CRM.
                        </p>
                    </div>
                    {capabilities.viewRoles && (
                        <Link
                            className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                            href="/roles"
                        >
                            Manage roles and permissions
                        </Link>
                    )}
                </header>

                {capabilities.invite && (
                    <section className="mt-7 rounded-2xl border bg-card p-5 sm:p-6">
                        <h2 className="text-lg font-semibold">
                            Invite a staff member
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            They will set their own password using a private,
                            single-use link that expires after 72 hours.
                        </p>
                        <form
                            className="mt-4 grid gap-3 sm:grid-cols-[1fr_1fr_1fr_auto] sm:items-start"
                            onSubmit={inviteStaff}
                        >
                            <Field
                                label="Full name"
                                value={invitationForm.data.name}
                                onChange={(value) =>
                                    invitationForm.setData('name', value)
                                }
                                error={invitationForm.errors.name}
                            />
                            <Field
                                label="Email"
                                type="email"
                                value={invitationForm.data.email}
                                onChange={(value) =>
                                    invitationForm.setData('email', value)
                                }
                                error={invitationForm.errors.email}
                            />
                            <label className="grid gap-1.5 text-sm font-medium">
                                Role
                                <select
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={invitationForm.data.role_slug}
                                    onChange={(event) =>
                                        invitationForm.setData(
                                            'role_slug',
                                            event.target.value,
                                        )
                                    }
                                    required
                                >
                                    <option value="">Choose a role</option>
                                    {roles
                                        .filter((role) => role.slug !== 'owner')
                                        .map((role) => (
                                            <option
                                                key={role.slug}
                                                value={role.slug}
                                            >
                                                {role.name}
                                            </option>
                                        ))}
                                </select>
                                {invitationForm.errors.role_slug && (
                                    <ErrorText
                                        text={invitationForm.errors.role_slug}
                                    />
                                )}
                            </label>
                            <Button
                                className="sm:mt-6"
                                disabled={
                                    invitationForm.processing ||
                                    roles.length === 0
                                }
                                type="submit"
                            >
                                {invitationForm.processing
                                    ? 'Sending…'
                                    : 'Send invitation'}
                            </Button>
                        </form>
                        {roles.length === 0 && (
                            <p className="mt-3 text-sm text-muted-foreground">
                                No role can currently be delegated by your
                                account.
                            </p>
                        )}
                    </section>
                )}

                <section className="mt-6 overflow-hidden rounded-2xl border bg-card">
                    <div className="border-b p-5">
                        <h2 className="text-lg font-semibold">
                            Staff accounts
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Suspension revokes active CRM sessions immediately.
                        </p>
                    </div>
                    {staff.length === 0 ? (
                        <p className="p-8 text-center text-sm text-muted-foreground">
                            No staff accounts have been created.
                        </p>
                    ) : (
                        <div className="divide-y">
                            {staff.map((member) => (
                                <article
                                    key={member.id}
                                    className="grid gap-4 p-5 sm:grid-cols-[1fr_160px_180px_auto] sm:items-center"
                                >
                                    <div>
                                        <p className="font-medium">
                                            {member.name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {member.email ??
                                                member.phone ??
                                                'No contact details'}
                                            {member.role === 'owner' && (
                                                <span className="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs text-primary">
                                                    Owner
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                    <span
                                        className={`w-fit rounded-full px-2.5 py-1 text-xs font-medium ${member.status === 'active' ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-300' : 'bg-muted text-muted-foreground'}`}
                                    >
                                        {member.status}
                                    </span>
                                    <label className="grid gap-1 text-xs text-muted-foreground">
                                        Role
                                        {capabilities.assign &&
                                        member.role !== 'owner' &&
                                        roles.some(
                                            (role) => role.slug === member.role,
                                        ) ? (
                                            <select
                                                aria-label={`Role for ${member.name}`}
                                                className="h-9 rounded-lg border bg-background px-2 text-sm text-foreground"
                                                value={member.role}
                                                onChange={(event) =>
                                                    updateRole(
                                                        member,
                                                        event.target.value,
                                                    )
                                                }
                                            >
                                                {roles.map((role) => (
                                                    <option
                                                        key={role.slug}
                                                        value={role.slug}
                                                    >
                                                        {role.name}
                                                    </option>
                                                ))}
                                            </select>
                                        ) : (
                                            <span className="pt-1 text-sm font-medium text-foreground">
                                                {roles.find(
                                                    (role) =>
                                                        role.slug ===
                                                        member.role,
                                                )?.name ?? member.role}
                                            </span>
                                        )}
                                        {roleErrors[member.id] && (
                                            <ErrorText
                                                text={roleErrors[member.id]}
                                            />
                                        )}
                                    </label>
                                    {member.role === 'owner' ? (
                                        <span className="text-xs text-muted-foreground">
                                            Protected account
                                        </span>
                                    ) : (
                                        <div className="flex flex-wrap gap-2">
                                            {capabilities.update && (
                                                <Button
                                                    onClick={() =>
                                                        beginProfileEdit(member)
                                                    }
                                                    type="button"
                                                    variant="outline"
                                                >
                                                    Edit profile
                                                </Button>
                                            )}
                                            {(member.status === 'active' &&
                                                capabilities.suspend) ||
                                            (member.status !== 'active' &&
                                                capabilities.activate) ? (
                                                <Button
                                                    onClick={() =>
                                                        updateStatus(member)
                                                    }
                                                    type="button"
                                                    variant="outline"
                                                >
                                                    {member.status === 'active'
                                                        ? 'Suspend'
                                                        : 'Activate'}
                                                </Button>
                                            ) : null}
                                        </div>
                                    )}
                                    {statusErrors[member.id] && (
                                        <ErrorText
                                            text={statusErrors[member.id]}
                                        />
                                    )}
                                    {editingProfile === member.id && (
                                        <form
                                            className="grid gap-3 rounded-xl border bg-muted/20 p-4 sm:col-span-4 sm:grid-cols-[1fr_1fr_auto_auto] sm:items-end"
                                            onSubmit={saveProfile}
                                        >
                                            <Field
                                                label="Staff display name"
                                                value={profileForm.data.name}
                                                onChange={(value) =>
                                                    profileForm.setData(
                                                        'name',
                                                        value,
                                                    )
                                                }
                                                error={profileForm.errors.name}
                                            />
                                            <Field
                                                label="Contact phone"
                                                value={profileForm.data.phone}
                                                onChange={(value) =>
                                                    profileForm.setData(
                                                        'phone',
                                                        value,
                                                    )
                                                }
                                                error={profileForm.errors.phone}
                                            />
                                            {profileErrors[member.id] && (
                                                <ErrorText
                                                    text={
                                                        profileErrors[member.id]
                                                    }
                                                />
                                            )}
                                            <Button
                                                disabled={
                                                    profileForm.processing
                                                }
                                                type="submit"
                                            >
                                                Save profile
                                            </Button>
                                            <Button
                                                onClick={() =>
                                                    setEditingProfile(null)
                                                }
                                                type="button"
                                                variant="ghost"
                                            >
                                                Cancel
                                            </Button>
                                        </form>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>

                <section className="mt-6 overflow-hidden rounded-2xl border bg-card">
                    <div className="border-b p-5">
                        <h2 className="text-lg font-semibold">
                            Pending invitations
                        </h2>
                    </div>
                    {invitations.length === 0 ? (
                        <p className="p-6 text-sm text-muted-foreground">
                            No pending invitations.
                        </p>
                    ) : (
                        <div className="divide-y">
                            {invitations.map((invitation) => (
                                <div
                                    className="flex flex-wrap items-center justify-between gap-3 p-4 text-sm"
                                    key={invitation.id}
                                >
                                    <div>
                                        <p className="font-medium">
                                            {invitation.name}
                                        </p>
                                        <p className="text-muted-foreground">
                                            {invitation.email} ·{' '}
                                            {invitation.role}
                                        </p>
                                    </div>
                                    <p className="text-muted-foreground">
                                        Expires{' '}
                                        {new Date(
                                            invitation.expires_at,
                                        ).toLocaleString()}
                                    </p>
                                    {capabilities.revokeInvitation && (
                                        <div className="flex flex-col items-end gap-1">
                                            <Button
                                                onClick={() =>
                                                    revokeInvitation(invitation)
                                                }
                                                type="button"
                                                variant="outline"
                                            >
                                                Revoke invitation
                                            </Button>
                                            {invitationErrors[
                                                invitation.id
                                            ] && (
                                                <ErrorText
                                                    text={
                                                        invitationErrors[
                                                            invitation.id
                                                        ]
                                                    }
                                                />
                                            )}
                                        </div>
                                    )}
                                </div>
                            ))}
                        </div>
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
    type = 'text',
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
    type?: string;
}) {
    return (
        <label className="grid gap-1.5 text-sm font-medium">
            {label}
            <input
                className="h-10 rounded-lg border bg-background px-3 font-normal"
                onChange={(event) => onChange(event.target.value)}
                type={type}
                value={value}
            />
            {error && <ErrorText text={error} />}
        </label>
    );
}
function ErrorText({ text }: { text: string }) {
    return <span className="text-xs font-normal text-destructive">{text}</span>;
}
