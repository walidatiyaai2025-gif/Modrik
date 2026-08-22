<x-filament-panels::page>
    @php
        $isAr = app()->getLocale() === 'ar';
        $isFr = app()->getLocale() === 'fr';
        $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        $steps = $this->lifecycle();
    @endphp

    <div dir="{{ $isAr ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-content-operations">
        <x-admin.operational-banner
            severity="info"
            :title="$label('سلطة النشر محفوظة', 'Publication authority is preserved', 'L’autorité de publication est préservée')"
            :message="$label('هذه الصفحة ترشد المشغّل بين الأسطح المصرح بها فقط. لا تتجاوز التحقق أو الحقوق أو المراجعة، ولا تمنح المحتوى الذي ينشئه المستخدم صلاحية النشر التلقائي.', 'This hub only guides operators through authorized surfaces. It does not bypass validation, rights, review, or the no-UGC-auto-promotion rule.', 'Ce centre guide uniquement vers les surfaces autorisées. Il ne contourne ni validation, ni droits, ni révision, ni la règle interdisant la promotion automatique de l’UGC.')"
        />

        <section class="modrik-panel" aria-labelledby="content-lifecycle-title">
            <div class="modrik-panel-header">
                <div>
                    <h2 id="content-lifecycle-title" class="modrik-panel-title">{{ $label('دورة المحتوى الرسمي', 'Official content lifecycle', 'Cycle du contenu officiel') }}</h2>
                    <p class="modrik-panel-subtitle">{{ $label('اتبع المراحل بالترتيب. أي حاجز ظاهر يجب حله في سطحه المخصص بدلاً من SQL أو روابط داخلية.', 'Follow the lifecycle in order. Resolve every blocker in its supported surface rather than through SQL or hidden URLs.', 'Suivez les étapes dans l’ordre. Résolvez chaque blocage dans sa surface dédiée, jamais par SQL ou URL cachée.') }}</p>
                </div>
                <x-filament::badge color="info">{{ count($steps) }} {{ $label('مراحل', 'stages', 'étapes') }}</x-filament::badge>
            </div>

            <div class="modrik-panel-body">
                <ol class="grid gap-4 lg:grid-cols-2" aria-label="{{ $label('دورة عمليات المحتوى', 'Content operations lifecycle', 'Cycle des opérations de contenu') }}">
                    @foreach ($steps as $index => $step)
                        <li class="rounded-2xl border border-gray-200 bg-white p-5" data-testid="content-operation-step-{{ $index + 1 }}">
                            <div class="flex items-start justify-between gap-4">
                                <div class="min-w-0">
                                    <div class="flex items-center gap-2"><span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-primary-50 text-sm font-bold text-primary-700">{{ $index + 1 }}</span><h3 class="text-base font-bold text-gray-950">{{ $step['label'] }}</h3></div>
                                    <p class="mt-3 text-sm leading-6 text-gray-600">{{ $step['description'] }}</p>
                                </div>
                                <x-filament::badge :color="$step['state'] === 'active' ? 'success' : 'warning'">{{ $step['state'] }}</x-filament::badge>
                            </div>
                            <div class="mt-5"><x-filament::button tag="a" :href="$step['url']" icon="heroicon-o-arrow-up-right">{{ $label('فتح المرحلة', 'Open stage', 'Ouvrir l’étape') }}</x-filament::button></div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="modrik-panel" aria-labelledby="content-next-capabilities-title">
            <div class="modrik-panel-header"><div><h2 id="content-next-capabilities-title" class="modrik-panel-title">{{ $label('قدرات قيد الاستكمال', 'Capabilities still being completed', 'Fonctions encore en cours') }}</h2><p class="modrik-panel-subtitle">{{ $label('إعادة بناء المنهج ما زالت فجوة تشغيلية صريحة، ولن يتم تقديم روابط وهمية أو تجاوزات يدوية.', 'Curriculum rebuild remains an explicit operational gap; this hub does not invent fake routes or manual bypasses.', 'La reconstruction du programme reste un écart opérationnel explicite ; aucun faux lien ni contournement manuel n’est créé.') }}</p></div></div>
            <div class="modrik-panel-body"><x-admin.operational-banner severity="warning" :title="$label('إعادة بناء المنهج', 'Curriculum rebuild', 'Reconstruction du programme')" :message="$label('المعاينة والفرق والإرجاع الآمن ما زالت ضمن نطاق Issue #182.', 'Preview, diff, versioned rebuild and safe rollback remain in Issue #182 scope.', 'Aperçu, diff, reconstruction versionnée et retour sûr restent dans le périmètre de Issue #182.')" /></div>
        </section>
    </div>
</x-filament-panels::page>
