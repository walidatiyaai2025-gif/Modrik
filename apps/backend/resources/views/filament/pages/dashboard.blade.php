<x-filament-panels::page>
    @php
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        $metrics = $this->metrics();
        $health = $this->operationalHealth();
        $quickActions = $this->quickActions();
        $activity = $this->recentActivity();
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-admin-dashboard">
        <x-admin.operational-banner
            :severity="$health['severity']"
            :title="$health['title']"
            :message="$health['message']"
        >
            <div class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-xs text-gray-600">
                <span>{{ $label('قائمة الانتظار', 'Queued jobs', 'Tâches en file') }}: <strong>{{ $health['jobs'] }}</strong></span>
                <span>{{ $label('مهام فاشلة', 'Failed jobs', 'Tâches échouées') }}: <strong>{{ $health['failed_jobs'] }}</strong></span>
                <span>{{ $label('أخطاء محتوى', 'Content failures', 'Échecs contenu') }}: <strong>{{ $health['content_failures'] }}</strong></span>
            </div>
        </x-admin.operational-banner>

        <section aria-label="{{ $label('مؤشرات التشغيل', 'Operational metrics', 'Indicateurs opérationnels') }}">
            <div class="modrik-dashboard-grid">
                @foreach ($metrics as $metric)
                    <x-admin.metric-card
                        :label="$metric['label']"
                        :value="$metric['value']"
                        :meta="$metric['meta']"
                        :tone="$metric['tone']"
                    />
                @endforeach
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-5">
            <section class="modrik-panel xl:col-span-3" aria-labelledby="modrik-quick-actions-title">
                <div class="modrik-panel-header">
                    <div>
                        <h2 id="modrik-quick-actions-title" class="modrik-panel-title">
                            {{ $label('الإجراءات السريعة', 'Quick actions', 'Actions rapides') }}
                        </h2>
                        <p class="modrik-panel-subtitle">
                            {{ $label('أكثر مسارات التشغيل استخدامًا حسب صلاحيات حسابك.', 'Frequent operator workflows available to your role.', 'Flux opérateur fréquents disponibles pour votre rôle.') }}
                        </p>
                    </div>
                </div>
                <div class="modrik-panel-body">
                    @if ($quickActions === [])
                        <x-admin.empty-state
                            :title="$label('لا توجد إجراءات متاحة', 'No actions available', 'Aucune action disponible')"
                            :message="$label('صلاحيات حسابك لا تعرض إجراءات تشغيلية على هذه الصفحة.', 'Your role exposes no operational actions on this page.', 'Votre rôle n’expose aucune action opérationnelle sur cette page.')"
                        />
                    @else
                        <div class="modrik-quick-actions">
                            @foreach ($quickActions as $action)
                                <a class="modrik-quick-action" href="{{ $action['url'] }}">
                                    <span class="min-w-0">
                                        <span class="block text-sm font-semibold">{{ $action['label'] }}</span>
                                        <span class="mt-1 block text-xs leading-5 text-gray-500">{{ $action['description'] }}</span>
                                    </span>
                                    <x-filament::icon
                                        icon="heroicon-o-arrow-up-right"
                                        class="h-4 w-4 shrink-0 text-gray-400"
                                        aria-hidden="true"
                                    />
                                </a>
                            @endforeach
                        </div>
                    @endif
                </div>
            </section>

            <section class="modrik-panel xl:col-span-2" aria-labelledby="modrik-operator-guide-title">
                <div class="modrik-panel-header">
                    <div>
                        <h2 id="modrik-operator-guide-title" class="modrik-panel-title">
                            {{ $label('قاعدة التشغيل', 'Operator rule', 'Règle opérateur') }}
                        </h2>
                        <p class="modrik-panel-subtitle">
                            {{ $label('الأولوية للاستثناءات والمخاطر، وليس للمؤشرات التجميلية.', 'Exceptions and risk come before decorative reporting.', 'Les exceptions et les risques passent avant les métriques décoratives.') }}
                        </p>
                    </div>
                </div>
                <div class="modrik-panel-body space-y-4 text-sm leading-6 text-gray-600">
                    <div class="rounded-xl bg-gray-50 p-4">
                        <div class="font-semibold text-gray-950">{{ $label('قبل النشر', 'Before publishing', 'Avant publication') }}</div>
                        <p class="mt-1">{{ $label('حل أخطاء التشغيل، راجع الحقوق، ثم ثبّت قرار المراجعة.', 'Resolve operational failures, approve rights evidence, then record the review decision.', 'Résolvez les échecs, validez les droits, puis enregistrez la décision de revue.') }}</p>
                    </div>
                    <div class="rounded-xl bg-gray-50 p-4">
                        <div class="font-semibold text-gray-950">{{ $label('المعرفات التقنية', 'Technical identifiers', 'Identifiants techniques') }}</div>
                        <p class="mt-1">{{ $label('تظهر للتتبع عند الحاجة فقط؛ لا تُعامل كاسم للعنصر أو كخطوة تشغيلية.', 'Use IDs and hashes for traceability only; they are not the operator’s primary mental model.', 'Utilisez IDs et hashes pour la traçabilité uniquement, pas comme modèle mental principal.') }}</p>
                    </div>
                </div>
            </section>
        </div>

        <section class="modrik-panel" aria-labelledby="modrik-recent-activity-title">
            <div class="modrik-panel-header">
                <div>
                    <h2 id="modrik-recent-activity-title" class="modrik-panel-title">
                        {{ $label('آخر نشاط تشغيلي', 'Recent operational activity', 'Activité opérationnelle récente') }}
                    </h2>
                    <p class="modrik-panel-subtitle">
                        {{ $label('آخر أحداث تدفق المحتوى من سجل التدقيق غير القابل للتعديل.', 'Latest content-workflow events from the immutable audit trail.', 'Derniers événements du flux contenu issus de la piste d’audit immuable.') }}
                    </p>
                </div>
                <x-filament::button
                    tag="a"
                    color="gray"
                    size="sm"
                    :href="\App\Filament\Pages\ContentReviewQueue::getUrl()"
                    icon="heroicon-o-queue-list"
                >
                    {{ $label('فتح قائمة المراجعة', 'Open review queue', 'Ouvrir la file de revue') }}
                </x-filament::button>
            </div>
            <x-admin.audit-timeline
                :items="$activity"
                :empty-title="$label('لا يوجد نشاط مسجل بعد', 'No audit activity yet', 'Aucune activité d’audit')"
            />
        </section>
    </div>
</x-filament-panels::page>
