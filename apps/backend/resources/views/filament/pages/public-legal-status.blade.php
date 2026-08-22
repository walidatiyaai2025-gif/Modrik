<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($status = $this->contractStatus())
    @php($pages = $this->publicPages())
    @php($blockers = $this->legalBlockers())

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-public-legal-status">
        <x-admin.operational-banner
            severity="warning"
            :title="$locale === 'ar' ? 'حدود السلطة القانونية' : ($locale === 'fr' ? 'Limite d’autorité juridique' : 'Legal authority boundary')"
            :message="$locale === 'ar'
                ? 'الصفحات القانونية الحالية نماذج محجوبة حتى يقدّم المالك/القانوني الحقائق المعتمدة. لا توجد هنا أداة تحرير أو نشر قانوني لأن الـBackend لا يملك عقدًا معتمدًا لذلك.'
                : ($locale === 'fr'
                    ? 'Les pages juridiques actuelles restent des modèles bloqués jusqu’à validation des faits par le propriétaire/juridique. Aucun outil d’édition ou de publication juridique n’est exposé car aucun contrat Backend approuvé n’existe.'
                    : 'Current legal pages remain blocked templates until owner/legal supplies approved facts. No legal edit or publish control is exposed because no approved Backend mutation contract exists.')"
        />

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4" aria-label="{{ $locale === 'ar' ? 'ملخص الحالة' : ($locale === 'fr' ? 'Résumé de l’état' : 'Status summary') }}">
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $locale === 'ar' ? 'الصفحات العامة' : ($locale === 'fr' ? 'Pages publiques' : 'Public pages') }}</div>
                <div class="mt-2 text-3xl font-bold text-gray-950">{{ count($pages) }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $locale === 'ar' ? 'اللغات' : ($locale === 'fr' ? 'Langues' : 'Locales') }}</div>
                <div class="mt-2 text-lg font-semibold text-gray-950">AR · EN · FR</div>
                <div class="mt-1 text-sm text-gray-500">RTL: {{ strtoupper($status['rtl_locale']) }}</div>
            </div>
            <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-gray-500">{{ $locale === 'ar' ? 'عقد التعديل' : ($locale === 'fr' ? 'Contrat de mutation' : 'Mutation contract') }}</div>
                <div class="mt-2"><x-filament::badge color="gray">{{ $status['mutation_contract'] }}</x-filament::badge></div>
            </div>
            <div class="rounded-2xl border border-warning-200 bg-warning-50 p-5 shadow-sm">
                <div class="text-xs font-semibold uppercase tracking-[0.16em] text-warning-700">{{ $locale === 'ar' ? 'الاعتماد القانوني' : ($locale === 'fr' ? 'Approbation juridique' : 'Legal approval') }}</div>
                <div class="mt-2"><x-filament::badge color="warning">{{ $status['legal_approval'] }}</x-filament::badge></div>
            </div>
        </section>

        <section class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm" aria-labelledby="public-pages-heading">
            <div class="border-b border-gray-100 px-5 py-4">
                <h2 id="public-pages-heading" class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'فهرس السطح العام' : ($locale === 'fr' ? 'Catalogue de la surface publique' : 'Public surface catalogue') }}</h2>
                <p class="mt-1 text-sm text-gray-600">{{ $locale === 'ar' ? 'المصدر البرمجي الموثوق: apps/web/src/public-site/content.ts' : ($locale === 'fr' ? 'Source de vérité du contenu : apps/web/src/public-site/content.ts' : 'Content source of truth: apps/web/src/public-site/content.ts') }}</p>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead class="bg-gray-50 text-gray-600">
                        <tr>
                            <th class="px-4 py-3 text-start font-semibold">{{ $locale === 'ar' ? 'المفتاح' : ($locale === 'fr' ? 'Clé' : 'Key') }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ $locale === 'ar' ? 'المسار' : ($locale === 'fr' ? 'Route' : 'Route') }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ $locale === 'ar' ? 'النوع' : ($locale === 'fr' ? 'Type' : 'Kind') }}</th>
                            <th class="px-4 py-3 text-start font-semibold">{{ $locale === 'ar' ? 'الحالة' : ($locale === 'fr' ? 'État' : 'State') }}</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach ($pages as $page)
                            <tr wire:key="public-page-{{ $page['key'] }}">
                                <td class="px-4 py-3 font-medium text-gray-950">{{ $page['key'] }}</td>
                                <td class="px-4 py-3 font-mono text-xs text-gray-700" dir="ltr">{{ $page['slug'] }}</td>
                                <td class="px-4 py-3 text-gray-700">{{ str_replace('_', ' ', $page['kind']) }}</td>
                                <td class="px-4 py-3">
                                    @if ($page['template'])
                                        <x-filament::badge color="warning">{{ $locale === 'ar' ? 'نموذج / غير معتمد' : ($locale === 'fr' ? 'Modèle / non approuvé' : 'Template / unapproved') }}</x-filament::badge>
                                    @else
                                        <x-filament::badge color="success">{{ $locale === 'ar' ? 'محتوى عام حالي' : ($locale === 'fr' ? 'Contenu public actuel' : 'Current public content') }}</x-filament::badge>
                                    @endif
                                    <div class="mt-1 text-xs text-gray-500">{{ $page['indexable'] ? 'indexable' : 'noindex' }}</div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="legal-blockers-heading">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 id="legal-blockers-heading" class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'المدخلات القانونية المحجوبة' : ($locale === 'fr' ? 'Entrées juridiques bloquantes' : 'Blocked owner/legal inputs') }}</h2>
                    <p class="mt-1 text-sm text-gray-600">{{ $locale === 'ar' ? 'هذه رموز متعمدة من عقد المحتوى العام، وليست بيانات ناقصة سيتم تخمينها.' : ($locale === 'fr' ? 'Ce sont des blocages explicites du contrat public, jamais des valeurs à deviner.' : 'These are explicit blockers from the public-content contract, never values to guess.') }}</p>
                </div>
                <x-filament::badge color="warning">{{ count($blockers) }}</x-filament::badge>
            </div>
            <div class="mt-4 grid gap-2 md:grid-cols-2 xl:grid-cols-3">
                @foreach ($blockers as $blocker)
                    <div class="rounded-xl border border-warning-100 bg-warning-50 px-3 py-2 font-mono text-xs text-warning-900" dir="ltr">{{ $blocker }}</div>
                @endforeach
            </div>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-gray-50 p-5" aria-labelledby="public-legal-boundary-heading">
            <h2 id="public-legal-boundary-heading" class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'ما لا تفعله هذه الشاشة' : ($locale === 'fr' ? 'Ce que cette page ne fait pas' : 'What this page intentionally does not do') }}</h2>
            <ul class="mt-3 list-disc space-y-2 ps-5 text-sm text-gray-700">
                <li>{{ $locale === 'ar' ? 'لا تحرر أو تنشر نصوصًا قانونية.' : ($locale === 'fr' ? 'Aucune édition ou publication de texte juridique.' : 'No legal text editing or publishing.') }}</li>
                <li>{{ $locale === 'ar' ? 'لا تخترع جهة قانونية أو اختصاصًا قضائيًا أو وسائل اتصال.' : ($locale === 'fr' ? 'Aucune entité, juridiction ou coordonnée inventée.' : 'No fabricated legal entity, jurisdiction or contact details.') }}</li>
                <li>{{ $locale === 'ar' ? 'لا تحول القوالب غير المعتمدة إلى سياسات نهائية.' : ($locale === 'fr' ? 'Aucun modèle non approuvé n’est transformé en politique finale.' : 'No unapproved template is promoted to a final policy.') }}</li>
            </ul>
        </section>
    </div>
</x-filament-panels::page>
