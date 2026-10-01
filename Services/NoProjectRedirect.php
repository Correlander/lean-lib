<?php

namespace Leantime\Plugins\LeanLib\Services;

use Closure;
use Illuminate\Http\Request;
use Leantime\Domain\Projects\Services\Projects;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/** Sends invalid or inaccessible starter-project links back to the dashboard. */
class NoProjectRedirect
{
    public function __construct(private Projects $projects) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (preg_match('~/projects/changeCurrentProject/(\d+)/?$~i', $request->getPathInfo(), $match)) {
            $userId = (int) session('userdata.id', 0);
            $projectId = (int) $match[1];
            if ($userId > 0 && ! $this->projects->isUserAssignedToProject($userId, $projectId)) {
                return new RedirectResponse(rtrim(BASE_URL, '/').'/dashboard/home');
            }
        }

        return $next($request);
    }
}
