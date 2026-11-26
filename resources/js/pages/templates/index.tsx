import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';

type TemplateComponent = {
    type?: string;
    text?: string;
    example?: { body_text?: string[][] };
};

type Template = {
    id: number;
    name: string;
    language: string;
    category: string | null;
    purpose: string | null;
    status: string;
    provider_status: string | null;
    provider_template_id: string | null;
    is_enabled: boolean;
    components: TemplateComponent[] | null;
    variable_map: string[] | null;
};

type TemplateDraft = {
    name: string;
    language: string;
    category: 'UTILITY' | 'MARKETING';
    purpose: string;
    body: string;
    examples: Record<string, string>;
};

const purposes = [
    'thank_you',
    'experience',
    'review_request',
    'return_intention',
    'retention',
    'reengagement',
    'custom',
] as const;

const emptyExamples = (variables: string[]): Record<string, string> =>
    Object.fromEntries(variables.map((variable) => [variable, '']));

function bodyComponent(template: Template): TemplateComponent | undefined {
    return template.components?.find((component) => component.type === 'BODY');
}

function numberedVariables(body: string): number[] {
    const matches = [...body.matchAll(/{{\s*(\d+)\s*}}/g)];
    return [...new Set(matches.map((match) => Number(match[1])))].sort(
        (left, right) => left - right,
    );
}

function draftData(template: Template): TemplateDraft {
    const component = bodyComponent(template);
    const variables = template.variable_map ?? [];
    const body = (component?.text ?? '').replace(
        /{{\s*(\d+)\s*}}/g,
        (_match, index: string) => `{{${variables[Number(index) - 1] ?? ''}}}`,
    );
    const exampleValues = component?.example?.body_text?.[0] ?? [];
    const examples = Object.fromEntries(
        variables.map((variable, index) => [
            variable,
            exampleValues[index] ?? '',
        ]),
    );

    return {
        name: template.name,
        language: template.language,
        category:
            template.category?.toUpperCase() === 'MARKETING'
                ? 'MARKETING'
                : 'UTILITY',
        purpose: purposes.includes(
            template.purpose as (typeof purposes)[number],
        )
            ? (template.purpose ?? 'custom')
            : 'custom',
        body,
        examples,
    };
}

function previewDraft(body: string, examples: Record<string, string>): string {
    return body.replace(/{{\s*([a-z_]+)\s*}}/g, (match, variable: string) => {
        const example = examples[variable]?.trim();

        return example === undefined || example === '' ? match : example;
    });
}

function previewProviderTemplate(template: Template): string {
    const body = bodyComponent(template)?.text ?? 'No body text is available.';
    const mappings = template.variable_map ?? [];

    return body.replace(/{{\s*(\d+)\s*}}/g, (_match, index: string) => {
        const variable = mappings[Number(index) - 1];

        return variable === undefined
            ? `[parameter ${index}]`
            : `{{${variable}}}`;
    });
}

export default function TemplatesIndex({
    templates,
    allowedVariables,
    permissions,
}: {
    templates: Template[];
    allowedVariables: string[];
    permissions: { create: boolean; update: boolean; sync: boolean };
}) {
    const makeDraft = (): TemplateDraft => ({
        name: '',
        language: 'en',
        category: 'UTILITY',
        purpose: 'thank_you',
        body: '',
        examples: emptyExamples(allowedVariables),
    });
    const form = useForm<TemplateDraft>(makeDraft());
    const mappingForm = useForm<{ variables: string[] }>({ variables: [] });
    const [editingTemplate, setEditingTemplate] = useState<Template | null>(
        null,
    );
    const [mappingTemplate, setMappingTemplate] = useState<Template | null>(
        null,
    );
    const [previewTemplateId, setPreviewTemplateId] = useState<number | null>(
        null,
    );

    function submitDraft(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        const options = {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setEditingTemplate(null);
            },
        };

        if (editingTemplate === null) {
            form.post('/templates', options);
        } else {
            form.put(`/templates/${editingTemplate.id}`, options);
        }
    }

    function startEditing(template: Template): void {
        setEditingTemplate(template);
        form.setData(draftData(template));
    }

    function cancelEditing(): void {
        setEditingTemplate(null);
        form.reset();
        form.clearErrors();
    }

    function openMapping(template: Template): void {
        const body = bodyComponent(template)?.text ?? '';
        const positions = numberedVariables(body);
        const current = template.variable_map ?? [];
        setMappingTemplate(template);
        mappingForm.setData({
            variables: positions.map((position) => current[position - 1] ?? ''),
        });
    }

    function saveMapping(event: FormEvent<HTMLFormElement>): void {
        event.preventDefault();
        if (mappingTemplate === null) return;

        mappingForm.patch(`/templates/${mappingTemplate.id}/variables`, {
            preserveScroll: true,
            onSuccess: () => setMappingTemplate(null),
        });
    }

    function submitToMeta(template: Template): void {
        router.post(
            `/templates/${template.id}/submit`,
            {},
            { preserveScroll: true },
        );
    }

    function synchronize(): void {
        router.post('/templates/synchronize', {}, { preserveScroll: true });
    }

    function toggleEnabled(template: Template): void {
        router.patch(
            `/templates/${template.id}/enabled`,
            { is_enabled: !template.is_enabled },
            { preserveScroll: true },
        );
    }

    return (
        <main className="min-h-screen bg-muted/30 px-4 py-6 sm:px-8 sm:py-9">
            <Head title="WhatsApp templates" />
            <div className="mx-auto max-w-6xl">
                <header className="mb-7 flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <p className="text-sm font-medium text-primary">
                            MESSAGING
                        </p>
                        <h1 className="mt-2 text-3xl font-semibold">
                            WhatsApp templates
                        </h1>
                        <p className="mt-2 max-w-2xl text-sm text-muted-foreground">
                            Manage local drafts and the approval status returned
                            by the connected Meta account. A local draft is not
                            an approved WhatsApp template.
                        </p>
                    </div>
                    {permissions.sync && (
                        <button
                            type="button"
                            onClick={synchronize}
                            className="rounded-lg border bg-background px-4 py-2 text-sm font-medium"
                        >
                            Synchronize provider status
                        </button>
                    )}
                </header>

                {(permissions.create || editingTemplate !== null) && (
                    <section className="mb-6 rounded-2xl border bg-card p-5 sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="font-semibold">
                                    {editingTemplate
                                        ? 'Edit local draft'
                                        : 'Create a local draft'}
                                </h2>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    Meta approval must be confirmed by
                                    synchronization before a template can be
                                    enabled.
                                </p>
                            </div>
                            {editingTemplate && (
                                <button
                                    type="button"
                                    onClick={cancelEditing}
                                    className="text-sm underline"
                                >
                                    Cancel editing
                                </button>
                            )}
                        </div>
                        <form
                            className="mt-5 grid gap-4 md:grid-cols-2"
                            onSubmit={submitDraft}
                        >
                            <label className="grid gap-1 text-sm">
                                Template name
                                <input
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={form.data.name}
                                    onChange={(event) =>
                                        form.setData('name', event.target.value)
                                    }
                                    required
                                />
                                {form.errors.name && (
                                    <span className="text-destructive">
                                        {form.errors.name}
                                    </span>
                                )}
                            </label>
                            <label className="grid gap-1 text-sm">
                                Language code
                                <input
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={form.data.language}
                                    onChange={(event) =>
                                        form.setData(
                                            'language',
                                            event.target.value,
                                        )
                                    }
                                    required
                                />
                                {form.errors.language && (
                                    <span className="text-destructive">
                                        {form.errors.language}
                                    </span>
                                )}
                            </label>
                            <label className="grid gap-1 text-sm">
                                Purpose
                                <select
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={form.data.purpose}
                                    onChange={(event) =>
                                        form.setData(
                                            'purpose',
                                            event.target.value,
                                        )
                                    }
                                >
                                    {purposes.map((purpose) => (
                                        <option key={purpose} value={purpose}>
                                            {purpose.replaceAll('_', ' ')}
                                        </option>
                                    ))}
                                </select>
                            </label>
                            <label className="grid gap-1 text-sm">
                                Category
                                <select
                                    className="h-10 rounded-lg border bg-background px-3"
                                    value={form.data.category}
                                    onChange={(event) =>
                                        form.setData(
                                            'category',
                                            event.target
                                                .value as TemplateDraft['category'],
                                        )
                                    }
                                >
                                    <option value="UTILITY">Utility</option>
                                    <option value="MARKETING">Marketing</option>
                                </select>
                            </label>
                            <label className="grid gap-1 text-sm md:col-span-2">
                                Message body
                                <textarea
                                    className="min-h-28 rounded-lg border bg-background p-3"
                                    value={form.data.body}
                                    onChange={(event) =>
                                        form.setData('body', event.target.value)
                                    }
                                    placeholder="Thanks for visiting {{customer_name}} ..."
                                    required
                                />
                                {form.errors.body && (
                                    <span className="text-destructive">
                                        {form.errors.body}
                                    </span>
                                )}
                            </label>
                            <fieldset className="grid gap-3 md:col-span-2 md:grid-cols-2">
                                <legend className="mb-2 text-sm font-medium">
                                    Example values required for variables used
                                    in the body
                                </legend>
                                {allowedVariables.map((variable) => (
                                    <label
                                        className="grid gap-1 text-sm"
                                        key={variable}
                                    >
                                        {`{{${variable}}}`}
                                        <input
                                            className="h-10 rounded-lg border bg-background px-3"
                                            value={
                                                form.data.examples[variable] ??
                                                ''
                                            }
                                            onChange={(event) =>
                                                form.setData('examples', {
                                                    ...form.data.examples,
                                                    [variable]:
                                                        event.target.value,
                                                })
                                            }
                                        />
                                        {form.errors[
                                            `examples.${variable}` as keyof typeof form.errors
                                        ] && (
                                            <span className="text-destructive">
                                                {
                                                    form.errors[
                                                        `examples.${variable}` as keyof typeof form.errors
                                                    ]
                                                }
                                            </span>
                                        )}
                                    </label>
                                ))}
                            </fieldset>
                            <div className="md:col-span-2 rounded-xl border bg-muted/30 p-4">
                                <h3 className="text-sm font-medium">
                                    Safe preview
                                </h3>
                                <p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">
                                    {form.data.body.trim()
                                        ? previewDraft(
                                              form.data.body,
                                              form.data.examples,
                                          )
                                        : 'Your message preview will appear here.'}
                                </p>
                            </div>
                            <div className="flex items-center gap-3 md:col-span-2">
                                <button
                                    disabled={form.processing}
                                    className="rounded-lg bg-primary px-4 py-2 text-sm font-medium text-primary-foreground"
                                >
                                    {form.processing
                                        ? 'Saving…'
                                        : editingTemplate
                                          ? 'Save draft changes'
                                          : 'Save draft'}
                                </button>
                                {form.errors.name && (
                                    <span className="text-sm text-destructive">
                                        {form.errors.name}
                                    </span>
                                )}
                            </div>
                        </form>
                    </section>
                )}

                <section className="overflow-hidden rounded-2xl border bg-card">
                    <div className="border-b p-5">
                        <h2 className="font-semibold">
                            Templates and provider approval status
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Provider status is shown exactly as stored after
                            synchronization.
                        </p>
                    </div>
                    {templates.length === 0 ? (
                        <p className="p-6 text-sm text-muted-foreground">
                            No templates have been added or synchronized.
                        </p>
                    ) : (
                        <div className="divide-y">
                            {templates.map((template) => {
                                const component = bodyComponent(template);
                                const positions = numberedVariables(
                                    component?.text ?? '',
                                );
                                const canEditDraft =
                                    permissions.update &&
                                    template.status === 'draft' &&
                                    template.provider_template_id === null;
                                const canMap =
                                    permissions.update &&
                                    template.provider_template_id !== null &&
                                    positions.length > 0;

                                return (
                                    <article
                                        className="space-y-4 p-5"
                                        key={template.id}
                                    >
                                        <div className="flex flex-wrap items-start justify-between gap-4">
                                            <div className="min-w-0">
                                                <h3 className="font-medium">
                                                    {template.name}{' '}
                                                    <span className="text-sm text-muted-foreground">
                                                        ({template.language})
                                                    </span>
                                                </h3>
                                                <p className="mt-1 text-sm text-muted-foreground">
                                                    {template.purpose ??
                                                        'Imported from Meta'}{' '}
                                                    · local {template.status} ·
                                                    provider{' '}
                                                    {template.provider_status ??
                                                        'status unavailable'}{' '}
                                                    ·{' '}
                                                    {template.is_enabled
                                                        ? 'enabled'
                                                        : 'disabled'}
                                                </p>
                                                {template.provider_template_id && (
                                                    <p className="mt-1 break-all text-xs text-muted-foreground">
                                                        Provider template ID:{' '}
                                                        {
                                                            template.provider_template_id
                                                        }
                                                    </p>
                                                )}
                                            </div>
                                            <div className="flex flex-wrap gap-2">
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setPreviewTemplateId(
                                                            previewTemplateId ===
                                                                template.id
                                                                ? null
                                                                : template.id,
                                                        )
                                                    }
                                                    className="rounded-lg border px-3 py-2 text-sm"
                                                >
                                                    {previewTemplateId ===
                                                    template.id
                                                        ? 'Hide preview'
                                                        : 'Preview'}
                                                </button>
                                                {canEditDraft && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            startEditing(
                                                                template,
                                                            )
                                                        }
                                                        className="rounded-lg border px-3 py-2 text-sm"
                                                    >
                                                        Edit draft
                                                    </button>
                                                )}
                                                {canMap && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            openMapping(
                                                                template,
                                                            )
                                                        }
                                                        className="rounded-lg border px-3 py-2 text-sm"
                                                    >
                                                        Map variables
                                                    </button>
                                                )}
                                                {permissions.sync &&
                                                    template.status ===
                                                        'draft' &&
                                                    template.provider_template_id ===
                                                        null && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                submitToMeta(
                                                                    template,
                                                                )
                                                            }
                                                            className="rounded-lg border px-3 py-2 text-sm"
                                                        >
                                                            Submit to Meta
                                                        </button>
                                                    )}
                                                {permissions.update && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleEnabled(
                                                                template,
                                                            )
                                                        }
                                                        disabled={
                                                            template.provider_status?.toUpperCase() !==
                                                                'APPROVED' ||
                                                            template.provider_template_id ===
                                                                null
                                                        }
                                                        className="rounded-lg border px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        {template.is_enabled
                                                            ? 'Disable'
                                                            : 'Enable'}
                                                    </button>
                                                )}
                                            </div>
                                        </div>
                                        {previewTemplateId === template.id && (
                                            <div
                                                className="rounded-xl border bg-muted/30 p-4"
                                                aria-live="polite"
                                            >
                                                <h4 className="text-sm font-medium">
                                                    Message preview
                                                </h4>
                                                <p className="mt-2 whitespace-pre-wrap text-sm text-muted-foreground">
                                                    {template.status === 'draft'
                                                        ? previewDraft(
                                                              draftData(
                                                                  template,
                                                              ).body,
                                                              draftData(
                                                                  template,
                                                              ).examples,
                                                          )
                                                        : previewProviderTemplate(
                                                              template,
                                                          )}
                                                </p>
                                                <p className="mt-2 text-xs text-muted-foreground">
                                                    Preview only. It does not
                                                    send a message or confirm
                                                    provider approval.
                                                </p>
                                            </div>
                                        )}
                                        {mappingTemplate?.id ===
                                            template.id && (
                                            <form
                                                className="grid gap-3 rounded-xl border p-4 sm:grid-cols-2"
                                                onSubmit={saveMapping}
                                            >
                                                <div className="sm:col-span-2">
                                                    <h4 className="text-sm font-medium">
                                                        Map approved template
                                                        parameters
                                                    </h4>
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Each positional
                                                        parameter must map to
                                                        one supported CRM value.
                                                    </p>
                                                </div>
                                                {positions.map(
                                                    (position, index) => (
                                                        <label
                                                            className="grid gap-1 text-sm"
                                                            key={position}
                                                        >
                                                            Meta parameter{' '}
                                                            {`{{${position}}}`}
                                                            <select
                                                                className="h-10 rounded-lg border bg-background px-3"
                                                                value={
                                                                    mappingForm
                                                                        .data
                                                                        .variables[
                                                                        index
                                                                    ] ?? ''
                                                                }
                                                                onChange={(
                                                                    event,
                                                                ) =>
                                                                    mappingForm.setData(
                                                                        'variables',
                                                                        mappingForm.data.variables.map(
                                                                            (
                                                                                value,
                                                                                currentIndex,
                                                                            ) =>
                                                                                currentIndex ===
                                                                                index
                                                                                    ? event
                                                                                          .target
                                                                                          .value
                                                                                    : value,
                                                                        ),
                                                                    )
                                                                }
                                                                required
                                                            >
                                                                <option
                                                                    value=""
                                                                    disabled
                                                                >
                                                                    Select a
                                                                    value
                                                                </option>
                                                                {allowedVariables.map(
                                                                    (
                                                                        variable,
                                                                    ) => (
                                                                        <option
                                                                            value={
                                                                                variable
                                                                            }
                                                                            key={
                                                                                variable
                                                                            }
                                                                        >
                                                                            {variable.replaceAll(
                                                                                '_',
                                                                                ' ',
                                                                            )}
                                                                        </option>
                                                                    ),
                                                                )}
                                                            </select>
                                                        </label>
                                                    ),
                                                )}
                                                {mappingForm.errors
                                                    .variables && (
                                                    <p className="text-sm text-destructive sm:col-span-2">
                                                        {
                                                            mappingForm.errors
                                                                .variables
                                                        }
                                                    </p>
                                                )}
                                                <div className="flex gap-2 sm:col-span-2">
                                                    <button
                                                        disabled={
                                                            mappingForm.processing
                                                        }
                                                        className="rounded-lg bg-primary px-3 py-2 text-sm text-primary-foreground"
                                                    >
                                                        Save mapping
                                                    </button>
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            setMappingTemplate(
                                                                null,
                                                            )
                                                        }
                                                        className="rounded-lg border px-3 py-2 text-sm"
                                                    >
                                                        Cancel
                                                    </button>
                                                </div>
                                            </form>
                                        )}
                                    </article>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
        </main>
    );
}
