<x-filament-panels::page>
    <div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-content-rights-review">
        @php
            $rows = $this->rows();
            $isAr = app()->getLocale() === 'ar';
            $isFr = app()->getLocale() === 'fr';
            $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        @endphp

        <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="rights-journey-heading">
            <div class="mb-4 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">
                        {{ $label('مسار نشر المحتوى', 'Content publication journey', 'Parcours de publication') }}
                    </p>
                    <h2 id="rights-journey-heading" class="mt-2 text-lg font-semibold text-gray-950">
                        {{ $label('مراجعة الحقوق قبل المراجعة النهائية', 'Clear rights before final review', 'Valider les droits avant la revue finale') }}
                    </h2>
                    <p class="mt-1 text-sm leading-6 text-gray-500">
                        {{ $label('هذه الخطوة لا تمنح حقوقًا تلقائيًا. اعتمد أساس الحقوق فقط عندما يكون مدعومًا بدليل حقيقي يقدمه المالك.', 'This step never invents rights. Approve a rights basis only when real owner-controlled evidence supports it.', 'Cette étape n’invente jamais de droits. Approuvez une base juridique uniquement avec une preuve réelle contrôlée par le propriétaire.') }}
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    <x-filament::button color="gray" size="sm" tag="a" :href="\App\Filament\Pages\ContentPreparationWizard::getUrl()">
                        {{ $label('الرجوع لإعداد المحتوى', 'Back to preparation', 'Retour à la préparation') }}
                    </x-filament::button>
                    <x-filament::button size="sm" tag="a" :href="\App\Filament\Pages\ContentReviewQueue::getUrl()">
                        {{ $label('فتح المراجعة النهائية', 'Open final review', 'Ouvrir la revue finale') }}
                    </x-filament::button>
                </div>
            </div>

            <x-admin.step-rail
                :label="$label('الخطوات الرسمية لنشر المحتوى', 'Official content publication steps', 'Étapes officielles de publication')"
                :steps="[
                    [
                        'state' => 'complete',
                        'label' => $label('المسار الأكاديمي', 'Academic track', 'Parcours académique'),
                        'description' => $label('المسار والنطاق الأكاديمي مسجلان.', 'Academic track and scope are registered.', 'Le parcours et le périmètre sont enregistrés.'),
                        'url' => \App\Filament\Pages\AcademicCatalogue::getUrl(),
                        'action' => $label('فتح الكتالوج', 'Open catalogue', 'Ouvrir le catalogue'),
                    ],
                    [
                        'state' => 'complete',
                        'label' => $label('الإعداد والتحقق', 'Preparation and validation', 'Préparation et validation'),
                        'description' => $label('تم إنشاء الحزمة وإرجاعها للتحقق.', 'Bundle preparation and return validation precede rights review.', 'La préparation et la validation précèdent la revue des droits.'),
                        'url' => \App\Filament\Pages\ContentPreparationWizard::getUrl(),
                        'action' => $label('فتح الإعداد', 'Open preparation', 'Ouvrir la préparation'),
                    ],
                    [
                        'state' => 'active',
                        'label' => $label('مراجعة الحقوق', 'Rights review', 'Revue des droits'),
                        'description' => $label('تحقق من الأساس والدليل الحقيقي قبل الاعتماد.', 'Verify the real legal basis and evidence before approval.', 'Vérifiez la base et la preuve avant approbation.'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $label('Dry-run والمراجعة', 'Dry-run and review', 'Dry-run et revue'),
                        'description' => $label('بعد اعتماد الحقوق راجع الفروقات والعوائق.', 'After rights approval, inspect deterministic differences and blockers.', 'Après approbation des droits, examinez les différences et blocages.'),
                        'url' => \App\Filament\Pages\ContentReviewQueue::getUrl(),
                        'action' => $label('الانتقال للمراجعة', 'Continue to review', 'Continuer vers la revue'),
                    ],
                    [
                        'state' => 'pending',
                        'label' => $label('الاستيراد والنشر', 'Import and publish', 'Importer et publier'),
                        'description' => $label('المحتوى المعتمد فقط ينتقل للاستيراد الرسمي ثم النشر.', 'Only approved content proceeds to canonical import and publication.', 'Seul le contenu approuvé passe à l’import puis à la publication.'),
                    ],
                ]"
            />
        </section>

        <div class="flex flex-wrap items-center justify-end gap-2">
            @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $localeLabel)
                <x-filament::button size="sm" :color="app()->getLocale() === $locale ? 'primary' : 'gray'" wire:click="setLocale('{{ $locale }}')">
                    {{ $localeLabel }}
                </x-filament::button>
            @endforeach
        </div>

        @if ($rows === [])
            <x-admin.empty-state
                :title="$label('لا توجد حزم تنتظر مراجعة الحقوق', 'No content packs await rights review', 'Aucun paquet n’attend une revue des droits')"
                :message="$label('عند وصول محتوى حقيقي يحتاج إثبات حقوق سيظهر هنا تلقائيًا.', 'Real content that requires rights evidence will appear here automatically.', 'Le contenu réel nécessitant une preuve de droits apparaîtra ici automatiquement.')"
            />
        @else
            <div class="space-y-5">
                @foreach ($rows as $row)
                    @php
                        $basisReady = trim((string) ($rightsBases[$row['id']] ?? $row['rights_basis'] ?? '')) !== '';
                        $evidenceReady = trim((string) ($evidenceReferences[$row['id']] ?? $row['rights_evidence_reference'] ?? '')) !== '';
                        $noteReady = trim((string) ($notes[$row['id']] ?? $row['rights_review_note'] ?? '')) !== '';
                    @endphp
                    <x-filament::section wire:key="rights-review-{{ $row['id'] }}">
                        <div class="space-y-5">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap gap-2">
                                        <x-filament::badge color="warning">{{ $label('تحتاج قرار حقوق', 'Rights decision required', 'Décision sur les droits requise') }}</x-filament::badge>
                                        <x-filament::badge :color="$row['rights_review_status'] === 'rejected' ? 'danger' : 'warning'">
                                            {{ $row['rights_review_status'] === 'rejected' ? $label('مرفوض', 'Rejected', 'Rejeté') : $label('قيد المراجعة', 'Under review', 'En revue') }}
                                        </x-filament::badge>
                                    </div>
                                    <p class="mt-2 text-sm text-gray-600">
                                        {{ $label('راجع مراجع المصادر المعلنة ثم سجّل أساس الحقوق والدليل فقط إذا كانا موثقين.', 'Review the declared source references, then record a rights basis and evidence only when they are documented.', 'Examinez les sources déclarées puis enregistrez une base et une preuve uniquement si elles sont documentées.') }}
                                    </p>
                                </div>
                                <div class="text-xs text-gray-500">{{ $row['created_at'] }}</div>
                            </div>

                            <div>
                                <h4 class="text-sm font-semibold">{{ $label('مراجع المصادر المعلنة في الحزمة', 'Manifest source references', 'Références source du manifeste') }}</h4>
                                @if (($row['rights_source_references'] ?? []) === [])
                                    <p class="mt-1 text-sm text-gray-500">{{ $label('لم يتم إعلان مراجع مصادر.', 'No source references were declared.', 'Aucune référence source déclarée.') }}</p>
                                @else
                                    <ul class="mt-2 list-disc space-y-1 px-5 text-sm">
                                        @foreach ($row['rights_source_references'] as $sourceReference)
                                            <li class="break-all">{{ $sourceReference }}</li>
                                        @endforeach
                                    </ul>
                                @endif
                            </div>

                            <label class="block space-y-2" for="rights-basis-{{ $row['id'] }}">
                                <span class="text-sm font-medium">{{ $label('أساس الحقوق', 'Rights basis', 'Base des droits') }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select id="rights-basis-{{ $row['id'] }}" wire:model.live="rightsBases.{{ $row['id'] }}">
                                        <option value="">{{ $label('اختر الأساس الموثق', 'Choose the documented basis', 'Choisir la base documentée') }}</option>
                                        <option value="owner_created">{{ $label('محتوى أصلي أنشأه المالك', 'Original content created by the owner', 'Contenu original créé par le propriétaire') }}</option>
                                        <option value="licensed">{{ $label('محتوى مرخّص بعقد أو إذن', 'Content covered by a license or permission', 'Contenu couvert par une licence ou autorisation') }}</option>
                                        <option value="public_domain">{{ $label('محتوى ضمن الملكية العامة', 'Verified public-domain content', 'Contenu vérifié du domaine public') }}</option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                                <span class="text-xs leading-5 text-gray-500">
                                    {{ $label('اختر الوصف الذي يطابق الدليل الحقيقي. مثال: إذا أعد فريق المركز المادة من الصفر اختر «محتوى أصلي أنشأه المالك».', 'Choose the description that matches the real evidence. Example: if the owner’s team created the material from scratch, choose “Original content created by the owner”.', 'Choisissez la description correspondant à la preuve. Exemple : si l’équipe du propriétaire a créé le contenu, choisissez « Contenu original créé par le propriétaire ».') }}
                                </span>
                            </label>

                            <label class="block space-y-2" for="rights-evidence-{{ $row['id'] }}">
                                <span class="text-sm font-medium">{{ $label('مرجع دليل الحقوق', 'Rights evidence reference', 'Référence de preuve des droits') }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        id="rights-evidence-{{ $row['id'] }}"
                                        maxlength="500"
                                        wire:model.live.debounce.150ms="evidenceReferences.{{ $row['id'] }}"
                                        value="{{ $row['rights_evidence_reference'] ?? '' }}"
                                    />
                                </x-filament::input.wrapper>
                                <span class="text-xs leading-5 text-gray-500">
                                    {{ $label('اكتب مرجعًا يمكن للمراجع الرجوع إليه؛ لا تكتب ادعاءً غير موثق. مثال: عقد الترخيص LIC-2026-014 أو رابط مستند الموافقة الداخلي.', 'Enter a reference another reviewer can verify; never enter an unsupported claim. Example: license agreement LIC-2026-014 or the internal approval-document link.', 'Saisissez une référence vérifiable. Exemple : contrat de licence LIC-2026-014 ou lien vers le document d’approbation interne.') }}
                                </span>
                            </label>

                            <label class="block space-y-2" for="rights-note-{{ $row['id'] }}">
                                <span class="text-sm font-medium">{{ $label('ملاحظة المراجع', 'Reviewer note', 'Note du réviseur') }}</span>
                                <textarea
                                    id="rights-note-{{ $row['id'] }}"
                                    rows="3"
                                    maxlength="2000"
                                    wire:model.live.debounce.150ms="notes.{{ $row['id'] }}"
                                    class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                >{{ $row['rights_review_note'] ?? '' }}</textarea>
                                <span class="text-xs leading-5 text-gray-500">
                                    {{ $label('للاعتماد: لخّص نطاق الحق إذا كان يحتاج توضيحًا. وللرفض: اكتب سبب الرفض. مثال: الترخيص يغطي الاستخدام التعليمي داخل التطبيق حتى ديسمبر 2027.', 'For approval, summarize the permitted scope when useful; for rejection, record the rejection reason. Example: license permits in-app educational use through December 2027.', 'Pour approbation, résumez la portée autorisée; pour rejet, indiquez le motif. Exemple : licence valable pour l’usage éducatif dans l’application jusqu’en décembre 2027.') }}
                                </span>
                            </label>

                            <div class="flex flex-wrap gap-3">
                                <x-filament::button
                                    color="success"
                                    wire:click="approve('{{ $row['id'] }}')"
                                    wire:loading.attr="disabled"
                                    :disabled="! ($basisReady && $evidenceReady)"
                                >
                                    {{ $label('اعتماد الحقوق والمتابعة', 'Approve rights and continue', 'Approuver les droits et continuer') }}
                                </x-filament::button>
                                <x-filament::button
                                    color="danger"
                                    wire:click="reject('{{ $row['id'] }}')"
                                    wire:loading.attr="disabled"
                                    :disabled="! $noteReady"
                                >
                                    {{ $label('رفض الحقوق', 'Reject rights', 'Rejeter les droits') }}
                                </x-filament::button>
                            </div>

                            <details class="border-t border-gray-100 pt-4 text-xs text-gray-500">
                                <summary class="cursor-pointer font-medium">{{ $label('بيانات التتبع التقنية', 'Technical traceability', 'Traçabilité technique') }}</summary>
                                <dl class="mt-3 grid gap-3 md:grid-cols-2" dir="ltr">
                                    <div><dt class="font-medium">Import ID</dt><dd class="break-all font-mono">{{ $row['id'] }}</dd></div>
                                    <div><dt class="font-medium">Preparation request</dt><dd class="break-all font-mono">{{ $row['preparation_request_id'] ?? '—' }}</dd></div>
                                    <div><dt class="font-medium">Pack ID</dt><dd class="break-all font-mono">{{ $row['pack_id'] ?? '—' }}</dd></div>
                                    <div><dt class="font-medium">Schema</dt><dd>{{ $row['schema_version'] ?? '—' }}</dd></div>
                                    <div class="md:col-span-2"><dt class="font-medium">Settings hash</dt><dd class="break-all font-mono">{{ $row['settings_hash'] ?? '—' }}</dd></div>
                                </dl>
                            </details>
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>