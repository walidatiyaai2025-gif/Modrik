<?php

namespace App\Filament\Pages;

use App\Filament\Support\AdminNavigationGroup;
use App\Models\SystemUpdateHistory;
use App\Models\User;
use App\Services\Updates\UnifiedPackageValidator;
use Filament\Pages\Page;
use Illuminate\Support\Facades\App;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
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

    public static function canAccess(): bool { $user = auth()->user(); return $user instanceof User && $user->role === 'admin' && $user->account_status === 'active' && $user->deleted_at === null; }
    public static function getNavigationLabel(): string { return match (App::getLocale()) { 'ar' => 'تحديثات النظام', 'fr' => 'Mises à jour système', default => 'System Updates' }; }
    public static function getNavigationSort(): int { return 7; }
    public function getTitle(): string { return self::getNavigationLabel(); }

    public function validatePackage(UnifiedPackageValidator $validator): void
    {
        $this->validate(['package' => ['required', 'file', 'max:524288']]);
        $result = $validator->validate($this->package->getRealPath(), $this->currentVersion());
        $this->validationResult = $result->toArray();
        SystemUpdateHistory::query()->create([
            'initiated_by' => auth()->id(), 'from_version' => $this->currentVersion(),
            'to_version' => $result->manifest['version'] ?? null, 'release_sha' => $result->manifest['release_sha'] ?? null,
            'status' => $result->valid ? 'validated' : 'validation_failed',
            'safe_details' => ['errors' => array_map(fn (array $e) => ['code' => $e['code'], 'path' => $e['path'] ?? null], $result->errors)],
        ]);
        $this->package->delete(); $this->package = null;
    }

    /** @return array<string,mixed> */
    protected function getViewData(): array { return ['currentVersion' => $this->currentVersion(), 'releaseSha' => $this->releaseSha(), 'history' => SystemUpdateHistory::query()->latest()->limit(20)->get()]; }
    private function currentVersion(): string { return (string) config('app.version', '0.1.0'); }
    private function releaseSha(): string { $path = storage_path('app/modrik-release.txt'); $sha = is_readable($path) ? trim((string) file_get_contents($path)) : ''; return preg_match('/^[0-9a-f]{40}$/i', $sha) ? strtolower($sha) : 'not-recorded'; }
}
