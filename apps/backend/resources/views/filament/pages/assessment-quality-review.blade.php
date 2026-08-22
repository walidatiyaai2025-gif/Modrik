<x-filament-panels::page>
    <div data-testid="modrik-assessment-quality-review" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="space-y-6">
        @php
            $isAr = app()->getLocale() === 'ar';
            $isFr = app()->getLocale() === 'fr';
            $t = static fn (string $en, string $ar, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
            $rows = $this->rows();
            $metadataCount = collect($rows)->where('metadata_present', true)->count();
            $shuffleSafeCount = collect($rows)->where('effective_shuffle_safe', true)->count();
            $snapshotCount = collect($rows)->sum('snapshot_count');
        @endphp

        <x-filament::section>
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="max-w-4xl space-y-2">
                    <h2 class="text-base font-semibold">{{ $t('Question quality signals — read only', 'إشارات جودة الأسئلة — للقراءة فقط', 'Signaux qualité — lecture seule') }}</h2>
                    <p class="text-sm text-gray-600 dark:text-gray-300">
                        {{ $t('Review only metadata persisted by the authoritative content/assessment contract. No synthetic quality score is invented and no student answer history is exposed.', 'راجع فقط بيانات الجودة المحفوظة ضمن عقد المحتوى والتقييم الموثوق. لا يتم اختراع درجة جودة اصطناعية ولا يتم عرض تاريخ إجابات الطلاب.', 'Contrôlez uniquement les métadonnées persistées par le contrat autoritatif. Aucun score synthétique ni historique de réponses étudiant n’est exposé.') }}
                    </p>
                </div>
                <x-filament::button tag="a" :href="$this->questionBankUrl()" color="primary">
                    {{ $t('Open Question Bank details', 'فتح تفاصيل بنك الأسئلة', 'Ouvrir les détails de la banque') }}
                </x-filament::button>
            </div>
            <div class="mt-4 rounded-xl border border-info-200 bg-info-50 p-4 text-sm dark:border-info-500/20 dark:bg-info-500/5" role="status">
                <strong>{{ $t('Historical attempt snapshots are aggregate-only here.', 'لقطات المحاولات التاريخية تظهر هنا كأعداد مجمعة فقط.', 'Les snapshots historiques sont uniquement agrégés ici.') }}</strong>
                <p class="mt-1">{{ $t('A snapshot count means the canonical question was already captured in one or more attempts. Future live-bank changes must never rewrite those immutable snapshots.', 'وجود عدد للقطات يعني أن السؤال الأساسي تم حفظه بالفعل داخل محاولة أو أكثر. أي تعديل مستقبلي للبنك يجب ألا يعيد كتابة تلك اللقطات.', 'Un nombre de snapshots signifie que la question a déjà été capturée dans des tentatives. Les futures modifications ne doivent jamais les réécrire.') }}</p>
            </div>
        </x-filament::section>

        <x-filament::section>
            <x-slot name="heading">{{ $t('Filter quality review', 'تصفية مراجعة الجودة', 'Filtrer la revue qualité') }}</x-slot>
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <label class="block space-y-2"><span class="text-sm font-medium">{{ $t('Question state', 'حالة السؤال', 'État de la question') }}</span><x-filament::input.wrapper><x-filament::input.select wire:model.live="statusFilter"><option value="all">{{ $t('All states', 'كل الحالات', 'Tous les états') }}</option><option value="published">{{ $t('Published', 'منشور', 'Publié') }}</option><option value="draft">{{ $t('Draft', 'مسودة', 'Brouillon') }}</option></x-filament::input.select></x-filament::input.wrapper></label>
                <label class="block space-y-2"><span class="text-sm font-medium">{{ $t('Assessment metadata', 'بيانات التقييم', 'Métadonnées évaluation') }}</span><x-filament::input.wrapper><x-filament::input.select wire:model.live="metadataFilter"><option value="all">{{ $t('All', 'الكل', 'Tout') }}</option><option value="present">{{ $t('Metadata present', 'بيانات موجودة', 'Métadonnées présentes') }}</option><option value="missing">{{ $t('Metadata missing', 'بيانات غير موجودة', 'Métadonnées absentes') }}</option></x-filament::input.select></x-filament::input.wrapper></label>
                <label class="block space-y-2"><span class="text-sm font-medium">{{ $t('Effective option order', 'ترتيب الاختيارات الفعلي', 'Ordre effectif des options') }}</span><x-filament::input.wrapper><x-filament::input.select wire:model.live="shuffleFilter"><option value="all">{{ $t('All policies', 'كل السياسات', 'Toutes les politiques') }}</option><option value="safe">{{ $t('Effectively shuffle-safe', 'آمن فعليًا للخلط', 'Mélange effectivement sûr') }}</option><option value="fixed">{{ $t('Preserve source order', 'الحفاظ على ترتيب المصدر', 'Préserver l’ordre source') }}</option></x-filament::input.select></x-filament::input.wrapper></label>
                <label class="block space-y-2"><span class="text-sm font-medium">{{ $t('Search prompt / scope', 'بحث في السؤال / النطاق', 'Rechercher énoncé / périmètre') }}</span><x-filament::input.wrapper><x-filament::input type="search" wire:model.live.debounce.250ms="search" /></x-filament::input.wrapper></label>
            </div>
        </x-filament::section>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="modrik-metric-card"><div class="modrik-metric-label">{{ $t('Questions shown', 'الأسئلة المعروضة', 'Questions affichées') }}</div><div class="modrik-metric-value">{{ count($rows) }}</div><div class="modrik-metric-meta">{{ $t('Current filters', 'حسب الفلاتر الحالية', 'Filtres actuels') }}</div></div>
            <div class="modrik-metric-card"><div class="modrik-metric-label">{{ $t('Metadata present', 'بيانات موجودة', 'Métadonnées présentes') }}</div><div class="modrik-metric-value">{{ $metadataCount }}</div><div class="modrik-metric-meta">{{ $t('Persisted contract fields only', 'حقول العقد المحفوظة فقط', 'Champs contractuels persistés') }}</div></div>
            <div class="modrik-metric-card"><div class="modrik-metric-label">{{ $t('Effectively shuffle-safe', 'آمن فعليًا للخلط', 'Mélange effectivement sûr') }}</div><div class="modrik-metric-value">{{ $shuffleSafeCount }}</div><div class="modrik-metric-meta">{{ $t('Explicit opt-in plus safe semantics', 'تصريح صريح مع دلالات آمنة', 'Opt-in explicite et sémantique sûre') }}</div></div>
            <div class="modrik-metric-card" data-tone="warning"><div class="modrik-metric-label">{{ $t('Historical snapshots', 'اللقطات التاريخية', 'Snapshots historiques') }}</div><div class="modrik-metric-value">{{ $snapshotCount }}</div><div class="modrik-metric-meta">{{ $t('Aggregate usage, never student answers', 'استخدام مجمع بدون إجابات الطلاب', 'Usage agrégé, jamais les réponses') }}</div></div>
        </div>

        <x-filament::section>
            <x-slot name="heading">{{ $t('Quality review queue', 'قائمة مراجعة الجودة', 'File de revue qualité') }}</x-slot>
            <x-slot name="description">{{ $t('Option shuffling is effective only when the explicit safe flag is present and no fixed, sequence, image-letter or all/none semantics override it.', 'لا يصبح خلط الاختيارات فعالًا إلا مع تصريح الأمان الصريح وعدم وجود دلالات ثابتة أو تسلسلية أو صورة/حرف أو كل/لا شيء تمنعه.', 'Le mélange n’est effectif que si le drapeau explicite est sûr et qu’aucune sémantique fixe, séquence, image-lettre ou tout/aucun ne l’annule.') }}</x-slot>
            @if ($rows === [])
                <div class="py-12 text-center text-sm text-gray-500" role="status">{{ $t('No questions match these quality-review filters.', 'لا توجد أسئلة مطابقة لفلاتر مراجعة الجودة.', 'Aucune question ne correspond à ces filtres.') }}</div>
            @else
                <div class="space-y-4">
                    @foreach ($rows as $index => $row)
                        <article class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-white/5" wire:key="assessment-quality-{{ $row['id'] }}">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <x-filament::badge color="gray">{{ $t('Question', 'سؤال', 'Question') }} {{ $index + 1 }}</x-filament::badge>
                                        <x-filament::badge :color="$row['status'] === 'published' ? 'success' : 'warning'">{{ $row['status'] }}</x-filament::badge>
                                        <x-filament::badge :color="$row['metadata_present'] ? 'primary' : 'warning'">{{ $row['metadata_present'] ? $t('Metadata present', 'بيانات موجودة', 'Métadonnées présentes') : $t('Metadata missing', 'بيانات غير موجودة', 'Métadonnées absentes') }}</x-filament::badge>
                                        <x-filament::badge :color="$row['effective_shuffle_safe'] ? 'success' : 'gray'">{{ $row['effective_shuffle_safe'] ? $t('Shuffle-safe', 'آمن للخلط', 'Mélange sûr') : $t('Preserve order', 'حفظ الترتيب', 'Préserver l’ordre') }}</x-filament::badge>
                                        @if ($row['explicit_shuffle_safe'] && ! $row['effective_shuffle_safe'])<x-filament::badge color="warning">{{ $t('Safety override active', 'تجاوز الأمان مفعل', 'Override de sûreté actif') }}</x-filament::badge>@endif
                                    </div>
                                    <h3 class="mt-3 text-base font-semibold leading-7" dir="auto">{{ $row['prompt'] }}</h3>
                                    <p class="mt-1 text-xs text-gray-500">{{ $row['track_title'] }} · {{ $row['year_level'] }} · {{ $row['node_title'] }}</p>
                                </div>
                                <div class="text-sm text-gray-600 dark:text-gray-300"><div>{{ $t('Score', 'الدرجة', 'Score') }}: <strong>{{ $row['maximum_score'] }}</strong></div><div class="mt-1">{{ $t('Content version', 'إصدار المحتوى', 'Version contenu') }}: v{{ $row['content_version'] }}</div></div>
                            </div>
                            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5"><div class="text-xs text-gray-500">{{ $t('Section', 'القسم', 'Section') }}</div><div class="mt-1 font-semibold">{{ $row['section'] ?? '—' }}</div></div>
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5"><div class="text-xs text-gray-500">{{ $t('Difficulty', 'الصعوبة', 'Difficulté') }}</div><div class="mt-1 font-semibold">{{ $row['difficulty'] ?? '—' }}</div></div>
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5"><div class="text-xs text-gray-500">{{ $t('Assessment memberships', 'عضوية الاختبارات', 'Appartenances') }}</div><div class="mt-1 font-semibold">{{ $row['membership_count'] }}</div></div>
                                <div class="rounded-lg bg-gray-50 p-3 text-sm dark:bg-white/5"><div class="text-xs text-gray-500">{{ $t('Historical snapshots', 'اللقطات التاريخية', 'Snapshots historiques') }}</div><div class="mt-1 font-semibold">{{ $row['snapshot_count'] }}</div></div>
                            </div>
                            @if ($row['concepts'] !== [])<div class="mt-4 flex flex-wrap gap-2">@foreach ($row['concepts'] as $concept)<x-filament::badge color="gray">{{ $concept }}</x-filament::badge>@endforeach</div>@endif
                            @if ($row['unsafe_reasons'] !== [])<div class="mt-4 rounded-lg border border-warning-200 bg-warning-50 p-3 text-sm dark:border-warning-500/20 dark:bg-warning-500/5"><strong>{{ $t('Ordering semantics require preservation.', 'دلالات الترتيب تتطلب الحفاظ عليه.', 'La sémantique impose de préserver l’ordre.') }}</strong><p class="mt-1 font-mono text-xs" dir="ltr">{{ implode(' · ', $row['unsafe_reasons']) }}</p></div>@endif
                            @if ($row['snapshot_count'] > 0)<div class="mt-4 rounded-lg border border-info-200 bg-info-50 p-3 text-sm dark:border-info-500/20 dark:bg-info-500/5">{{ $t('This canonical question already has immutable historical attempt snapshots. Future bank edits cannot rewrite them.', 'لهذا السؤال الأساسي لقطات محاولات تاريخية غير قابلة للتغيير. أي تعديل مستقبلي للبنك لا يمكنه إعادة كتابتها.', 'Cette question possède déjà des snapshots historiques immuables. Les futures modifications ne peuvent pas les réécrire.') }}</div>@endif
                            <details class="mt-4 rounded-lg border border-gray-200 p-3 text-xs dark:border-white/10"><summary class="cursor-pointer font-medium">{{ $t('Technical traceability', 'التتبع التقني', 'Traçabilité technique') }}</summary><dl class="mt-3 grid gap-2 md:grid-cols-2"><div><dt class="font-medium">Question ID</dt><dd class="break-all font-mono">{{ $row['id'] }}</dd></div><div><dt class="font-medium">Curriculum node</dt><dd class="break-all font-mono">{{ $row['node_code'] }}</dd></div><div><dt class="font-medium">Type</dt><dd>{{ $row['type'] }}</dd></div><div><dt class="font-medium">Updated</dt><dd>{{ $row['updated_at'] }}</dd></div></dl></details>
                        </article>
                    @endforeach
                </div>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
