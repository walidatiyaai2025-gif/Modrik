<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($policy = $status['policy'])

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-advertising-safety">
        <x-admin.operational-banner
            :severity="$status['operator_enabled'] ? 'warning' : 'success'"
            :title="$locale === 'ar' ? 'الأمان له الأولوية دائمًا' : ($locale === 'fr' ? 'La sécurité reste prioritaire' : 'Safety always has precedence')"
            :message="$locale === 'ar' ? 'مفتاح لوحة الإدارة طبقة إيقاف إضافية فقط. لا يمكنه فتح مناطق No-Ad الثابتة أو تجاوز العمر/الموافقة أو سياسة Backend.' : ($locale === 'fr' ? 'Le commutateur Admin est uniquement un coupe-circuit supplémentaire. Il ne peut jamais ouvrir les zones No-Ad ni contourner l’âge, le consentement ou la politique Backend.' : 'The Admin switch is an additional kill switch only. It can never open immutable No-Ad Zones or bypass age, consent or Backend policy.')"
        >
            <div class="modrik-code mt-2 text-xs" dir="ltr">APP_ENV={{ $environment }}</div>
        </x-admin.operational-banner>

        <div class="grid gap-4 md:grid-cols-3">
            <x-admin.metric-card
                :label="$locale === 'ar' ? 'مفتاح المشغل' : ($locale === 'fr' ? 'Commutateur opérateur' : 'Operator switch')"
                :value="$status['operator_enabled'] ? 'Enabled' : 'OFF'"
                :detail="$locale === 'ar' ? 'OFF يمنع الإعلانات فورًا؛ ON لا يضمن الأهلية.' : ($locale === 'fr' ? 'OFF bloque immédiatement ; ON ne garantit pas l’éligibilité.' : 'OFF blocks immediately; ON does not guarantee eligibility.')"
            />
            <x-admin.metric-card
                :label="$locale === 'ar' ? 'وضع الاختبار' : ($locale === 'fr' ? 'Mode test' : 'Test mode')"
                :value="$status['test_mode'] ? 'Enabled' : 'Disabled'"
                :detail="$locale === 'ar' ? 'إعداد تشغيلي بإصدار وتدقيق' : ($locale === 'fr' ? 'Paramètre opérationnel versionné et audité' : 'Versioned, audited operational setting')"
            />
            <x-admin.metric-card
                :label="$locale === 'ar' ? 'إصدار سياسة Backend' : ($locale === 'fr' ? 'Version de politique Backend' : 'Backend policy version')"
                :value="$policy ? 'v'.$policy['version'] : 'Missing'"
                :detail="$policy ? ($policy['global_enabled'] ? 'Policy global enabled' : 'Policy global disabled') : 'Fail-closed: CONFIG_MISSING'"
            />
        </div>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="no-ad-zones-heading">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-danger-600">Internal non-editable</p>
                <h2 id="no-ad-zones-heading" class="mt-2 text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'مناطق منع الإعلانات الثابتة' : ($locale === 'fr' ? 'Zones sans publicité immuables' : 'Immutable No-Ad Zones') }}</h2>
                <p class="mt-1 text-sm leading-6 text-gray-500">{{ $locale === 'ar' ? 'تُعرض للشفافية التشغيلية فقط ولا توجد أي عناصر تعديل لها.' : ($locale === 'fr' ? 'Affichées uniquement pour la transparence opérationnelle ; aucun contrôle de modification n’est fourni.' : 'Displayed for operational transparency only; no edit control exists for these zones.') }}</p>
            </div>
            <div class="mt-5 flex flex-wrap gap-2">
                @foreach ($status['immutable_no_ad_zones'] as $zone)
                    <x-filament::badge color="danger">{{ $zone }}</x-filament::badge>
                @endforeach
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="placement-map-heading">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 id="placement-map-heading" class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'خريطة المواضع إلى المناطق' : ($locale === 'fr' ? 'Mappage emplacements → zones' : 'Placement → zone map') }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $locale === 'ar' ? 'المطابقة Backend-owned ولا تأتي من العميل.' : ($locale === 'fr' ? 'Le mappage appartient au Backend et ne vient jamais du client.' : 'Mapping is Backend-owned and never supplied by the client.') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500"><tr><th class="px-4 py-3 text-start">Placement</th><th class="px-4 py-3 text-start">Zone</th><th class="px-4 py-3 text-start">Immutable no-ad</th></tr></thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($status['placement_zones'] as $placement => $zone)
                            <tr><td class="modrik-code px-4 py-3 text-xs" dir="ltr">{{ $placement }}</td><td class="px-4 py-3">{{ $zone }}</td><td class="px-4 py-3"><x-filament::badge :color="in_array($zone, $status['immutable_no_ad_zones'], true) ? 'danger' : 'gray'">{{ in_array($zone, $status['immutable_no_ad_zones'], true) ? 'YES' : 'NO' }}</x-filament::badge></td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($policy)
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="policy-window-heading">
                <h2 id="policy-window-heading" class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'نافذة سياسة Backend الحالية' : ($locale === 'fr' ? 'Fenêtre de politique Backend actuelle' : 'Current Backend policy window') }}</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <div class="rounded-xl bg-gray-50 p-4"><div class="text-xs text-gray-500">Effective</div><div class="modrik-code mt-2 text-sm" dir="ltr">{{ $policy['effective_at'] }}</div></div>
                    <div class="rounded-xl bg-gray-50 p-4"><div class="text-xs text-gray-500">Expires</div><div class="modrik-code mt-2 text-sm" dir="ltr">{{ $policy['expires_at'] }}</div></div>
                </div>
            </section>
        @endif

        <div class="flex justify-end"><x-filament::button tag="a" href="/admin/system-settings">{{ $locale === 'ar' ? 'إدارة مفتاح الإيقاف' : ($locale === 'fr' ? 'Gérer le coupe-circuit' : 'Manage operator kill switch') }}</x-filament::button></div>
    </div>
</x-filament-panels::page>
