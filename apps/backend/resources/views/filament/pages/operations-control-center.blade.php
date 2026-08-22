<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($statusColor = fn (string $status) => in_array($status, ['healthy'], true) ? 'success' : (in_array($status, ['attention', 'not_observable'], true) ? 'warning' : 'danger'))

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-operations-control-center">
        <x-admin.operational-banner
            :severity="($overview['backend']['status'] ?? '') === 'healthy' && ($overview['queue']['status'] ?? '') === 'healthy' && ($overview['storage']['status'] ?? '') === 'healthy' ? 'success' : 'warning'"
            :title="$locale === 'ar' ? 'صحة التشغيل الحالية' : ($locale === 'fr' ? 'Santé opérationnelle actuelle' : 'Current operational health')"
            :message="$locale === 'ar'
                ? 'هذه الصفحة تجمع إشارات آمنة من العقود الحالية فقط. لا توجد أوامر shell أو SQL أو تعديل payload أو أسرار، ولا يتم اختراع heartbeat أو سعة تخزين غير متاحة.'
                : ($locale === 'fr'
                    ? 'Cette page compose uniquement des signaux sûrs issus des contrats existants. Aucun shell, SQL, payload arbitraire ou secret ; aucun heartbeat ou capacité non observable n’est inventé.'
                    : 'This page composes safe signals from existing contracts only. It exposes no shell, SQL, arbitrary payload mutation or secrets, and does not fabricate scheduler heartbeats or storage capacity.')"
        >
            <div class="mt-2 flex flex-wrap items-center gap-2 text-xs">
                <x-filament::badge color="gray">APP_ENV={{ $overview['environment'] }}</x-filament::badge>
                <x-filament::badge color="gray">Build {{ $overview['build_identity'] ?: ($locale === 'ar' ? 'غير مسجل' : ($locale === 'fr' ? 'non enregistré' : 'not recorded')) }}</x-filament::badge>
                <x-filament::button size="xs" color="gray" wire:click="$refresh" wire:loading.attr="disabled">
                    {{ $locale === 'ar' ? 'إعادة فحص الصحة' : ($locale === 'fr' ? 'Réessayer les contrôles' : 'Retry health checks') }}
                </x-filament::button>
            </div>
        </x-admin.operational-banner>

        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ops-db-heading">
                <div class="flex items-start justify-between gap-3"><h2 id="ops-db-heading" class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'قاعدة البيانات' : ($locale === 'fr' ? 'Base de données' : 'Database') }}</h2><x-filament::badge :color="$statusColor($overview['backend']['status'])">{{ $overview['backend']['status'] }}</x-filament::badge></div>
                <p class="mt-3 text-sm text-gray-600">{{ $overview['backend']['detail'] }}</p>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ops-queue-heading">
                <div class="flex items-start justify-between gap-3"><h2 id="ops-queue-heading" class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'الطوابير' : ($locale === 'fr' ? 'Files' : 'Queue') }}</h2><x-filament::badge :color="$statusColor($overview['queue']['status'])">{{ $overview['queue']['status'] }}</x-filament::badge></div>
                <dl class="mt-3 grid grid-cols-2 gap-3 text-sm"><div><dt class="text-gray-500">{{ $locale === 'ar' ? 'منتظر' : ($locale === 'fr' ? 'En attente' : 'Queued') }}</dt><dd class="mt-1 text-xl font-semibold text-gray-950">{{ $overview['queue']['queued'] }}</dd></div><div><dt class="text-gray-500">{{ $locale === 'ar' ? 'فشل' : ($locale === 'fr' ? 'Échec' : 'Failed') }}</dt><dd class="mt-1 text-xl font-semibold text-gray-950">{{ $overview['queue']['failed'] }}</dd></div></dl>
                <p class="mt-3 text-xs text-gray-500">{{ $overview['queue']['detail'] }}</p>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ops-storage-heading">
                <div class="flex items-start justify-between gap-3"><h2 id="ops-storage-heading" class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'التخزين' : ($locale === 'fr' ? 'Stockage' : 'Storage') }}</h2><x-filament::badge :color="$statusColor($overview['storage']['status'])">{{ $overview['storage']['status'] }}</x-filament::badge></div>
                <p class="mt-3 text-sm text-gray-600">{{ $overview['storage']['detail'] }}</p>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ops-scheduler-heading">
                <div class="flex items-start justify-between gap-3"><h2 id="ops-scheduler-heading" class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'المجدول / العمال' : ($locale === 'fr' ? 'Planificateur / workers' : 'Scheduler / workers') }}</h2><x-filament::badge :color="$statusColor($overview['scheduler']['status'])">{{ str_replace('_', ' ', $overview['scheduler']['status']) }}</x-filament::badge></div>
                <p class="mt-3 text-sm text-gray-600">{{ $overview['scheduler']['detail'] }}</p>
            </section>
        </div>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ops-runtime-heading">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">Runtime</p><h2 id="ops-runtime-heading" class="mt-1 text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'التشخيص والـOutbox' : ($locale === 'fr' ? 'Diagnostics et Outbox' : 'Diagnostics & Outbox') }}</h2></div>
                    <x-filament::badge :color="$overview['runtime']['diagnostics_enabled'] ? 'success' : 'gray'">{{ $overview['runtime']['diagnostics_enabled'] ? 'Enabled' : 'Disabled' }}</x-filament::badge>
                </div>
                <dl class="mt-5 grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Diagnostics</dt><dd class="mt-1 text-xl font-semibold text-gray-950">{{ $overview['runtime']['diagnostic_events'] }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Outbox</dt><dd class="mt-1 text-xl font-semibold text-gray-950">{{ $overview['runtime']['outbox_events'] }}</dd></div>
                    <div class="rounded-xl bg-gray-50 p-3"><dt class="text-xs text-gray-500">Attempts</dt><dd class="mt-1 text-xl font-semibold text-gray-950">{{ $overview['runtime']['outbox_delivery_attempts'] }}</dd></div>
                </dl>
                <div class="mt-5 flex flex-wrap justify-end gap-2">
                    <x-filament::button tag="a" color="gray" href="/admin/system-capabilities">{{ $locale === 'ar' ? 'فهرس القدرات' : ($locale === 'fr' ? 'Index des capacités' : 'Capability index') }}</x-filament::button>
                    @if ($runtimeInspectorEnabled)
                        <x-filament::button tag="a" href="/admin/runtime-inspector">{{ $locale === 'ar' ? 'فحص التشخيص' : ($locale === 'fr' ? 'Inspecter les diagnostics' : 'Inspect diagnostics') }}</x-filament::button>
                    @else
                        <x-filament::button color="gray" disabled>{{ $locale === 'ar' ? 'Runtime Inspector غير مفعّل' : ($locale === 'fr' ? 'Runtime Inspector désactivé' : 'Runtime Inspector gated') }}</x-filament::button>
                    @endif
                </div>
            </section>

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ops-integrations-heading">
                <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">Integrations</p><h2 id="ops-integrations-heading" class="mt-1 text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'ملخص التكاملات الآمن' : ($locale === 'fr' ? 'Résumé sûr des intégrations' : 'Safe integration summary') }}</h2></div><x-filament::badge :color="$statusColor($overview['integrations']['status'])">{{ $overview['integrations']['status'] }}</x-filament::badge></div>
                <dl class="mt-5 space-y-3 text-sm">
                    <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-3"><dt class="text-gray-500">{{ $locale === 'ar' ? 'مزودو Auth بانتظار adapter' : ($locale === 'fr' ? 'Transports Auth en attente' : 'Pending Auth transports') }}</dt><dd class="font-semibold text-gray-950">{{ $overview['integrations']['pending_auth_transports'] }}</dd></div>
                    <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-3"><dt class="text-gray-500">Firebase FCM</dt><dd><x-filament::badge color="gray">{{ $overview['integrations']['firebase_fcm_status'] }}</x-filament::badge></dd></div>
                    <div class="flex items-center justify-between gap-4 border-t border-gray-100 pt-3"><dt class="text-gray-500">{{ $locale === 'ar' ? 'مرجع اعتماد Firebase' : ($locale === 'fr' ? 'Référence credential Firebase' : 'Firebase credential reference') }}</dt><dd><x-filament::badge :color="$overview['integrations']['firebase_credentials_reference_set'] ? 'success' : 'gray'">{{ $overview['integrations']['firebase_credentials_reference_set'] ? 'Set' : 'Not Set' }}</x-filament::badge></dd></div>
                </dl>
                <p class="mt-4 text-xs text-gray-500">{{ $overview['integrations']['detail'] }}</p>
                <div class="mt-5 flex justify-end"><x-filament::button tag="a" color="gray" href="/admin/system-settings">{{ $locale === 'ar' ? 'الإعدادات والتكاملات' : ($locale === 'fr' ? 'Paramètres et intégrations' : 'Settings & integrations') }}</x-filament::button></div>
            </section>
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="ops-actions-heading">
            <h2 id="ops-actions-heading" class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'إجراءات التشغيل المدعومة' : ($locale === 'fr' ? 'Actions opérationnelles prises en charge' : 'Supported operational actions') }}</h2>
            <p class="mt-1 text-sm text-gray-600">{{ $locale === 'ar' ? 'لا يضيف هذا المركز أوامر عامة جديدة. ينتقل فقط إلى الأسطح التي تمتلك عقد تشغيل معتمد بالفعل.' : ($locale === 'fr' ? 'Ce centre n’ajoute aucune commande générique. Il dirige uniquement vers des surfaces déjà autorisées par un contrat opérationnel.' : 'This control center adds no generic command surface. It links only to already-authorized operational contracts.') }}</p>
            <div class="mt-4 flex flex-wrap gap-2">
                <x-filament::button tag="a" href="/admin/account-operations">{{ $locale === 'ar' ? 'الحسابات واسترداد الجلسات' : ($locale === 'fr' ? 'Comptes et récupération des sessions' : 'Accounts & session recovery') }}</x-filament::button>
                <x-filament::button tag="a" color="gray" href="/admin/content-ingestion-operations">{{ $locale === 'ar' ? 'تشغيل المحتوى وإعادة المحاولة' : ($locale === 'fr' ? 'Ingestion contenu et retry' : 'Content ingestion & retry') }}</x-filament::button>
                <x-filament::button tag="a" color="gray" href="/admin/system-settings">{{ $locale === 'ar' ? 'سجل الإعدادات' : ($locale === 'fr' ? 'Registre des paramètres' : 'Settings registry') }}</x-filament::button>
            </div>
        </section>
    </div>
</x-filament-panels::page>
