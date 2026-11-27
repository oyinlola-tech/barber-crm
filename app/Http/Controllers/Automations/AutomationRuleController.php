<?php

namespace App\Http\Controllers\Automations;

use App\Http\Controllers\Controller;
use App\Services\Automations\Commands\CreateAutomationRuleCommand;
use App\Services\Automations\Commands\SetAutomationRuleStatusCommand;
use App\Services\Automations\Commands\UpdateAutomationRuleCommand;
use App\Services\Automations\Queries\ListAutomationRulesQuery;
use App\Services\Templates\Queries\ListMessageTemplatesQuery;
use App\Validators\Automations\StoreAutomationRuleRequest;
use App\Validators\Automations\UpdateAutomationRuleRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AutomationRuleController extends Controller
{
    public function index(Request $request, ListAutomationRulesQuery $query, ListMessageTemplatesQuery $templates): Response
    {
        $businessId = $request->attributes->get('currentBusiness')->getKey();
        abort_unless($request->user()->hasBusinessPermission((int) $businessId, 'automations.view'), 403);
        $canViewTemplates = $request->user()->hasBusinessPermission((int) $businessId, 'templates.view');

        return Inertia::render('automations/index', [
            'rules' => $query->execute((int) $businessId),
            'templates' => $canViewTemplates ? $templates->execute((int) $businessId) : [],
            'capabilities' => [
                'create' => $canViewTemplates && $request->user()->hasBusinessPermission((int) $businessId, 'automations.create'),
                'update' => $canViewTemplates && $request->user()->hasBusinessPermission((int) $businessId, 'automations.update'),
                'enable' => $request->user()->hasBusinessPermission((int) $businessId, 'automations.enable'),
                'disable' => $request->user()->hasBusinessPermission((int) $businessId, 'automations.disable'),
            ],
        ]);
    }

    public function store(StoreAutomationRuleRequest $request, CreateAutomationRuleCommand $command): RedirectResponse
    {
        $command->execute($request->attributes->get('currentBusiness')->getKey(), $request->validated(), $request->user()->getKey());

        return to_route('automations.index')->with('success', 'Automation rule created.');
    }

    public function update(
        UpdateAutomationRuleRequest $request,
        int $automationRule,
        SetAutomationRuleStatusCommand $statusCommand,
        UpdateAutomationRuleCommand $updateCommand,
    ): RedirectResponse {
        $businessId = (int) $request->attributes->get('currentBusiness')->getKey();
        $attributes = $request->validated();
        if (count($attributes) === 1 && array_key_exists('is_enabled', $attributes)) {
            $statusCommand->execute($businessId, $automationRule, $request->boolean('is_enabled'), $request->user()->getKey());
        } else {
            $updateCommand->execute($businessId, $automationRule, (int) $request->user()->getKey(), $attributes);
        }

        return back()->with('success', 'Automation rule updated.');
    }
}
