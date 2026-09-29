<?php

namespace Leantime\Plugins\LeantimeLib\Controllers;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Core\Auth\Permissions\PermissionService;
use Leantime\Domain\Projects\Permissions\ProjectsPermissions;
use Leantime\Plugins\LeantimeLib\Services\ProjectIntegrationRegistry;
use Throwable;

class ProjectIntegrations
{
    private PermissionService $permissions;

    public function __construct(private ProjectIntegrationRegistry $registry) {}

    public function init(PermissionService $permissions): void
    {
        $this->permissions = $permissions;
    }

    #[RequiresPermission(ProjectsPermissions::VIEW, entityScoped: true)]
    public function show(int $projectId)
    {
        // Native route permission middleware receives request input but not URL path parameters.
        // Authorize against the actual routed project ID rather than accepting a duplicate query value.
        $this->permissions->authorize(ProjectsPermissions::VIEW, $projectId);
        try {
            return response()->json(['html' => $this->registry->renderPanels($projectId)]);
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
}
