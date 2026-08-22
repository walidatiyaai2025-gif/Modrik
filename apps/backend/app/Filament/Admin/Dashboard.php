<?php

namespace App\Filament\Admin;

use App\Filament\Pages\ContentPreparationRequests;
use App\Filament\Pages\ContentPreparationWizard;
use App\Filament\Pages\ContentReviewQueue;
use App\Filament\Pages\RuntimeInspector;
use App\Filament\Pages\SystemCapabilities;
use App\Models\User;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Enums\Width;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class Dashboard extends BaseDashboard
{
    protected string $view = 'filament.pages.dashboard';

    public static function getNavigationLabel(): string
    {
        return match (App::getLocale()) {
            'ar' => 'نظرة عامة',
            'fr' => 'Vue d’ensemble',
            default => 'Overview',
        };
    }

    public function getTitle(): string
    {
        return match (App::getLocale()) {
            'ar' => 'مركز عمليات مُدرك',
            'fr' => 'Centre des opérations MODRIK',
            default => 'MODRIK Operations',
        };
    }

    public function getSubheading(): string
    {
        return match (App::getLocale()) {
            'ar' => 'الأعمال التي تحتاج انتباهك الآن، وحالة مسار المحتوى والتشغيل — بدون مؤشرات تجميلية أو بيانات وهمية.',
            'fr' => 'Travail nécessitant votre attention et état opérationnel du contenu — sans métriques décoratives ni données fictives.',
            default => 'Work that needs attention now, plus content-pipeline and runtime health — no vanity metrics or fabricated data.',
        };
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    /** @return array<int, array{label: string, value: int, meta: string, tone: string}> */
    public function metrics(): array
    {
        $requests = $this->tableCount('preparation_requests');
        $awaitingReview = $this->tableCount('preparation_imports', static function (Builder $query): void {
            $query->whereNull('review_decision')->whereNotNull('dry_run_hash');
        });
        $operationFailures = $this->tableCount('preparation_imports', static function (Builder $query): void {
            $query->whereNotNull('last_error_code');
        });
        $published = $this->tableCount('content_publications', static function (Builder $query): void {
            $query->where('status', 'published');
        });

        return [
            [
                'label' => $this->label('طلبات الإعداد', 'Preparation requests', 'Demandes de préparation'),
                'value' => $requests,
                'meta' => $this->label('كل الطلبات المسجلة', 'All recorded requests', 'Toutes les demandes enregistrées'),
                'tone' => 'neutral',
            ],
            [
                'label' => $this->label('بانتظار المراجعة', 'Awaiting review', 'En attente de revue'),
                'value' => $awaitingReview,
                'meta' => $this->label('Dry-run مكتمل والقرار لم يسجل بعد', 'Dry-run complete, decision still pending', 'Dry-run terminé, décision en attente'),
                'tone' => $awaitingReview > 0 ? 'warning' : 'neutral',
            ],
            [
                'label' => $this->label('مشاكل تشغيلية', 'Operational failures', 'Échecs opérationnels'),
                'value' => $operationFailures,
                'meta' => $this->label('عمليات محتوى لها رمز خطأ مسجل', 'Content operations with a recorded error code', 'Opérations de contenu avec erreur enregistrée'),
                'tone' => $operationFailures > 0 ? 'danger' : 'success',
            ],
            [
                'label' => $this->label('منشور رسميًا', 'Published', 'Publié'),
                'value' => $published,
                'meta' => $this->label('عمليات نشر مكتملة', 'Completed publication operations', 'Opérations de publication terminées'),
                'tone' => 'success',
            ],
        ];
    }

    /** @return array{severity: string, title: string, message: string, jobs: int, failed_jobs: int, content_failures: int} */
    public function operationalHealth(): array
    {
        $jobs = $this->tableCount('jobs');
        $failedJobs = $this->tableCount('failed_jobs');
        $contentFailures = $this->tableCount('preparation_imports', static function (Builder $query): void {
            $query->whereNotNull('last_error_code');
        });

        if ($failedJobs > 0 || $contentFailures > 0) {
            return [
                'severity' => 'danger',
                'title' => $this->label('توجد مشاكل تحتاج تدخلاً', 'Action required', 'Action requise'),
                'message' => $this->label(
                    'راجع العمليات الفاشلة قبل متابعة النشر أو المهام الحساسة.',
                    'Review failed jobs and content operations before continuing sensitive publication work.',
                    'Vérifiez les tâches et opérations de contenu en échec avant toute publication sensible.',
                ),
                'jobs' => $jobs,
                'failed_jobs' => $failedJobs,
                'content_failures' => $contentFailures,
            ];
        }

        if ($jobs >= 10) {
            return [
                'severity' => 'warning',
                'title' => $this->label('تراكم في قائمة المهام', 'Queue backlog', 'File d’attente chargée'),
                'message' => $this->label(
                    'لا توجد أخطاء مسجلة، لكن قائمة الانتظار تحتاج متابعة تشغيلية.',
                    'No recorded failures, but the queued workload deserves operational attention.',
                    'Aucun échec enregistré, mais la file de travail doit être surveillée.',
                ),
                'jobs' => $jobs,
                'failed_jobs' => $failedJobs,
                'content_failures' => $contentFailures,
            ];
        }

        return [
            'severity' => 'success',
            'title' => $this->label('التشغيل مستقر', 'Operations stable', 'Opérations stables'),
            'message' => $this->label(
                'لا توجد مؤشرات فشل مسجلة في قوائم المهام أو عمليات المحتوى الحالية.',
                'No recorded failure signals in the current queue or content-operation state.',
                'Aucun signal d’échec enregistré dans la file ou les opérations de contenu actuelles.',
            ),
            'jobs' => $jobs,
            'failed_jobs' => $failedJobs,
            'content_failures' => $contentFailures,
        ];
    }

    /** @return array<int, array{label: string, description: string, url: string}> */
    public function quickActions(): array
    {
        $user = auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        $actions = [
            [
                'label' => $this->label('طلب إعداد جديد', 'New preparation request', 'Nouvelle préparation'),
                'description' => $this->label('ابدأ دورة إعداد محتوى جديدة', 'Start a new content-preparation lifecycle', 'Démarrer un nouveau cycle de préparation'),
                'url' => ContentPreparationWizard::getUrl(),
            ],
            [
                'label' => $this->label('قائمة المراجعة', 'Review queue', 'File de revue'),
                'description' => $this->label('راجع القرارات والعوائق قبل النشر', 'Resolve review decisions and blockers before publication', 'Traiter décisions et blocages avant publication'),
                'url' => ContentReviewQueue::getUrl(),
            ],
            [
                'label' => $this->label('سجل طلبات الإعداد', 'Preparation history', 'Historique des préparations'),
                'description' => $this->label('اعثر على الطلبات السابقة وافتحها', 'Find and reopen previous preparation requests', 'Retrouver et rouvrir les demandes précédentes'),
                'url' => ContentPreparationRequests::getUrl(),
            ],
            [
                'label' => $this->label('وظائف النظام', 'System capabilities', 'Fonctions du système'),
                'description' => $this->label('اعرف أين تُدار كل وظيفة وحالتها', 'See where each capability is operated and classified', 'Voir où chaque fonction est gérée et classée'),
                'url' => SystemCapabilities::getUrl(),
            ],
        ];

        if ((string) $user->role === 'admin' && RuntimeInspector::canAccess()) {
            $actions[] = [
                'label' => 'Runtime Inspector',
                'description' => $this->label('افتح التشخيصات المنقحة وحالة التشغيل', 'Open sanitized diagnostics and runtime state', 'Ouvrir les diagnostics filtrés et l’état runtime'),
                'url' => RuntimeInspector::getUrl(),
            ];
        }

        return $actions;
    }

    /** @return array<int, array{action: string, status: string, actor: string, reason: string, created_at: string}> */
    public function recentActivity(): array
    {
        if (! Schema::hasTable('content_workflow_audits')) {
            return [];
        }

        return DB::table('content_workflow_audits')
            ->select(['action', 'to_status', 'actor_id', 'reason', 'created_at'])
            ->orderByDesc('created_at')
            ->limit(8)
            ->get()
            ->map(static fn (object $row): array => [
                'action' => (string) $row->action,
                'status' => (string) ($row->to_status ?? ''),
                'actor' => (string) ($row->actor_id ?? ''),
                'reason' => (string) ($row->reason ?? ''),
                'created_at' => (string) $row->created_at,
            ])
            ->all();
    }

    private function label(string $ar, string $en, string $fr): string
    {
        return match (App::getLocale()) {
            'ar' => $ar,
            'fr' => $fr,
            default => $en,
        };
    }

    /** @param null|callable(Builder): void $scope */
    private function tableCount(string $table, ?callable $scope = null): int
    {
        if (! Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($scope !== null) {
            $scope($query);
        }

        return $query->count();
    }
}
