<?php

namespace Leantime\Plugins\LeantimeLib\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Auth\Permissions\PermissionService;
use Leantime\Core\Exceptions\ValidationException;
use Leantime\Domain\Projects\Permissions\ProjectsPermissions;
use Leantime\Plugins\LeantimeLib\Services\ProjectIntegrationRegistry;
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
            $canEdit = $this->permissions->currentUserCan(ProjectsPermissions::EDIT, null, true);
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

    #[RequiresPermission(ProjectsPermissions::EDIT, global: true)]
    public function saveSectionVisibility(Request $request, int $projectId)
    {
        $this->permissions->authorize(ProjectsPermissions::EDIT, null, true);
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
}
