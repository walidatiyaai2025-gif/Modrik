<?php

namespace App\Http\Controllers;

use App\Filament\Pages\SystemUpdates;
use App\Models\SystemUpdateHistory;
use App\Services\Updates\UnifiedPackageValidator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class SystemUpdateUploadController extends Controller
{
    public function __invoke(Request $request, UnifiedPackageValidator $validator): RedirectResponse
    {
        abort_unless(SystemUpdates::canAccess(), 403);

        $maxPackageKb = (int) config('updates.max_package_kb', 131072);
        $validated = $request->validate([
            'package' => ['required', 'file', 'max:'.$maxPackageKb],
        ]);

        $package = $validated['package'];
        $key = Str::random(48).'.zip';
        $directory = trim((string) config('updates.upload_directory', 'system-updates/uploads'), '/');
        $disk = (string) config('updates.upload_disk', 'local');
        $stored = null;

        try {
            $stored = $package->storeAs($directory, $key, $disk);
            if (! is_string($stored)) {
                return redirect(SystemUpdates::getUrl())->withErrors([
                    'package' => 'The package could not be placed in private update storage.',
                ]);
            }

            $result = $validator->validate(
                Storage::disk($disk)->path($stored),
                (string) config('app.version', '0.1.0'),
            );

            $history = SystemUpdateHistory::query()->create([
                'initiated_by' => auth()->id(),
                'from_version' => (string) config('app.version', '0.1.0'),
                'to_version' => $result->manifest['version'] ?? null,
                'release_sha' => $result->manifest['release_sha'] ?? null,
                'status' => $result->valid ? 'validated' : 'validation_failed',
                'package_storage_key' => $result->valid ? $key : null,
                'safe_details' => [
                    'errors' => array_map(
                        fn (array $error): array => [
                            'code' => $error['code'],
                            'path' => $error['path'] ?? null,
                        ],
                        $result->errors,
                    ),
                ],
                'completed_at' => $result->valid ? null : now(),
            ]);

            if (! $result->valid) {
                Storage::disk($disk)->delete($stored);
            }

            $response = redirect(SystemUpdates::getUrl())
                ->with('modrik.update.validation_result', $result->toArray());

            if ($result->valid) {
                $response->with('modrik.update.validated_update_id', (string) $history->getKey());
            }

            return $response;
        } catch (Throwable) {
            if (is_string($stored)) {
                Storage::disk($disk)->delete($stored);
            }

            return redirect(SystemUpdates::getUrl())->withErrors([
                'package' => 'The update package could not be validated safely. Please retry.',
            ]);
        }
    }
}
