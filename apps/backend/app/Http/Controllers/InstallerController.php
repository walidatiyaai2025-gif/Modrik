<?php

namespace App\Http\Controllers;

use App\Services\InstallationStateService;
use App\Services\InstallerRequirements;
use App\Services\InstallerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class InstallerController extends Controller
{
    public function show(InstallerRequirements $requirements): View
    {
        return view('installer.wizard', ['requirements' => $requirements->current()]);
    }

    public function submit(Request $request, InstallerService $installer, InstallerRequirements $requirements, InstallationStateService $state): RedirectResponse
    {
        if (! $requirements->passes($requirements->current())) {
            return back()->withInput($request->except(['db_password', 'admin_password']))->withErrors(['install' => 'Server requirements must pass before installation can start.']);
        }
        $data = $request->validate(['db_host' => ['required', 'string', 'max:255'], 'db_port' => ['required', 'integer', 'between:1,65535'], 'db_database' => ['required', 'string', 'max:128'], 'db_username' => ['required', 'string', 'max:128'], 'db_password' => ['present', 'string', 'max:512'], 'app_url' => ['required', 'url', 'max:2048'], 'web_url' => ['required', 'url', 'max:2048'], 'admin_email' => ['required', 'email', 'max:255'], 'admin_password' => ['required', 'string', 'min:12', 'max:255', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'], 'release_sha' => ['required', 'regex:/^[0-9a-f]{40}$/i']]);
        try {
            $installer->install($data);
        } catch (Throwable) {
            return back()->withInput($request->except(['db_password', 'admin_password']))->withErrors(['install' => 'Installation failed safely. Configuration passwords were not retained; verify the database/runtime and retry.']);
        }

        try {
            return redirect()->route('installer.finish', ['token' => $state->issueCompletionToken()]);
        } catch (Throwable) {
            return redirect('/admin/login')->with('status', 'MODRIK installation completed and the installer is locked.');
        }
    }

    public function finish(Request $request, InstallationStateService $state): View|RedirectResponse
    {
        $token = $request->query('token');
        if (! $state->installed() || ! $state->consumeCompletionToken(is_string($token) ? $token : null)) {
            return redirect('/admin/login');
        }

        return view('installer.finish');
    }
}
