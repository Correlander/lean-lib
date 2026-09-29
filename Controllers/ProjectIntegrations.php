<?php

namespace Leantime\Plugins\LeantimeLib\Controllers;

use Leantime\Core\Auth\Permissions\RequiresPermission;
use Leantime\Domain\Projects\Permissions\ProjectsPermissions;
use Leantime\Plugins\LeantimeLib\Services\ProjectIntegrationRegistry;

class ProjectIntegrations
{
    public function __construct(private ProjectIntegrationRegistry $registry) {}

    #[RequiresPermission(ProjectsPermissions::VIEW, projectIdParam: 'projectId')]
    public function show(int $projectId)
    {
        return response()->json(['html' => $this->registry->renderPanels($projectId)]);
    }
}
