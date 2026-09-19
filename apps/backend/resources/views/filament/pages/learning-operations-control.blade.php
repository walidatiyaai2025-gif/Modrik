<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($features = $this->features())
    @php($jobs = $this->jobs())

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-learning-operations">
        <x-admin.operational-banner
            severity="warning"
            :title="$locale === 'ar' ? 'التحكم التشغيلي للتعلم' : ($locale === 'fr' ? 'Contrôle opérationnel apprentissage' : 'Learning operational control')"
            :message="$locale === 'ar'
                ? 'مفاتيح الإيقاف والنطاقات تخضع للإصدارات والتدقيق. قواعد الأمان والتقييم والخصوصية وسلامة النشر غير قابلة للتحرير هنا.'
                : ($locale === 'fr'
                    ? 'Les états sont versionnés et audités. Les invariants de sécurité, notation, confidentialité et publication ne sont pas modifiables ici.'
                    : 'Feature states are versioned and audited. Security, scoring, privacy and publication-integrity invariants are not editable here.')"
        />

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'مفاتيح وميزات التعلم' : ($locale === 'fr' ? 'Fonctionnalités apprentissage' : 'Learning feature controls') }}</h2>
            <div class="mt-4 grid gap-4 xl:grid-cols-2">
                @foreach ($features as $key => $feature)
                    <article class="rounded-xl border border-gray-200 p-4" data-feature="{{ $key }}">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-950">{{ str_replace('_', ' ', $key) }}</h3>
                                <p class="text-xs text-gray-500">v{{ $feature['version'] }} · {{ $feature['persisted'] ? 'persisted' : 'default' }}</p>
                            </div>
                            <select wire:model="featureStates.{{ $key }}" class="rounded-lg border-gray-300 text-sm">
                                @foreach (['disabled', 'enabled', 'pilot', 'admin_only'] as $state)
                                    <option value="{{ $state }}">{{ str_replace('_', ' ', $state) }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="mt-3 flex gap-2">
                            <input wire:model="featureReasons.{{ $key }}" class="min-w-0 flex-1 rounded-lg border-gray-300 text-sm" placeholder="{{ $locale === 'ar' ? 'سبب التغيير' : ($locale === 'fr' ? 'Motif du changement' : 'Change reason') }}" />
                            <x-filament::button
                                wire:click="saveFeature('{{ $key }}')"
                                wire:confirm="{{ $locale === 'ar' ? 'تأكيد تغيير حالة هذه الميزة؟' : ($locale === 'fr' ? 'Confirmer ce changement ?' : 'Confirm this feature-state change?') }}"
                            >{{ $locale === 'ar' ? 'حفظ' : ($locale === 'fr' ? 'Enregistrer' : 'Save') }}</x-filament::button>
                        </div>
                        @error('featureReasons.'.$key)<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </article>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'وظائف التعلم' : ($locale === 'fr' ? 'Tâches apprentissage' : 'Learning jobs') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $locale === 'ar' ? 'الوظائف ذات التبعيات غير المكتملة تبقى محظورة ولا تعرض نجاحًا وهميًا.' : ($locale === 'fr' ? 'Les tâches dont les dépendances manquent restent bloquées et ne simulent jamais un succès.' : 'Jobs with missing dependencies remain blocked and never report fake success.') }}</p>

            <div class="mt-4 space-y-4">
                @foreach ($jobs as $key => $job)
                    <article class="rounded-xl border border-gray-200 p-4" data-job="{{ $key }}">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h3 class="font-semibold text-gray-950">{{ str_replace('_', ' ', $key) }}</h3>
                                <p class="text-xs text-gray-500">{{ $job['schedule'] }} · v{{ $job['version'] }}</p>
                            </div>
                            <div class="flex gap-2">
                                <x-filament::badge :color="$job['availability'] === 'ready' ? 'success' : 'warning'">{{ str_replace('_', ' ', $job['availability']) }}</x-filament::badge>
                                <x-filament::badge :color="$job['paused'] ? 'danger' : 'gray'">{{ $job['paused'] ? 'paused' : 'active' }}</x-filament::badge>
                            </div>
                        </div>

                        @if ($job['dependency'])
                            <p class="mt-2 text-sm text-warning-700">{{ $locale === 'ar' ? 'مطلوب: ' : ($locale === 'fr' ? 'Dépendance : ' : 'Dependency: ') }}{{ $job['dependency'] }}</p>
                        @endif

                        <dl class="mt-3 grid gap-2 text-xs sm:grid-cols-4">
                            <div><dt class="text-gray-500">Last status</dt><dd class="font-medium">{{ $job['last_status'] ?? 'never' }}</dd></div>
                            <div><dt class="text-gray-500">Duration</dt><dd class="font-medium">{{ $job['last_duration_ms'] ?? '—' }} ms</dd></div>
                            <div><dt class="text-gray-500">Last run</dt><dd class="font-medium">{{ $job['last_run_at'] ?? '—' }}</dd></div>
                            <div><dt class="text-gray-500">Next run</dt><dd class="font-medium">{{ $job['next_run_at'] ?? 'scheduler-owned' }}</dd></div>
                        </dl>

                        <div class="mt-3 flex flex-wrap gap-2">
                            <input wire:model="jobReasons.{{ $key }}" class="min-w-[14rem] flex-1 rounded-lg border-gray-300 text-sm" placeholder="{{ $locale === 'ar' ? 'سبب الإيقاف أو الاستئناف' : ($locale === 'fr' ? 'Motif pause/reprise' : 'Pause/resume reason') }}" />
                            @if ($job['paused'])
                                <x-filament::button color="gray" wire:click="resumeJob('{{ $key }}')" wire:confirm="{{ $locale === 'ar' ? 'استئناف هذه الوظيفة؟' : 'Resume this job?' }}">Resume</x-filament::button>
                            @else
                                <x-filament::button color="gray" wire:click="pauseJob('{{ $key }}')" wire:confirm="{{ $locale === 'ar' ? 'إيقاف هذه الوظيفة؟' : 'Pause this job?' }}">Pause</x-filament::button>
                            @endif
                            <x-filament::button
                                wire:click="runJobNow('{{ $key }}')"
                                wire:confirm="{{ $locale === 'ar' ? 'تشغيل هذه الوظيفة الآن ضمن الحدود الآمنة؟' : ($locale === 'fr' ? 'Exécuter cette tâche maintenant ?' : 'Run this bounded job now?') }}"
                                :disabled="$job['availability'] !== 'ready' || $job['paused']"
                            >Run Now</x-filament::button>
                        </div>
                        @error('jobReasons.'.$key)<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
                        @error('jobs.'.$key)<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </article>
                @endforeach
            </div>
        </section>
    </div>
</x-filament-panels::page>
