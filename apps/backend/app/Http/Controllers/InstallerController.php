<?php

namespace App\Http\Controllers;

use App\Services\InstallerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

final class InstallerController extends Controller
{
    public function show(): View
    {
        return view('installer.wizard', ['requirements' => $this->requirements()]);
    }

    public function submit(Request $request, InstallerService $installer): RedirectResponse
    {
        $data = $request->validate(['db_host' => ['required', 'string', 'max:255'], 'db_port' => ['required', 'integer', 'between:1,65535'], 'db_database' => ['required', 'string', 'max:128'], 'db_username' => ['required', 'string', 'max:128'], 'db_password' => ['present', 'string', 'max:512'], 'app_url' => ['required', 'url', 'max:2048'], 'web_url' => ['required', 'url', 'max:2048'], 'admin_email' => ['required', 'email', 'max:255'], 'admin_password' => ['required', 'string', 'min:12', 'max:255', 'regex:/[a-z]/', 'regex:/[A-Z]/', 'regex:/[0-9]/'], 'release_sha' => ['required', 'regex:/^[0-9a-f]{40}$/i']]);
        try {
            $installer->install($data);

            return redirect('/admin/login')->with('status', 'MODRIK installation completed.');
        } catch (Throwable) {
            return back()->withInput($request->except(['db_password', 'admin_password']))->withErrors(['install' => 'Installation failed safely. Configuration passwords were not retained; verify the database/runtime and retry.']);
        }
    }

    /** @return array<string,bool> */
    private function requirements(): array
    {
        return ['php_8_4' => version_compare(PHP_VERSION, '8.4.0', '>='), 'pdo_mysql' => extension_loaded('pdo_mysql'), 'mbstring' => extension_loaded('mbstring'), 'openssl' => extension_loaded('openssl'), 'zip' => extension_loaded('zip'), 'storage_writable' => is_writable(storage_path())];
    }
}
