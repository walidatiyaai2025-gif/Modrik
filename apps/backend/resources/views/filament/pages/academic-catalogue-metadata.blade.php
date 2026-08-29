<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($years = $this->years())
    @php($tracks = $this->tracks())

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-academic-catalogue-metadata">
        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-gray-600">
                {{ $locale === 'ar'
                    ? 'أدخل أسماء السنوات وترتيب العرض فقط من مصادر تشغيلية معتمدة. لا تنشئ هذه الصفحة حقائق منهجية أو تعليمية.'
                    : ($locale === 'fr'
                        ? 'Saisissez uniquement les libellés d’année et l’ordre d’affichage provenant de sources opérateur approuvées. Cette page ne crée aucun fait curriculaire.'
                        : 'Enter only operator-approved year labels and display ordering. This page does not create curriculum or academic facts.') }}
            </p>
        </section>

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'بيانات السنوات' : ($locale === 'fr' ? 'Métadonnées des années' : 'Year metadata') }}</h2>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full divide-y divide-gray-200 text-sm">
                    <thead><tr>
                        <th class="px-3 py-2 text-start">{{ $locale === 'ar' ? 'المفتاح' : ($locale === 'fr' ? 'Clé' : 'Key') }}</th>
                        <th class="px-3 py-2 text-start">AR</th><th class="px-3 py-2 text-start">EN</th><th class="px-3 py-2 text-start">FR</th>
                        <th class="px-3 py-2 text-start">{{ $locale === 'ar' ? 'الترتيب' : ($locale === 'fr' ? 'Ordre' : 'Order') }}</th>
                        <th class="px-3 py-2 text-end"></th>
                    </tr></thead>
                    <tbody class="divide-y divide-gray-100">
                    @foreach ($years as $year)
                        <tr>
                            <td class="px-3 py-2 font-mono text-xs">{{ $year['year_level'] }}</td>
                            <td class="px-3 py-2">{{ $year['labels']['ar'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $year['labels']['en'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $year['labels']['fr'] ?? '—' }}</td>
                            <td class="px-3 py-2">{{ $year['display_order'] }}</td>
                            <td class="px-3 py-2 text-end"><x-filament::button size="sm" wire:click="beginYear(@js($year['year_level']))">{{ $locale === 'ar' ? 'تعديل' : ($locale === 'fr' ? 'Modifier' : 'Edit') }}</x-filament::button></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        @if ($yearLevel)
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-gray-950">{{ $yearLevel }}</h2>
                <div class="mt-4 grid gap-4 md:grid-cols-2">
                    <label><span class="text-sm font-medium">AR</span><input class="fi-input mt-1 block w-full rounded-lg border-gray-300" maxlength="160" wire:model="yearLabelAr" /></label>
                    <label><span class="text-sm font-medium">EN</span><input class="fi-input mt-1 block w-full rounded-lg border-gray-300" maxlength="160" wire:model="yearLabelEn" /></label>
                    <label><span class="text-sm font-medium">FR</span><input class="fi-input mt-1 block w-full rounded-lg border-gray-300" maxlength="160" wire:model="yearLabelFr" /></label>
                    <label><span class="text-sm font-medium">{{ $locale === 'ar' ? 'ترتيب السنة' : ($locale === 'fr' ? 'Ordre de l’année' : 'Year order') }}</span><input type="number" class="fi-input mt-1 block w-full rounded-lg border-gray-300" wire:model="yearDisplayOrder" /></label>
                </div>
                @error('yearLabelEn') <p class="mt-2 text-sm text-danger-600">{{ $message }}</p> @enderror
                <div class="mt-4"><x-filament::button wire:click="saveYear">{{ $locale === 'ar' ? 'حفظ' : ($locale === 'fr' ? 'Enregistrer' : 'Save') }}</x-filament::button></div>
            </section>
        @endif

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
            <h2 class="text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'ترتيب المسارات' : ($locale === 'fr' ? 'Ordre des parcours' : 'Track ordering') }}</h2>
            <div class="mt-4 space-y-2">
                @foreach ($tracks as $track)
                    @php($decodedTitle = json_decode((string) $track['title'], true))
                    @php($trackTitle = is_array($decodedTitle) ? ($decodedTitle[$locale] ?? $decodedTitle['en'] ?? $track['id']) : $track['id'])
                    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 px-4 py-3">
                        <div><div class="font-semibold">{{ $trackTitle }}</div><div class="text-xs text-gray-500">{{ $track['year_level'] }} · {{ $track['display_order'] }}</div></div>
                        <x-filament::button size="sm" wire:click="beginTrack('{{ $track['id'] }}')">{{ $locale === 'ar' ? 'ترتيب' : ($locale === 'fr' ? 'Ordonner' : 'Order') }}</x-filament::button>
                    </div>
                @endforeach
            </div>
        </section>

        @if ($trackId)
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm">
                <label><span class="text-sm font-medium">{{ $locale === 'ar' ? 'ترتيب عرض المسار' : ($locale === 'fr' ? 'Ordre d’affichage du parcours' : 'Track display order') }}</span><input type="number" class="fi-input mt-1 block w-full rounded-lg border-gray-300" wire:model="trackDisplayOrder" /></label>
                <div class="mt-4"><x-filament::button wire:click="saveTrack">{{ $locale === 'ar' ? 'حفظ الترتيب' : ($locale === 'fr' ? 'Enregistrer l’ordre' : 'Save order') }}</x-filament::button></div>
            </section>
        @endif
    </div>
</x-filament-panels::page>
