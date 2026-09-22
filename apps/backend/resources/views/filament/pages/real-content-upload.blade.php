<x-filament-panels::page>
    @php
        $locale = app()->getLocale();
        $rtl = $locale === 'ar';
        $label = static fn (string $ar, string $en, string $fr): string => $rtl ? $ar : ($locale === 'fr' ? $fr : $en);
        $tracks = $this->academicTrackOptions();
        $requests = $this->recentRequests();
    @endphp

    <div dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-real-content-upload">
        <x-admin.operational-banner
            severity="success"
            :title="$label('ChatGPT خارج النظام — بدون أي AI داخل MODRIK', 'ChatGPT stays outside MODRIK — zero in-app AI', 'ChatGPT reste hors MODRIK — aucune IA intégrée')"
            :message="$label(
                'هنا لا ترفع الكتاب إلى MODRIK. أنشئ فقط حزمة إعداد للصف والمادة، ثم ارفع الحزمة والكتاب هنا في محادثة ChatGPT. بعد تجهيز المحتوى ترجع الناتج إلى MODRIK للمراجعة والنشر.',
                'Do not upload the book to MODRIK here. Create only a preparation package for the year and subject, then upload that package and the book to normal ChatGPT. Bring the generated result back to MODRIK for review and publication. No PDF upload here, no OCR, no splitting and no question generation inside MODRIK.',
                'Ne téléversez pas le livre dans MODRIK ici. Créez seulement le paquet de préparation, utilisez ChatGPT manuellement, puis renvoyez le résultat à MODRIK.'
            )"
        />

        <section class="modrik-panel">
            <div class="modrik-panel-header"><div><h2 class="modrik-panel-title">{{ $label('الخطة الفعلية', 'Actual workflow', 'Flux réel') }}</h2></div></div>
            <div class="modrik-panel-body">
                <ol class="grid gap-3 md:grid-cols-4 text-sm">
                    <li class="rounded-xl border p-4"><strong>1.</strong> {{ $label('اختر Year والمادة وأنشئ الحزمة.', 'Choose Year and Subject and create the package.', 'Choisissez l’année et la matière.') }}</li>
                    <li class="rounded-xl border p-4"><strong>2.</strong> {{ $label('حمّل ملف Preparation JSON.', 'Download the Preparation JSON.', 'Téléchargez le JSON de préparation.') }}</li>
                    <li class="rounded-xl border p-4"><strong>3.</strong> {{ $label('ارفع الكتاب + الملف هنا في ChatGPT؛ ChatGPT يجهز JSON/Content Pack.', 'Upload the book + package to ChatGPT; ChatGPT prepares the JSON/Content Pack.', 'Fournissez le livre + paquet à ChatGPT.') }}</li>
                    <li class="rounded-xl border p-4"><strong>4.</strong> {{ $label('في MODRIK ارفع الناتج ثم Validate → Review → Publish.', 'In MODRIK upload the result, then Validate → Review → Publish.', 'Dans MODRIK : importer → valider → réviser → publier.') }}</li>
                </ol>
            </div>
        </section>

        @if ($tracks === [])
            <section class="modrik-panel">
                <x-admin.empty-state
                    :title="$label('لا يوجد مسار أكاديمي حقيقي بعد', 'No real academic track yet', 'Aucun parcours académique réel')"
                    :message="$label('أنشئ Year 6 أو Year 7 أولًا من الكتالوج الأكاديمي.', 'Create Year 6 or Year 7 in Academic Catalogue first.', 'Créez d’abord Year 6 ou Year 7.')"
                >
                    <x-filament::button tag="a" :href="\App\Filament\Pages\AcademicCatalogue::getUrl()">{{ \App\Filament\Pages\AcademicCatalogue::getNavigationLabel() }}</x-filament::button>
                </x-admin.empty-state>
            </section>
        @else
            <section class="modrik-panel">
                <div class="modrik-panel-header">
                    <div>
                        <h2 class="modrik-panel-title">{{ $label('إنشاء حزمة ChatGPT', 'Create ChatGPT package', 'Créer le paquet ChatGPT') }}</h2>
                        <p class="modrik-panel-subtitle">{{ $label('لا يوجد رفع PDF هنا، ولا OCR، ولا تقسيم، ولا توليد أسئلة داخل MODRIK.', 'No PDF upload here, no OCR, no splitting and no question generation inside MODRIK.', 'Aucun PDF, OCR, découpage ou génération dans MODRIK.') }}</p>
                    </div>
                </div>
                <div class="modrik-panel-body space-y-5">
                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2">
                            <span class="text-sm font-semibold">{{ $label('السنة / المسار', 'Year / academic track', 'Année / parcours') }}</span>
                            <x-filament::input.wrapper>
                                <x-filament::input.select wire:model="academicTrackId">
                                    <option value="">{{ $label('اختر المسار', 'Choose track', 'Choisir le parcours') }}</option>
                                    @foreach ($tracks as $id => $track)<option value="{{ $id }}">{{ $track }}</option>@endforeach
                                </x-filament::input.select>
                            </x-filament::input.wrapper>
                            @error('academicTrackId') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                        </label>

                        <label class="space-y-2">
                            <span class="text-sm font-semibold">{{ $label('المادة', 'Subject', 'Matière') }}</span>
                            <x-filament::input.wrapper><x-filament::input wire:model="subjectLabel" placeholder="Science / Math / English" /></x-filament::input.wrapper>
                            @error('subjectLabel') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                        </label>
                    </div>

                    <div class="grid gap-5 lg:grid-cols-2">
                        <fieldset class="rounded-xl border p-4">
                            <legend class="px-2 text-sm font-semibold">{{ $label('لغات المحتوى', 'Content languages', 'Langues') }}</legend>
                            <div class="mt-2 flex flex-wrap gap-5">
                                @foreach (['ar' => 'AR', 'en' => 'EN', 'fr' => 'FR'] as $value => $copy)
                                    <label class="flex items-center gap-2"><input type="checkbox" value="{{ $value }}" wire:model="locales" class="rounded" /><span>{{ $copy }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>

                        <fieldset class="rounded-xl border p-4">
                            <legend class="px-2 text-sm font-semibold">{{ $label('المطلوب من ChatGPT', 'What ChatGPT should prepare', 'Contenu demandé') }}</legend>
                            <div class="mt-2 grid gap-3">
                                @foreach ([
                                    'lesson' => $label('دروس', 'Lessons', 'Leçons'),
                                    'practice_quiz' => $label('أسئلة وتدريبات', 'Practice questions', 'Questions'),
                                    'mock_exam' => $label('اختبار تجريبي', 'Mock exam', 'Examen blanc'),
                                ] as $value => $copy)
                                    <label class="flex items-center gap-2"><input type="checkbox" value="{{ $value }}" wire:model="contentTypes" class="rounded" /><span>{{ $copy }}</span></label>
                                @endforeach
                            </div>
                        </fieldset>
                    </div>

                    <label class="block max-w-sm space-y-2">
                        <span class="text-sm font-semibold">{{ $label('أقصى عدد أسئلة لكل اختبار', 'Max questions per quiz', 'Questions max') }}</span>
                        <x-filament::input.wrapper><x-filament::input type="number" min="1" max="200" wire:model="maximumQuestionsPerQuiz" /></x-filament::input.wrapper>
                    </label>

                    <x-filament::button wire:click="createPreparation" wire:loading.attr="disabled">
                        {{ $label('إنشاء حزمة ChatGPT', 'Create ChatGPT package', 'Créer le paquet ChatGPT') }}
                    </x-filament::button>
                </div>
            </section>
        @endif

        @if ($this->lastResult !== [])
            <section class="modrik-panel border-success-300">
                <div class="modrik-panel-header">
                    <div>
                        <h2 class="modrik-panel-title">{{ $label('الحزمة جاهزة', 'Package ready', 'Paquet prêt') }}</h2>
                        <p class="modrik-panel-subtitle">{{ $label('الآن حمّلها وارفعها مع الكتاب هنا في ChatGPT. لم ينشئ MODRIK أي محتوى.', 'Now download it and upload it with the book to ChatGPT. MODRIK generated no content.', 'Téléchargez-la et utilisez ChatGPT avec le livre.') }}</p>
                    </div>
                    <x-filament::badge color="success">manual_chatgpt</x-filament::badge>
                </div>
                <div class="modrik-panel-body flex flex-wrap gap-3">
                    <x-filament::button wire:click="downloadPreparationPackage" icon="heroicon-o-arrow-down-tray">{{ $label('تحميل Preparation JSON', 'Download Preparation JSON', 'Télécharger le JSON') }}</x-filament::button>
                    <x-filament::button color="gray" tag="a" :href="\App\Filament\Pages\ContentPreparationWizard::getUrl(['request' => $this->lastResult['preparation_request_id'] ?? ''])">{{ $label('فتح الطلب لرفع ناتج ChatGPT', 'Open request to upload ChatGPT result', 'Ouvrir la demande pour importer le résultat') }}</x-filament::button>
                </div>
            </section>
        @endif

        <section class="modrik-panel">
            <div class="modrik-panel-header"><div><h2 class="modrik-panel-title">{{ $label('آخر حزم الإعداد', 'Recent preparation packages', 'Paquets récents') }}</h2></div><x-filament::badge color="gray">{{ count($requests) }}</x-filament::badge></div>
            <div class="modrik-panel-body">
                @if ($requests === [])
                    <p class="text-sm text-gray-500">{{ $label('لا توجد حزم إعداد بعد.', 'No preparation packages yet.', 'Aucun paquet.') }}</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y text-sm">
                            <thead><tr><th class="px-3 py-2 text-start">Year</th><th class="px-3 py-2 text-start">Track</th><th class="px-3 py-2 text-start">{{ $label('المادة', 'Subject', 'Matière') }}</th><th class="px-3 py-2 text-start">{{ $label('الحالة', 'Status', 'Statut') }}</th><th></th></tr></thead>
                            <tbody class="divide-y">
                            @foreach ($requests as $request)
                                <tr>
                                    <td class="px-3 py-3">{{ $request['year_level'] }}</td>
                                    <td class="px-3 py-3">{{ $request['track_reference'] }}</td>
                                    <td class="px-3 py-3">{{ implode(' · ', $request['subjects']) }}</td>
                                    <td class="px-3 py-3"><x-filament::badge color="info">{{ $request['status'] }}</x-filament::badge></td>
                                    <td class="px-3 py-3 text-end"><x-filament::button size="sm" tag="a" :href="\App\Filament\Pages\ContentPreparationWizard::getUrl(['request' => $request['id']])">{{ $label('فتح', 'Open', 'Ouvrir') }}</x-filament::button></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </section>
    </div>
</x-filament-panels::page>
