<x-filament-panels::page>
    <div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="space-y-6">
        @php
            $rows = $this->rows();
            $isAr = app()->getLocale() === 'ar';
            $isFr = app()->getLocale() === 'fr';
            $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
        @endphp

        <div class="flex flex-wrap items-center justify-end gap-2">
            @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $localeLabel)
                <x-filament::button size="sm" :color="app()->getLocale() === $locale ? 'primary' : 'gray'" wire:click="setLocale('{{ $locale }}')">
                    {{ $localeLabel }}
                </x-filament::button>
            @endforeach
        </div>

        <x-filament::section>
            <div class="text-sm text-gray-600 dark:text-gray-300">
                {{ $label(
                    'هذه الخطوة لا تمنح حقوقًا تلقائيًا. اختر أساس الحقوق فقط إذا كان مدعومًا بدليل حقيقي يقدمه المالك. إذا لم يتوفر دليل، ارفض أو اترك المحتوى قيد المراجعة.',
                    'This step never invents rights. Select a rights basis only when it is backed by real owner-controlled evidence. If evidence is unavailable, reject or leave the import pending.',
                    'Cette étape n’invente jamais de droits. Sélectionnez une base juridique uniquement si elle est étayée par une preuve réelle contrôlée par le propriétaire.'
                ) }}
            </div>
        </x-filament::section>

        @if ($rows === [])
            <x-filament::section>
                <div class="py-10 text-center text-sm text-gray-500">
                    {{ $label('لا توجد حزم محتوى تنتظر مراجعة الحقوق.', 'No content packs are awaiting rights review.', 'Aucun paquet de contenu n’attend une revue des droits.') }}
                </div>
            </x-filament::section>
        @else
            <div class="space-y-5">
                @foreach ($rows as $row)
                    @php
                        $basisReady = trim((string) ($rightsBases[$row['id']] ?? $row['rights_basis'] ?? '')) !== '';
                        $evidenceReady = trim((string) ($evidenceReferences[$row['id']] ?? $row['rights_evidence_reference'] ?? '')) !== '';
                        $noteReady = trim((string) ($notes[$row['id']] ?? $row['rights_review_note'] ?? '')) !== '';
                    @endphp
                    <x-filament::section wire:key="rights-review-{{ $row['id'] }}">
                        <div class="space-y-4">
                            <div class="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <div class="flex flex-wrap gap-2">
                                        <x-filament::badge color="warning">{{ $label('مراجعة الحقوق', 'Rights review', 'Revue des droits') }}</x-filament::badge>
                                        <x-filament::badge color="gray">{{ $row['rights_status'] ?? '—' }}</x-filament::badge>
                                        <x-filament::badge :color="$row['rights_review_status'] === 'rejected' ? 'danger' : 'warning'">{{ $row['rights_review_status'] }}</x-filament::badge>
                                    </div>
                                    <p class="mt-2 break-all font-mono text-xs">{{ $row['id'] }}</p>
                                </div>
                                <div class="text-xs text-gray-500">{{ $row['created_at'] }}</div>
                            </div>

                            <dl class="grid gap-3 text-sm md:grid-cols-2 xl:grid-cols-4">
                                <div><dt class="font-medium">{{ $label('طلب الإعداد', 'Preparation request', 'Demande de préparation') }}</dt><dd class="break-all font-mono text-xs">{{ $row['preparation_request_id'] ?? '—' }}</dd></div>
                                <div><dt class="font-medium">{{ $label('معرّف الحزمة', 'Pack ID', 'ID du paquet') }}</dt><dd class="break-all font-mono text-xs">{{ $row['pack_id'] ?? '—' }}</dd></div>
                                <div><dt class="font-medium">Schema</dt><dd>{{ $row['schema_version'] ?? '—' }}</dd></div>
                                <div><dt class="font-medium">settings_hash</dt><dd class="break-all font-mono text-xs">{{ $row['settings_hash'] ?? '—' }}</dd></div>
                            </dl>

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
                                <span class="text-sm font-medium">{{ $label('أساس الحقوق المعتمد', 'Approved rights basis', 'Base de droits approuvée') }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input.select id="rights-basis-{{ $row['id'] }}" wire:model.live="rightsBases.{{ $row['id'] }}">
                                        <option value="">{{ $label('اختر فقط إذا كان لديك دليل', 'Choose only when evidence supports it', 'Choisir uniquement avec preuve') }}</option>
                                        <option value="owner_created">owner_created</option>
                                        <option value="licensed">licensed</option>
                                        <option value="public_domain">public_domain</option>
                                    </x-filament::input.select>
                                </x-filament::input.wrapper>
                            </label>

                            <label class="block space-y-2" for="rights-evidence-{{ $row['id'] }}">
                                <span class="text-sm font-medium">{{ $label('مرجع دليل الحقوق', 'Rights evidence reference', 'Référence de preuve des droits') }}</span>
                                <x-filament::input.wrapper>
                                    <x-filament::input
                                        id="rights-evidence-{{ $row['id'] }}"
                                        maxlength="500"
                                        wire:model.live.debounce.150ms="evidenceReferences.{{ $row['id'] }}"
                                        value="{{ $row['rights_evidence_reference'] ?? '' }}"
                                        placeholder="{{ $label('رقم اتفاقية/رابط سياسة/مرجع موافقة موثقة', 'Agreement ID, policy URL, or documented approval reference', 'ID d’accord, URL de politique ou référence d’approbation') }}"
                                    />
                                </x-filament::input.wrapper>
                                <span class="text-xs text-gray-500">{{ $label('مطلوب للاعتماد. لا تكتب ادعاءً غير موثق.', 'Required for approval. Do not enter an unsupported rights claim.', 'Requis pour approbation. N’entrez aucune revendication non prouvée.') }}</span>
                            </label>

                            <label class="block space-y-2" for="rights-note-{{ $row['id'] }}">
                                <span class="text-sm font-medium">{{ $label('ملاحظة المراجع', 'Reviewer note', 'Note du réviseur') }}</span>
                                <textarea
                                    id="rights-note-{{ $row['id'] }}"
                                    rows="3"
                                    maxlength="2000"
                                    wire:model.live.debounce.150ms="notes.{{ $row['id'] }}"
                                    class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                                    placeholder="{{ $label('اشرح نطاق الاستخدام أو سبب الرفض.', 'Describe the permitted scope or rejection reason.', 'Décrivez la portée autorisée ou le motif du rejet.') }}"
                                >{{ $row['rights_review_note'] ?? '' }}</textarea>
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
                        </div>
                    </x-filament::section>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-panels::page>