<?php

namespace Webkul\Installer\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Webkul\Installer\Helpers\DatabaseManager;

class CanInstall
{
    /**
     * Handles Requests for Installer middleware.
     *
     * @return void
     */
    public function handle(Request $request, Closure $next)
    {
        /**
         * TEMPORARY — EmpireMart production bootstrap. REMOVE ME.
         *
         * The short-lived, token-protected bootstrap routes under
         * `/__empire/*` (see `routes/web.php`) must stay reachable both
         * before and after installation for verification, so they bypass
         * this guard entirely. They protect themselves; every other
         * request keeps the standard installer behaviour.
         */
        if (Str::startsWith(trim($request->decodedPath(), '/'), '__empire/')) {
            return $next($request);
        }

        if ($this->isAlreadyInstalled()) {
            if ($this->isInstallerRequest($request)) {
                if (! $request->ajax()) {
                    return redirect()->route('shop.home.index');
                }

                return response()->json([
                    'message' => trans('installer::app.installer.middleware.already-installed'),
                ], 403);
            }
        } elseif (! $this->isInstallerRequest($request)) {
            return redirect()->route('installer.index');
        }

        return $next($request);
    }

    /**
     * Application Already Installed.
     *
     * The `storage/installed` marker file is only a fast-path cache. The
     * database check performed by the `DatabaseManager` is authoritative:
     * on runtimes with an ephemeral filesystem (Vercel serverless
     * functions, containers without a persistent volume) the marker file
     * disappears on every cold start while the database survives. Falling
     * back to the database therefore keeps an installed application from
     * ever redirecting to the installer again, regardless of the state of
     * the local filesystem.
     */
    public function isAlreadyInstalled(): bool
    {
        if (file_exists(storage_path('installed'))) {
            return true;
        }

        if (! app(DatabaseManager::class)->isInstalled()) {
            return false;
        }

        /**
         * Persistence of the marker is best-effort: it may fail on
         * read-only filesystems, which must never break the response.
         * The installation event only fires when the marker is actually
         * (re)created so it is not re-dispatched on every request on
         * ephemeral runtimes.
         */
        if (@touch(storage_path('installed'))) {
            Event::dispatch('bagisto.installed');
        }

        return true;
    }

    /**
     * Whether the request targets the installer.
     *
     * The comparison is made on the decoded path. The router resolves a percent-encoded path such
     * as `/%69nstall` to the installer, so matching the raw path here would let it slip past the
     * guard on an installed site.
     */
    protected function isInstallerRequest(Request $request): bool
    {
        $path = trim($request->decodedPath(), '/');

        return $path === 'install'
            || Str::startsWith($path, 'install/');
    }
}
