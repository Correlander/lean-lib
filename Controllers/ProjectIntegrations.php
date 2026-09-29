<?php

namespace Leantime\Plugins\LeantimeLib\Controllers;

use Illuminate\Support\Facades\Log;
use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Domain\Projects\Permissions\ProjectsPermissions;
use Leantime\Plugins\LeantimeLib\Services\ProjectIntegrationRegistry;
use Throwable;

class ProjectIntegrations
{
    public function __construct(private ProjectIntegrationRegistry $registry) {}

    #[RequiresPermission(ProjectsPermissions::VIEW, projectIdParam: 'projectId')]
    public function show(int $projectId)
    {
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
