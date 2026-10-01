<?php

namespace Leantime\Plugins\LeanLib\Services;

use Closure;
use Illuminate\Http\Request;
use Leantime\Domain\Plugins\Permissions\PluginsPermissions;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends Leantime's native plugin manager and marketplace UI to the Library manager.
 *
 * The redirect also prevents native GET activation links and marketplace HTMX install
 * actions from bypassing Library preflight while lean-lib is enabled.
 */
class PluginManagementRedirect
{
    public function handle(Request $request, Closure $next): Response
    {
        $path = $request->getPathInfo();
        $isNativeManager = preg_match('#^/plugins/(?:myapps|marketplace|details)(?:/|$)#i', $path) === 1;
        $isMarketplaceInstall = preg_match('#^/hx/plugins/details/install(?:/|$)#i', $path) === 1;

        if (($isNativeManager || $isMarketplaceInstall)
            && function_exists('can')
            && can(PluginsPermissions::MANAGE)) {
            return new RedirectResponse(rtrim(BASE_URL, '/').'/setting/editCompanySettings#integrations');
        }

        return $next($request);
    }
}
