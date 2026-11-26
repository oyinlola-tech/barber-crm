<?php

namespace App\Http\Controllers\Templates;

use App\Http\Controllers\Controller;
use App\Services\Templates\Commands\ConfigureMessageTemplateVariablesCommand;
use App\Services\Templates\Commands\CreateMessageTemplateDraftCommand;
use App\Services\Templates\Commands\DeleteMessageTemplateDraftCommand;
use App\Services\Templates\Commands\SetMessageTemplateEnabledCommand;
use App\Services\Templates\Commands\SubmitMessageTemplateCommand;
use App\Services\Templates\Commands\SynchronizeMessageTemplatesCommand;
use App\Services\Templates\Commands\UpdateMessageTemplateDraftCommand;
use App\Services\Templates\Queries\ListMessageTemplatesQuery;
use App\Validators\Templates\ConfigureMessageTemplateVariablesRequest;
use App\Validators\Templates\DeleteMessageTemplateDraftRequest;
use App\Validators\Templates\MessageTemplateDraftRequest;
use App\Validators\Templates\SetMessageTemplateEnabledRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class MessageTemplateController extends Controller
{
    public function index(Request $request, ListMessageTemplatesQuery $query): Response
    {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission($businessId, 'templates.view'), 403);

        return Inertia::render('templates/index', [
            'templates' => $query->execute($businessId),
            'allowedVariables' => config('automation.allowed_template_variables', []),
            'permissions' => [
                'create' => $request->user()->hasBusinessPermission($businessId, 'templates.create'),
                'update' => $request->user()->hasBusinessPermission($businessId, 'templates.update'),
                'sync' => $request->user()->hasBusinessPermission($businessId, 'templates.sync'),
                'delete' => $request->user()->hasBusinessPermission($businessId, 'templates.delete'),
            ],
        ]);
    }

    public function store(MessageTemplateDraftRequest $request, CreateMessageTemplateDraftCommand $command): RedirectResponse
    {
        $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), (int) $request->user()->getKey(), $request->validated());

        return back()->with('success', 'Local template draft created. Submit it to Meta for approval before using it.');
    }

    public function update(MessageTemplateDraftRequest $request, int $template, UpdateMessageTemplateDraftCommand $command): RedirectResponse
    {
        $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), $template, (int) $request->user()->getKey(), $request->validated());

        return back()->with('success', 'Template draft updated.');
    }

    public function destroy(DeleteMessageTemplateDraftRequest $request, int $template, DeleteMessageTemplateDraftCommand $command): RedirectResponse
    {
        $command->execute(
            (int) $request->attributes->get('currentBusiness')->getKey(),
            $template,
            (int) $request->user()->getKey(),
        );

        return back()->with('success', 'Local template draft deleted.');
    }

    public function submit(Request $request, int $template, SubmitMessageTemplateCommand $command): RedirectResponse
    {
        $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), $template, (int) $request->user()->getKey());

        return back()->with('success', 'Template submitted. Synchronize later to confirm Meta approval.');
    }

    public function synchronize(Request $request, SynchronizeMessageTemplatesCommand $command): RedirectResponse
    {
        $count = $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), (int) $request->user()->getKey());

        return back()->with('success', "Synchronized {$count} templates from Meta.");
    }

    public function enabled(SetMessageTemplateEnabledRequest $request, int $template, SetMessageTemplateEnabledCommand $command): RedirectResponse
    {
        $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), $template, (int) $request->user()->getKey(), $request->boolean('is_enabled'));

        return back()->with('success', 'Template sending status updated.');
    }

    public function variables(ConfigureMessageTemplateVariablesRequest $request, int $template, ConfigureMessageTemplateVariablesCommand $command): RedirectResponse
    {
        $command->execute((int) $request->attributes->get('currentBusiness')->getKey(), $template, (int) $request->user()->getKey(), $request->validated('variables'));

        return back()->with('success', 'Template variable mapping updated.');
    }
}
