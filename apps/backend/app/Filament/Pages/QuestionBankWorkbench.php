<?php

namespace App\Filament\Pages;

use App\Exceptions\ApiProblemException;
use App\Filament\Support\AdminNavigationGroup;
use App\Models\User;
use App\Services\ContentAdminWorkflowService;
use App\Services\QuestionBankWorkbenchService;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Livewire\WithFileUploads;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

final class QuestionBankWorkbench extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.question-bank-workbench';

    protected static ?string $slug = 'question-bank-workbench';

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Content;

    public mixed $questionBankJson = null;

    public string $statusFilter = 'all';

    public ?string $selectedImportId = null;

    /** @var list<string> */
    public array $selectedImportIds = [];

    /** @var array<string, string> */
    public array $reasons = [];

    /** @var array<string, string> */
    public array $itemDifficulties = [];

    /** @var array<string, string> */
    public array $itemNodeIds = [];

    /** @var array<string, string> */
    public array $itemReasons = [];

    /** @var array<string, string> */
    public array $sourceKinds = [];

    /** @var array<string, string> */
    public array $sourceReferences = [];

    /** @var array<string, string> */
    public array $sourceRights = [];

    /** @var array<string, string> */
    public array $sourceNotes = [];

    public ?string $pendingBulkAction = null;

    public string $bulkReason = '';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && in_array((string) $user->role, ['admin', 'content_team'], true);
    }

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'منضدة بنك الأسئلة',
            'fr' => 'Atelier banque de questions',
            default => 'Question Bank Workbench',
        };
    }

    public static function getNavigationSort(): int
    {
        return 27;
    }

    public function getTitle(): string
    {
        return self::getNavigationLabel();
    }

    public function getSubheading(): string
    {
        return $this->text(
            'استيراد ومراجعة وربط ونشر Question Bank JSON الناتج من مسار ChatGPT اليدوي، بدون اعتماد AI مدفوع وقت التشغيل.',
            'Import, review, map, rights-clear and publish Question Bank JSON returned by the manual ChatGPT workflow, with no paid-AI runtime dependency.',
            'Importez, révisez, mappez, validez les droits et publiez le JSON Question Bank produit manuellement, sans IA payante au runtime.',
        );
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    public function setLocale(string $locale): void
    {
        if (! in_array($locale, ['ar', 'en', 'fr'], true)) {
            return;
        }

        session()->put('admin_locale', $locale);
        App::setLocale($locale);
    }

    public function upload(): void
    {
        $this->validate([
            'questionBankJson' => ['required', 'file', 'max:20480'],
        ]);
        if (! $this->questionBankJson instanceof UploadedFile) {
            $this->addError('questionBankJson', $this->text('ملف JSON مطلوب.', 'A JSON file is required.', 'Un fichier JSON est requis.'));

            return;
        }

        $path = $this->questionBankJson->getRealPath();
        $contents = $path === false ? false : file_get_contents($path);
        if (! is_string($contents)) {
            $this->addError('questionBankJson', $this->text('تعذر قراءة الملف.', 'The uploaded file could not be read.', 'Le fichier ne peut pas être lu.'));

            return;
        }

        try {
            $result = app(QuestionBankWorkbenchService::class)->stage($this->operator(), $contents);
            $this->questionBankJson = null;
            $this->selectedImportId = (string) $result['import']['id'];
            $this->hydrateDetail($result);
            Notification::make()
                ->title((string) $result['import']['status'] === 'rejected'
                    ? $this->text('تم رفض الحزمة', 'Pack rejected', 'Pack rejeté')
                    : $this->text('تم تجهيز الحزمة للمراجعة', 'Pack staged for review', 'Pack préparé pour révision'))
                ->color((string) $result['import']['status'] === 'rejected' ? 'danger' : 'success')
                ->send();
        } catch (ApiProblemException $exception) {
            $this->addError('questionBankJson', $exception->problemCode.' — '.$exception->getMessage());
        } catch (Throwable) {
            $this->addError('questionBankJson', $this->text('فشل الاستيراد بأمان.', 'Import failed safely.', 'L’import a échoué en sécurité.'));
        }
    }

    /** @return list<array<string, mixed>> */
    public function imports(): array
    {
        $rows = app(QuestionBankWorkbenchService::class)->imports();
        if ($this->statusFilter === 'all') {
            return $rows;
        }

        return array_values(array_filter($rows, fn (array $row): bool => (string) $row['status'] === $this->statusFilter));
    }

    /** @return array<string, mixed>|null */
    public function selectedDetail(): ?array
    {
        if ($this->selectedImportId === null) {
            return null;
        }

        try {
            $detail = app(QuestionBankWorkbenchService::class)->importResult($this->selectedImportId, false);
            $this->hydrateDetail($detail);

            return $detail;
        } catch (ApiProblemException) {
            $this->selectedImportId = null;

            return null;
        }
    }

    public function selectImport(string $importId): void
    {
        $this->selectedImportId = $this->selectedImportId === $importId ? null : $importId;
        if ($this->selectedImportId !== null) {
            $this->selectedDetail();
        }
    }

    public function approve(string $importId): void
    {
        $this->review($importId, 'approved');
    }

    public function reject(string $importId): void
    {
        $this->review($importId, 'rejected');
    }

    public function publish(string $importId): void
    {
        $this->perform($importId, fn (QuestionBankWorkbenchService $service, User $user): array => $service->publish($user, $importId));
    }

    public function unpublish(string $importId): void
    {
        $reason = (string) ($this->reasons[$importId] ?? '');
        $this->perform($importId, fn (QuestionBankWorkbenchService $service, User $user): array => $service->unpublish($user, $importId, $reason));
    }

    public function suspend(string $importId): void
    {
        $reason = (string) ($this->reasons[$importId] ?? '');
        $this->perform($importId, fn (QuestionBankWorkbenchService $service, User $user): array => $service->suspend($user, $importId, $reason));
    }

    public function archive(string $importId): void
    {
        $reason = (string) ($this->reasons[$importId] ?? '');
        $this->perform($importId, fn (QuestionBankWorkbenchService $service, User $user): array => $service->archive($user, $importId, $reason));
    }

    public function reclassify(string $itemId): void
    {
        try {
            $result = app(QuestionBankWorkbenchService::class)->reclassifyItem(
                $this->operator(),
                $itemId,
                (string) ($this->itemDifficulties[$itemId] ?? ''),
                (string) ($this->itemNodeIds[$itemId] ?? ''),
                (string) ($this->itemReasons[$itemId] ?? ''),
            );
            $this->selectedImportId = (string) $result['import']['id'];
            $this->hydrateDetail($result);
            Notification::make()->title($this->text('تم حفظ التصنيف', 'Reclassification saved', 'Reclassification enregistrée'))->success()->send();
        } catch (ApiProblemException $exception) {
            $this->addError('items.'.$itemId, $exception->problemCode.' — '.$exception->getMessage());
        }
    }

    public function saveSource(string $sourceId): void
    {
        try {
            app(QuestionBankWorkbenchService::class)->updateSourceMaterial(
                $this->operator(),
                $sourceId,
                (string) ($this->sourceKinds[$sourceId] ?? 'other'),
                $this->sourceReferences[$sourceId] ?? null,
                (string) ($this->sourceRights[$sourceId] ?? 'pending'),
                $this->sourceNotes[$sourceId] ?? null,
            );
            Notification::make()->title($this->text('تم حفظ المصدر والحقوق', 'Source and rights saved', 'Source et droits enregistrés'))->success()->send();
        } catch (ApiProblemException $exception) {
            $this->addError('sources.'.$sourceId, $exception->problemCode.' — '.$exception->getMessage());
        }
    }

    public function requestBulk(string $action): void
    {
        if (! in_array($action, ['approve', 'reject', 'publish', 'unpublish', 'suspend', 'archive'], true) || $this->selectedImportIds === []) {
            return;
        }

        $this->pendingBulkAction = $action;
        $this->dispatch('open-modal', id: 'confirm-question-bank-bulk');
    }

    public function cancelBulk(): void
    {
        $this->pendingBulkAction = null;
        $this->dispatch('close-modal', id: 'confirm-question-bank-bulk');
    }

    public function confirmBulk(): void
    {
        $action = $this->pendingBulkAction;
        if ($action === null) {
            return;
        }
        try {
            $result = app(QuestionBankWorkbenchService::class)->bulk(
                $this->operator(),
                $this->selectedImportIds,
                $action,
                $this->bulkReason,
            );
            $this->pendingBulkAction = null;
            $this->selectedImportIds = [];
            $this->bulkReason = '';
            $this->dispatch('close-modal', id: 'confirm-question-bank-bulk');
            Notification::make()->title($this->text('اكتمل الإجراء المجمع', 'Bulk action completed', 'Action groupée terminée').' · '.$result['count'])->success()->send();
        } catch (ApiProblemException $exception) {
            $this->addError('bulkReason', $exception->problemCode.' — '.$exception->getMessage());
        }
    }

    public function promptLibraryUrl(): string
    {
        return PromptLibrary::getUrl();
    }

    /** @return array<string, string> */
    public function curriculumOptions(string $importId): array
    {
        $import = DB::table('question_bank_imports')->where('id', $importId)->first();
        if ($import === null) {
            return [];
        }
        $request = DB::table('preparation_requests')->where('id', $import->preparation_request_id)->first();
        if ($request === null) {
            return [];
        }
        $settings = json_decode((string) $request->normalized_settings, true);
        $scope = is_array($settings) && is_array($settings['academic_scope'] ?? null) ? $settings['academic_scope'] : [];
        $trackReference = $scope['track_reference'] ?? null;
        if (! is_string($trackReference)) {
            return [];
        }

        return DB::table('curriculum_nodes')
            ->join('academic_tracks', 'academic_tracks.id', '=', 'curriculum_nodes.academic_track_id')
            ->where('academic_tracks.code', $trackReference)
            ->where('curriculum_nodes.status', 'published')
            ->orderBy('curriculum_nodes.type')
            ->orderBy('curriculum_nodes.code')
            ->get(['curriculum_nodes.id', 'curriculum_nodes.type', 'curriculum_nodes.code'])
            ->mapWithKeys(static fn (object $row): array => [(string) $row->id => (string) $row->type.' · '.(string) $row->code])
            ->all();
    }

    public function exportJson(string $importId): StreamedResponse
    {
        $data = app(QuestionBankWorkbenchService::class)->exportJson($importId);

        return response()->streamDownload(
            static function () use ($data): void {
                echo json_encode($data, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            },
            'modrik-question-bank-'.$importId.'.json',
            ['Content-Type' => 'application/json; charset=UTF-8'],
        );
    }

    public function exportCsv(string $importId): StreamedResponse
    {
        $rows = app(QuestionBankWorkbenchService::class)->exportCsvRows($importId);

        return response()->streamDownload(
            static function () use ($rows): void {
                $handle = fopen('php://output', 'wb');
                if ($handle === false) {
                    return;
                }
                fputcsv($handle, ['external_id', 'type', 'language', 'difficulty', 'status', 'scope_state', 'source_key', 'source_page', 'canonical_question_id']);
                foreach ($rows as $row) {
                    fputcsv($handle, [
                        $row['external_id'] ?? '',
                        $row['type'] ?? '',
                        $row['language'] ?? '',
                        $row['difficulty'] ?? '',
                        $row['status'] ?? '',
                        $row['scope_state'] ?? '',
                        $row['source_key'] ?? '',
                        $row['source_page'] ?? '',
                        $row['canonical_question_id'] ?? '',
                    ]);
                }
                fclose($handle);
            },
            'modrik-question-bank-'.$importId.'-summary.csv',
            ['Content-Type' => 'text/csv; charset=UTF-8'],
        );
    }

    private function review(string $importId, string $decision): void
    {
        $reason = (string) ($this->reasons[$importId] ?? '');
        $this->perform($importId, fn (QuestionBankWorkbenchService $service, User $user): array => $service->review($user, $importId, $decision, $reason));
    }

    /** @param callable(QuestionBankWorkbenchService, User): array<string, mixed> $callback */
    private function perform(string $importId, callable $callback): void
    {
        try {
            $result = $callback(app(QuestionBankWorkbenchService::class), $this->operator());
            $this->selectedImportId = (string) $result['import']['id'];
            $this->hydrateDetail($result);
            Notification::make()->title($this->text('تم تحديث دورة العمل', 'Workflow updated', 'Workflow mis à jour'))->success()->send();
        } catch (ApiProblemException $exception) {
            $this->addError('imports.'.$importId, $exception->problemCode.' — '.$exception->getMessage());
        }
    }

    /** @param array<string, mixed> $detail */
    private function hydrateDetail(array $detail): void
    {
        foreach (($detail['items'] ?? []) as $item) {
            if (! is_array($item) || ! is_string($item['id'] ?? null)) {
                continue;
            }
            $id = $item['id'];
            $this->itemDifficulties[$id] = (string) ($item['difficulty'] ?? 'Medium');
            $this->itemNodeIds[$id] = is_string($item['curriculum_node_id'] ?? null) ? $item['curriculum_node_id'] : '';
            $this->itemReasons[$id] ??= '';
        }
        foreach (($detail['sources'] ?? []) as $source) {
            if (! is_array($source) || ! is_string($source['id'] ?? null)) {
                continue;
            }
            $id = $source['id'];
            $this->sourceKinds[$id] = (string) ($source['kind'] ?? 'other');
            $this->sourceReferences[$id] = is_string($source['reference'] ?? null) ? $source['reference'] : '';
            $this->sourceRights[$id] = (string) ($source['rights_status'] ?? 'pending');
            $this->sourceNotes[$id] = is_string($source['rights_note'] ?? null) ? $source['rights_note'] : '';
        }
    }

    private function operator(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);
        app(ContentAdminWorkflowService::class)->assertOperator($user);

        return $user;
    }

    private function text(string $ar, string $en, string $fr): string
    {
        return match (App::getLocale()) {
            'ar' => $ar,
            'fr' => $fr,
            default => $en,
        };
    }
}
