<x-filament-panels::page>
    @php
        $entries = $this->prompts();
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-prompt-library">
        <x-admin.operational-banner
            severity="info"
            :title="$label('إعداد يدوي فقط', 'Manual/offline preparation only', 'Préparation manuelle/hors ligne uniquement')"
            :message="$label(
                'هذه المكتبة لا تستدعي أي نموذج AI وقت التشغيل. انسخ الـPrompt واستخدمه يدويًا مع المصادر وحزمة الإعداد المرتبطة، ثم أعد الناتج عبر مسار الرفع والتحقق المحكوم.',
                'This library never calls an AI model at runtime. Copy the prompt, use it manually with the bound sources/preparation package, then return the result through the governed upload and validation workflow.',
                'Cette bibliothèque n’appelle aucun modèle d’IA au runtime. Copiez le prompt, utilisez-le manuellement avec les sources et le package liés, puis renvoyez le résultat via le flux gouverné de téléversement et validation.'
            )"
        />

        @foreach ($entries as $entry)
            <article class="modrik-panel" wire:key="prompt-library-{{ $entry['id'] }}-{{ $entry['version'] }}">
                <div class="modrik-panel-header">
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <x-filament::badge color="success">{{ $entry['status'] }}</x-filament::badge>
                            <x-filament::badge color="info">v{{ $entry['version'] }}</x-filament::badge>
                            <x-filament::badge color="gray">{{ $entry['compatible_schema'] }}</x-filament::badge>
                        </div>
                        <h2 class="mt-3 break-words text-base font-bold text-gray-950">{{ $entry['id'] }}</h2>
                        <p class="mt-1 text-sm text-gray-600">{{ $entry['purpose'] }}</p>
                    </div>
                </div>

                <div class="modrik-panel-body space-y-6">
                    <dl class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="text-xs font-semibold text-gray-500">{{ $label('الإصدار', 'Version', 'Version') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-950">{{ $entry['version'] }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="text-xs font-semibold text-gray-500">{{ $label('Schema المتوافق', 'Compatible schema', 'Schéma compatible') }}</dt>
                            <dd class="modrik-code mt-1 text-sm text-gray-950">{{ $entry['compatible_schema'] }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="text-xs font-semibold text-gray-500">{{ $label('اعتماد وقت التشغيل', 'Runtime dependency', 'Dépendance runtime') }}</dt>
                            <dd class="mt-1 text-sm font-semibold text-gray-950">{{ $entry['runtime_dependency'] }}</dd>
                        </div>
                        <div class="rounded-xl bg-gray-50 p-3">
                            <dt class="text-xs font-semibold text-gray-500">{{ $label('المصدر القانوني', 'Canonical source', 'Source canonique') }}</dt>
                            <dd class="modrik-code mt-1 break-all text-xs text-gray-700">{{ $entry['source'] }}</dd>
                        </div>
                    </dl>

                    <section x-data="{ copied: false }" class="space-y-3" aria-labelledby="prompt-preview-title">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <h3 id="prompt-preview-title" class="text-sm font-bold text-gray-950">
                                    {{ $label('معاينة الـPrompt كاملة', 'Full prompt preview', 'Aperçu complet du prompt') }}
                                </h3>
                                <p class="mt-1 text-xs text-gray-500">
                                    {{ $label('انسخ النص كما هو داخل سير العمل اليدوي فقط.', 'Copy this exact text only into the manual preparation workflow.', 'Copiez ce texte exact uniquement dans le flux de préparation manuelle.') }}
                                </p>
                            </div>
                            <x-filament::button
                                type="button"
                                icon="heroicon-o-clipboard"
                                x-on:click="navigator.clipboard.writeText($refs.promptBody.value).then(() => { copied = true; setTimeout(() => copied = false, 1500) })"
                            >
                                <span x-show="! copied">{{ $label('نسخ الـPrompt', 'Copy Prompt', 'Copier le prompt') }}</span>
                                <span x-cloak x-show="copied">{{ $label('تم النسخ', 'Copied', 'Copié') }}</span>
                            </x-filament::button>
                        </div>
                        <textarea
                            x-ref="promptBody"
                            readonly
                            rows="24"
                            class="modrik-code block w-full resize-y rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs leading-6 text-gray-800"
                            dir="ltr"
                        >{{ $entry['prompt'] }}</textarea>
                    </section>

                    <section class="space-y-3" aria-labelledby="prompt-sample-title">
                        <div>
                            <h3 id="prompt-sample-title" class="text-sm font-bold text-gray-950">
                                {{ $label('نموذج ناتج متوافق', 'Compatible sample output', 'Exemple de sortie compatible') }}
                            </h3>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ $label('نموذج تركيبي فقط للتحقق من الشكل؛ ليس محتوى Pilot حقيقيًا ولا دليل حقوق.', 'Synthetic contract sample only; it is not real pilot content or rights evidence.', 'Exemple contractuel synthétique uniquement ; ce n’est ni du contenu pilote réel ni une preuve de droits.') }}
                            </p>
                        </div>
                        <pre class="modrik-code max-h-[32rem] overflow-auto rounded-xl border border-gray-200 bg-gray-50 p-4 text-xs leading-6 text-gray-800" dir="ltr">{{ $entry['sample_output'] }}</pre>
                    </section>

                    <section class="space-y-2" aria-labelledby="prompt-history-title">
                        <h3 id="prompt-history-title" class="text-sm font-bold text-gray-950">
                            {{ $label('سجل الإصدارات', 'Version history', 'Historique des versions') }}
                        </h3>
                        @foreach ($entry['history'] as $history)
                            <div class="flex flex-wrap items-center gap-2 rounded-xl border border-gray-200 bg-white p-3 text-sm">
                                <x-filament::badge color="success">{{ $history['status'] }}</x-filament::badge>
                                <span class="font-semibold">v{{ $history['version'] }}</span>
                                <span class="text-gray-500">{{ $history['source'] }}</span>
                            </div>
                        @endforeach
                    </section>
                </div>
            </article>
        @endforeach
    </div>
</x-filament-panels::page>
