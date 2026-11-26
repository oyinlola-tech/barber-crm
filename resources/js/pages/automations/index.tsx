import { Head, router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useState } from 'react';

type Rule = {
    id: number;
    name: string;
    trigger: string;
    action: string;
    delay_minutes: number;
    is_enabled: boolean;
    settings: {
        template_id?: number;
        inactive_after_days?: number;
        max_follow_ups?: number;
        repeat_interval_minutes?: number;
    };
};
type Template = {
    id: number;
    name: string;
    language: string;
    purpose: string | null;
    provider_status: string | null;
    is_enabled: boolean;
};

export default function AutomationsIndex({
    rules,
    templates,
    capabilities,
}: {
    rules: Rule[];
    templates: Template[];
    capabilities: {
        create: boolean;
        update: boolean;
        enable: boolean;
        disable: boolean;
    };
}) {
    const form = useForm({
        name: '',
        trigger: 'visit_completed',
        action: 'send_template',
        delay_minutes: 4320,
        template_id: '',
        inactive_after_days: 30,
        max_follow_ups: 1,
        repeat_interval_days: 7,
        is_enabled: false,
    });
    const [editingRuleId, setEditingRuleId] = useState<number | null>(null);
    const eligibleTemplates = templates.filter(
        (template) =>
            template.is_enabled &&
            template.provider_status === 'APPROVED' &&
            (form.data.trigger !== 'customer_inactive' ||
                template.purpose === 'reengagement' ||
                template.purpose === 'retention'),
    );
    const templateError =
        form.errors.template_id ??
        (form.errors as Record<string, string | undefined>)[
            'settings.template_id'
        ];
    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        form.transform((data) => ({
            name: data.name,
            trigger: data.trigger,
            action: data.action,
            delay_minutes: Number(data.delay_minutes),
            settings: {
                template_id: Number(data.template_id),
                ...(data.trigger === 'customer_inactive'
                    ? {
                          inactive_after_days: Number(data.inactive_after_days),
                          max_follow_ups: Number(data.max_follow_ups),
                          repeat_interval_minutes:
                              Number(data.repeat_interval_days) * 1440,
                      }
                    : {}),
            },
            is_enabled: data.is_enabled,
        }));
        const options = {
            onSuccess: () => {
                setEditingRuleId(null);
                form.reset('name', 'template_id');
            },
        };
        if (editingRuleId === null) {
            form.post('/automations', options);
        } else {
            form.patch(`/automations/${editingRuleId}`, options);
        }
    }
    function editRule(rule: Rule) {
        setEditingRuleId(rule.id);
        form.clearErrors();
        form.setData({
            name: rule.name,
            trigger: rule.trigger,
            action: rule.action,
            delay_minutes: rule.delay_minutes,
            template_id: String(rule.settings.template_id ?? ''),
            inactive_after_days: rule.settings.inactive_after_days ?? 30,
            max_follow_ups: rule.settings.max_follow_ups ?? 1,
            repeat_interval_days: Math.max(
                1,
                Math.ceil(
                    (rule.settings.repeat_interval_minutes ?? 10080) / 1440,
                ),
            ),
            is_enabled: rule.is_enabled,
        });
    }
    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="Follow-up automations" />
            <div className="mx-auto max-w-6xl">
                <header className="mb-7">
                    <p className="text-sm font-medium text-primary">
                        RETENTION
                    </p>
                    <h1 className="mt-2 text-3xl font-semibold">
                        Follow-up automations
                    </h1>
                    <p className="mt-2 text-sm text-muted-foreground">
                        Automated sends require active WhatsApp consent and an
                        enabled, Meta-approved template.
                    </p>
                </header>
                {capabilities.create && (
                    <section className="mb-6 rounded-2xl border bg-card p-5">
                        <div className="flex items-center justify-between">
                            <h2 className="font-semibold">
                                {editingRuleId === null
                                    ? 'Create a rule'
                                    : 'Edit automation rule'}
                            </h2>
                            {editingRuleId !== null && (
                                <button
                                    type="button"
                                    className="text-sm underline"
                                    onClick={() => {
                                        setEditingRuleId(null);
                                        form.reset();
                                        form.clearErrors();
                                    }}
                                >
                                    Cancel edit
                                </button>
                            )}
                        </div>
                        <form
                            className="mt-4 grid gap-4 md:grid-cols-2"
                            onSubmit={submit}
                        >
                            <label className="grid gap-1 text-sm">
                                Rule name
                                <input
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    required
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Trigger
                                <select
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={form.data.trigger}
                                    onChange={(e) =>
                                        form.setData('trigger', e.target.value)
                                    }
                                >
                                    <option value="visit_completed">
                                        After a completed visit
                                    </option>
                                    <option value="customer_inactive">
                                        When a customer becomes inactive
                                    </option>
                                </select>
                            </label>
                            {form.data.trigger === 'customer_inactive' && (
                                <>
                                    <label className="grid gap-1 text-sm">
                                        Inactive after (days)
                                        <input
                                            className="h-10 rounded-lg border bg-background px-3"
                                            type="number"
                                            min="3"
                                            max="365"
                                            value={
                                                form.data.inactive_after_days
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'inactive_after_days',
                                                    Number(event.target.value),
                                                )
                                            }
                                            required
                                        />
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        Maximum follow-ups
                                        <input
                                            className="h-10 rounded-lg border bg-background px-3"
                                            type="number"
                                            min="1"
                                            max="20"
                                            value={form.data.max_follow_ups}
                                            onChange={(event) =>
                                                form.setData(
                                                    'max_follow_ups',
                                                    Number(event.target.value),
                                                )
                                            }
                                            required
                                        />
                                    </label>
                                    <label className="grid gap-1 text-sm">
                                        Days between follow-ups
                                        <input
                                            className="h-10 rounded-lg border bg-background px-3"
                                            type="number"
                                            min="1"
                                            max="365"
                                            value={
                                                form.data.repeat_interval_days
                                            }
                                            onChange={(event) =>
                                                form.setData(
                                                    'repeat_interval_days',
                                                    Number(event.target.value),
                                                )
                                            }
                                            required
                                        />
                                    </label>
                                </>
                            )}
                            <label className="grid gap-1 text-sm">
                                Delay (minutes)
                                <input
                                    className="h-10 rounded-lg border bg-background px-3"
                                    type="number"
                                    min="0"
                                    max="525600"
                                    value={form.data.delay_minutes}
                                    onChange={(e) =>
                                        form.setData(
                                            'delay_minutes',
                                            Number(e.target.value),
                                        )
                                    }
                                    required
                                />
                            </label>
                            <label className="grid gap-1 text-sm">
                                Approved template
                                <select
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={form.data.template_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'template_id',
                                            e.target.value,
                                        )
                                    }
                                    required
                                >
                                    <option value="">Choose template</option>
                                    {eligibleTemplates.map((t) => (
                                        <option value={t.id} key={t.id}>
                                            {t.name} ({t.language})
                                        </option>
                                    ))}
                                </select>
                                {templateError && (
                                    <p className="text-sm text-destructive">
                                        {templateError}
                                    </p>
                                )}
                            </label>
                            <button
                                className="w-fit rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                                disabled={
                                    form.processing ||
                                    eligibleTemplates.length === 0
                                }
                            >
                                {editingRuleId === null
                                    ? 'Create automation'
                                    : 'Save changes'}
                            </button>
                        </form>
                    </section>
                )}
                <section className="overflow-hidden rounded-2xl border bg-card">
                    <h2 className="border-b p-5 font-semibold">Rules</h2>
                    {rules.length === 0 ? (
                        <p className="p-6 text-sm text-muted-foreground">
                            No automation rules configured yet.
                        </p>
                    ) : (
                        <div className="divide-y">
                            {rules.map((rule) => (
                                <article
                                    className="flex flex-wrap items-center justify-between gap-3 p-5"
                                    key={rule.id}
                                >
                                    <div>
                                        <h3 className="font-medium">
                                            {rule.name}
                                        </h3>
                                        <p className="mt-1 text-sm text-muted-foreground">
                                            {rule.trigger.replaceAll('_', ' ')}{' '}
                                            · {rule.delay_minutes} minute delay
                                            · template #
                                            {rule.settings.template_id ??
                                                'unset'}
                                        </p>
                                    </div>
                                    {(capabilities.update ||
                                        (rule.is_enabled
                                            ? capabilities.disable
                                            : capabilities.enable)) && (
                                        <div className="flex gap-2">
                                            {capabilities.update && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        editRule(rule)
                                                    }
                                                    className="rounded-lg border px-3 py-2 text-sm"
                                                >
                                                    Edit
                                                </button>
                                            )}
                                            {(rule.is_enabled
                                                ? capabilities.disable
                                                : capabilities.enable) && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        router.patch(
                                                            `/automations/${rule.id}`,
                                                            {
                                                                is_enabled:
                                                                    !rule.is_enabled,
                                                            },
                                                        )
                                                    }
                                                    className="rounded-lg border px-3 py-2 text-sm"
                                                >
                                                    {rule.is_enabled
                                                        ? 'Disable'
                                                        : 'Enable'}
                                                </button>
                                            )}
                                        </div>
                                    )}
                                </article>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </main>
    );
}
