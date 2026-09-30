<?php

namespace Leantime\Plugins\LeantimeLib\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Auth\Permissions\PermissionService;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Projects\Permissions\ProjectsPermissions;
use Leantime\Plugins\LeantimeLib\Services\ProjectIntegrationRegistry;
use Leantime\Plugins\LeantimeLib\Services\TodoFieldRegistry;
use Throwable;

class ProjectIntegrations
{
    public function __construct(
        private ProjectIntegrationRegistry $registry,
        private PermissionService $permissions,
    ) {}

    #[RequiresPermission(ProjectsPermissions::VIEW, entityScoped: true)]
    public function show(int $projectId)
    {
        // Native route permission middleware receives request input but not URL path parameters.
        // Authorize against the actual routed project ID rather than accepting a duplicate query value.
        $this->permissions->authorize(ProjectsPermissions::VIEW, $projectId);
        try {
            $canEdit = $this->permissions->currentUserCan(ProjectsPermissions::EDIT, $projectId);
            return response()->json(['html' => $this->registry->renderPanels($projectId, $canEdit)]);
        } catch (Throwable $exception) {
            Log::error('Leantime Library project integrations request failed.', [
                'project_id' => $projectId,
                'exception_class' => $exception::class,
                'exception_code' => (int) $exception->getCode(),
                'exception_file' => basename($exception->getFile()),
                'exception_line' => $exception->getLine(),
            ]);
            return response()->json(['error' => 'Project integrations could not be rendered.'], 500);
        }
    }

    // RouteLoader's permission middleware reads request input, not Laravel path parameters.
    // Defer its attribute check and authorize the actual routed project ID in this action.
    #[RequiresPermission(ProjectsPermissions::EDIT, entityScoped: true)]
    public function saveSectionVisibility(Request $request, int $projectId)
    {
        $this->permissions->authorize(ProjectsPermissions::EDIT, $projectId);
        $input = ValidationException::validate($request->only(['sectionId', 'enabled', 'useDefault']), [
            'sectionId' => ['required', 'string', 'max:120'],
            'enabled' => ['nullable', 'boolean'],
            'useDefault' => ['nullable', 'boolean'],
        ]);
        $sectionId = $input['sectionId'];
        $enabled = filter_var($input['useDefault'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ? null
            : filter_var($input['enabled'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
        $result = app(\Leantime\Plugins\LeantimeLib\Services\TodoSectionRegistry::class)
            ->setProjectSectionVisibility($projectId, $sectionId, $enabled);
        if ($result === null) return response()->json(['error' => 'That To-do section is not registered.'], 422);
        return response()->json($result);
    }

    #[RequiresPermission(ProjectsPermissions::EDIT, entityScoped: true)]
    public function savePanelOrder(Request $request, int $projectId)
    {
        $this->permissions->authorize(ProjectsPermissions::EDIT, $projectId);
        $input = ValidationException::validate($request->only(['order', 'useDefault']), [
            'order' => ['required', 'array'],
            'order.*' => ['required', 'string', 'max:80'],
            'useDefault' => ['nullable', 'boolean'],
        ]);
        $useDefault = filter_var($input['useDefault'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $saved = $this->registry->saveProjectOrder($projectId, $input['order'], $useDefault);
        if (! $saved) return response()->json(['error' => 'The project integration order could not be saved.'], 500);
        return response()->json(['saved' => true, 'useDefault' => $useDefault]);
    }

    // The project ID comes from the route path, so authorize it in the action body.
    #[RequiresPermission(ProjectsPermissions::EDIT, entityScoped: true)]
    public function saveTodoLayout(Request $request, int $projectId)
    {
        $this->permissions->authorize(ProjectsPermissions::EDIT, $projectId);
        try {
            $input = ValidationException::validate($request->only(['tabs', 'fields', 'reset']), [
                'tabs' => ['required', 'array'],
                'tabs.order' => ['present', 'array'],
                'tabs.order.*' => ['required', 'string', 'max:120'],
                'tabs.visible' => ['present', 'array'],
                'tabs.visible.*' => ['required', 'string', 'max:120'],
                'fields' => ['required', 'array'],
                'fields.main' => ['present', 'array'],
                'fields.main.*' => ['required', 'string', 'max:120'],
                'fields.auxiliary' => ['present', 'array'],
                'fields.auxiliary.*' => ['required', 'string', 'max:120'],
                'fields.sidebar' => ['present', 'array'],
                'fields.sidebar.*' => ['required', 'string', 'max:120'],
                'fields.hidden' => ['present', 'array'],
                'fields.hidden.*' => ['required', 'string', 'max:120'],
                'fields.groups' => ['present', 'array'],
                'fields.groups.*' => ['nullable', 'array'],
                'fields.groups.*.*' => ['required', 'string', 'max:120'],
                'reset' => ['nullable', 'boolean'],
            ]);
        } catch (ValidationException $exception) {
            return response()->json([
                'error' => $exception->getMessage(),
                'errors' => $exception->getErrorData(),
            ], 422);
        }

        if (! filter_var($input['reset'] ?? false, FILTER_VALIDATE_BOOLEAN) && $input['tabs']['visible'] === []) {
            return response()->json(['error' => 'Keep at least one To-do tab visible.'], 422);
        }

        $tabs = app(\Leantime\Plugins\LeantimeLib\Services\TodoTabRegistry::class);
        $sections = app(\Leantime\Plugins\LeantimeLib\Services\TodoSectionRegistry::class);
        $fields = app(TodoFieldRegistry::class);
        if (filter_var($input['reset'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            $tabs->resetProjectLayout($projectId);
            $sections->resetProjectLayout($projectId);
            $fields->resetProjectLayout($projectId);
            return response()->json(['saved' => true, 'reset' => true]);
        }

        $tabsSaved = $tabs->setProjectLayout($projectId, $input['tabs']['order'], $input['tabs']['visible']);
        $fieldsSaved = $fields->saveLayout($input['fields'], $projectId);
        if (! $tabsSaved || ! $fieldsSaved) {
            return response()->json(['error' => 'The project To-do layout could not be saved.'], 500);
        }
        return response()->json(['saved' => true]);
    }
}
