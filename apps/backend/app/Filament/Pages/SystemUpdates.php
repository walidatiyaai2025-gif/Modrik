<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\SystemUpdateHistory;
use App\Models\User;
use App\Services\Updates\TransactionalReleaseManager;
use App\Services\Updates\UnifiedPackageValidator;
use App\Services\Updates\UpdateExecutionResult;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use RuntimeException;
use Throwable;
use UnitEnum;

final class SystemUpdates extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.system-updates';

    protected static ?string $slug = 'system-updates';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Operations;

    public ?TemporaryUploadedFile $package = null;

    /** @var array<string,mixed>|null */
    public ?array $validationResult = null;

    public ?string $validatedUpdateId = null;

    /** @var array<string,mixed>|null */
    public ?array $installationResult = null;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->role === 'admin' && $user->account_status === 'active' && $user->deleted_at === null;
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'تحديثات النظام', 'fr' => 'Mises à jour système', default => 'System Updates'
        };
    }

    public static function getNavigationSort(): int
    {
        return 7;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function validatePackage(UnifiedPackageValidator $validator): void
    {
        $maxPackageKb = (int) config('updates.max_package_kb', 131072);
        $this->validate(['package' => ['required', 'file', 'max:'.$maxPackageKb]]);
        $package = $this->package;
        if (! $package instanceof TemporaryUploadedFile) {
            $this->addError('package', 'The temporary upload is no longer available. Please choose the package again.');

            return;
        }
        $key = Str::random(48).'.zip';
        $directory = (string) config('updates.upload_directory', 'system-updates/uploads');
        $disk = (string) config('updates.upload_disk', 'local');
        $stored = $package->storeAs($directory, $key, $disk);
        $package->delete();
        $this->package = null;
        if (! is_string($stored)) {
            $this->addError('package', 'The package could not be placed in private update storage.');

            return;
        }

        $archive = Storage::disk($disk)->path($stored);
        $result = $validator->validate($archive, $this->currentVersion());
        $this->validationResult = $result->toArray();
        $history = SystemUpdateHistory::query()->create([
            'initiated_by' => auth()->id(), 'from_version' => $this->currentVersion(),
            'to_version' => $result->manifest['version'] ?? null, 'release_sha' => $result->manifest['release_sha'] ?? null,
            'status' => $result->valid ? 'validated' : 'validation_failed',
            'package_storage_key' => $result->valid ? $key : null,
            'safe_details' => ['errors' => array_map(fn (array $e) => ['code' => $e['code'], 'path' => $e['path'] ?? null], $result->errors)],
            'completed_at' => $result->valid ? null : now(),
        ]);
        if ($result->valid) {
            $this->validatedUpdateId = (string) $history->getKey();
        } else {
            Storage::disk($disk)->delete($stored);
            $this->validatedUpdateId = null;
        }
    }

    public function installUpdate(TransactionalReleaseManager $manager): void
    {
        if (! self::canAccess()) {
            abort(403);
        }
        if (! is_string($this->validatedUpdateId)) {
            throw new RuntimeException('validated_update_required');
        }

        $history = SystemUpdateHistory::query()
            ->whereKey($this->validatedUpdateId)
            ->where('initiated_by', auth()->id())
            ->where('status', 'validated')
            ->firstOrFail();
        $key = $history->package_storage_key;
        if (! is_string($key) || preg_match('/^[A-Za-z0-9]{48}\.zip$/', $key) !== 1) {
            throw new RuntimeException('validated_package_missing');
        }

        $disk = (string) config('updates.upload_disk', 'local');
        $relative = trim((string) config('updates.upload_directory', 'system-updates/uploads'), '/').'/'.$key;
        $history->update(['status' => 'installing', 'started_at' => now(), 'safe_details' => ['phase' => 'lock_and_stage']]);

        try {
            $result = $manager->install(
                Storage::disk($disk)->path($relative),
                (string) config('updates.runtime_root'),
                $this->currentVersion(),
            );
            $this->installationResult = $result->toArray();
            $history->update([
                'status' => $result->status,
                'safe_details' => $result->details,
                'completed_at' => now(),
                'package_storage_key' => null,
            ]);
        } catch (Throwable $exception) {
            $code = $exception instanceof RuntimeException ? $exception->getMessage() : 'unexpected_update_failure';
            $safeCode = in_array($code, ['concurrent_update', 'package_validation_failed', 'stage_failed', 'candidate_move_failed', 'current_backup_failed', 'activation_failed'], true)
                ? $code : 'unexpected_update_failure';
            $this->installationResult = ['status' => UpdateExecutionResult::FAILED, 'details' => ['code' => $safeCode]];
            $history->update(['status' => UpdateExecutionResult::FAILED, 'safe_details' => ['code' => $safeCode], 'completed_at' => now(), 'package_storage_key' => null]);
        } finally {
            Storage::disk($disk)->delete($relative);
            $this->validatedUpdateId = null;
            $this->validationResult = null;
        }
    }

    /** @return array<string,mixed> */
    protected function getViewData(): array
    {
        return [
            'currentVersion' => $this->currentVersion(),
            'releaseSha' => $this->releaseSha(),
            'maxPackageMb' => round(((int) config('updates.max_package_kb', 131072)) / 1024),
            'history' => SystemUpdateHistory::query()->latest()->limit(20)->get(),
        ];
    }

    private function currentVersion(): string
    {
        return (string) config('app.version', '0.1.0');
    }

    private function releaseSha(): string
    {
        $path = storage_path('app/modrik-release.txt');
        $sha = is_readable($path) ? trim((string) file_get_contents($path)) : '';

        return preg_match('/^[0-9a-f]{40}$/i', $sha) ? strtolower($sha) : 'not-recorded';
    }
}
