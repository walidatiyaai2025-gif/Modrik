<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')
    @php($imports = $this->imports())
    @php($detail = $this->selectedDetail())
    @php($label = static fn (string $ar, string $en, string $fr): string => $rtl ? $ar : ($locale === 'fr' ? $fr : $en))

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-question-bank-workbench">
        <x-admin.operational-banner
            severity="info"
            :title="$label('مسار Question Bank اليدوي', 'Manual Question Bank workflow', 'Flux Question Bank manuel')"
            :message="$label(
                'ارفع JSON من MODRIK_QUESTION_BANK_MASTER_V1. الـBackend يتحقق ويراجع ويربط وينشر؛ لا يوجد استدعاء AI مدفوع وقت التشغيل.',
                'Upload JSON from MODRIK_QUESTION_BANK_MASTER_V1. The Backend validates, reviews, maps and publishes; no paid-AI call runs in production.',
                'Importez le JSON MODRIK_QUESTION_BANK_MASTER_V1. Le Backend valide, révise, mappe et publie sans appel IA payant au runtime.'
            )"
        />

        <section class="modrik-panel">
            <div class="modrik-panel-header">
                <div>
                    <h2 class="modrik-panel-title">{{ $label('استيراد حزمة JSON', 'Import JSON pack', 'Importer le pack JSON') }}</h2>
                    <p class="modrik-panel-subtitle">modrik-question-bank-v1 · max 20 MB</p>
                </div>
                <x-filament::button tag="a" color="gray" :href="AppFilamentPagesPromptLibrary::getUrl()">
                    {{ $label('فتح مكتبة الـPrompt', 'Open Prompt Library', 'Ouvrir la bibliothèque') }}
                </x-filament::button>
            </div>
            <div class="modrik-panel-body space-y-3">
                <input type="file" wire:model="questionBankJson" accept=".json,application/json" class="block w-full text-sm" />
                @error('questionBankJson')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                <x-filament::button wire:click="upload" wire:loading.attr="disabled">{{ $label('تحقق واستورد', 'Validate & import', 'Valider et importer') }}</x-filament::button>
            </div>
        </section>

        <section class="modrik-panel">
            <div class="modrik-panel-header">
                <div>
                    <h2 class="modrik-panel-title">{{ $label('قائمة الحزم', 'Import queue', 'File des imports') }}</h2>
                    <p class="modrik-panel-subtitle">{{ $label('الحالة والربط والحقوق قبل المحتوى التقني.', 'Workflow state, mapping and rights before technical payload.', 'État, mapping et droits avant le payload technique.') }}</p>
                </div>
                <x-filament::badge color="gray">{{ count($imports) }}</x-filament::badge>
            </div>
            <div class="modrik-panel-body space-y-4">
                <div class="flex flex-wrap gap-2">
                    <select wire:model.live="statusFilter" class="rounded-lg border-gray-300 text-sm">
                        @foreach (['all', 'needs_review', 'approved', 'rejected', 'published', 'suspended', 'archived'] as $state)
                            <option value="{{ $state }}">{{ str_replace('_', ' ', $state) }}</option>
                        @endforeach
                    </select>
                    @foreach (['approve', 'reject', 'publish', 'unpublish', 'suspend', 'archive'] as $action)
                        <x-filament::button size="sm" color="gray" wire:click="requestBulk('{{ $action }}')" :disabled="$selectedImportIds === []">
                            {{ ucfirst($action) }}
                        </x-filament::button>
                    @endforeach
                </div>

                @if ($imports === [])
                    <x-admin.empty-state
                        :title="$label('لا توجد حزم Question Bank', 'No Question Bank imports', 'Aucun import Question Bank')"
                        :message="$label('ابدأ بحزمة JSON مرتبطة بطلب إعداد محفوظ.', 'Start with a JSON pack bound to a saved preparation request.', 'Commencez avec un pack JSON lié à une demande de préparation.')"
                    />
                @else
                    <div class="space-y-3">
                        @foreach ($imports as $row)
                            @php($validation = json_decode((string) $row['validation_summary'], true) ?: [])
                            <article class="rounded-xl border border-gray-200 bg-white p-4" wire:key="qb-import-{{ $row['id'] }}">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-start gap-3">
                                        <input type="checkbox" wire:model.live="selectedImportIds" value="{{ $row['id'] }}" class="mt-1 rounded border-gray-300" />
                                        <div class="min-w-0">
                                            <div class="flex flex-wrap gap-2">
                                                <x-filament::badge :color="$row['status'] === 'published' ? 'success' : ($row['status'] === 'rejected' ? 'danger' : 'warning')">{{ $row['status'] }}</x-filament::badge>
                                                <x-filament::badge color="gray">{{ $row['schema_version'] }}</x-filament::badge>
                                                <x-filament::badge color="gray">{{ $validation['item_count'] ?? 0 }} questions</x-filament::badge>
                                                @if (($validation['mapping_required'] ?? 0) > 0)
                                                    <x-filament::badge color="warning">{{ $validation['mapping_required'] }} mapping required</x-filament::badge>
                                                @endif
                                            </div>
                                            <p class="mt-2 break-all text-xs text-gray-500">Pack {{ $row['pack_id'] }} · Request {{ $row['preparation_request_id'] }}</p>
                                        </div>
                                    </div>
                                    <x-filament::button size="sm" wire:click="selectImport('{{ $row['id'] }}')">{{ $selectedImportId === $row['id'] ? $label('إغلاق', 'Close', 'Fermer') : $label('مراجعة', 'Review', 'Réviser') }}</x-filament::button>
                                </div>
                                @error('imports.'.$row['id'])<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
                            </article>
                        @endforeach
                    </div>
                @endif
            </div>
        </section>

        @if ($detail)
            @php($import = $detail['import'])
            @php($nodeOptions = $this->curriculumOptions((string) $import['id']))
            <section class="modrik-panel">
                <div class="modrik-panel-header">
                    <div>
                        <h2 class="modrik-panel-title">{{ $label('مراجعة الحزمة', 'Import review', 'Révision de l’import') }}</h2>
                        <p class="modrik-panel-subtitle">{{ $import['status'] }} · {{ $import['prompt_id'] }} {{ $import['prompt_version'] }}</p>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <x-filament::button size="sm" color="gray" wire:click="exportJson('{{ $import['id'] }}')">JSON</x-filament::button>
                        <x-filament::button size="sm" color="gray" wire:click="exportCsv('{{ $import['id'] }}')">CSV</x-filament::button>
                    </div>
                </div>

                <div class="modrik-panel-body space-y-6">
                    <div class="grid gap-3 lg:grid-cols-2">
                        @foreach ($detail['sources'] as $source)
                            <article class="rounded-xl border border-gray-200 p-4" wire:key="qb-source-{{ $source['id'] }}">
                                <div class="font-semibold text-gray-950">{{ $source['name'] }}</div>
                                <div class="mt-1 text-xs text-gray-500">{{ $source['source_key'] }}</div>
                                <div class="mt-3 grid gap-2 sm:grid-cols-2">
                                    <select wire:model="sourceKinds.{{ $source['id'] }}" class="rounded-lg border-gray-300 text-sm">
                                        @foreach (['book', 'pdf', 'worksheet', 'exam', 'other'] as $kind)<option value="{{ $kind }}">{{ $kind }}</option>@endforeach
                                    </select>
                                    <select wire:model="sourceRights.{{ $source['id'] }}" class="rounded-lg border-gray-300 text-sm">
                                        @foreach (['pending', 'approved', 'rejected'] as $rights)<option value="{{ $rights }}">{{ $rights }}</option>@endforeach
                                    </select>
                                    <input wire:model="sourceReferences.{{ $source['id'] }}" class="rounded-lg border-gray-300 text-sm sm:col-span-2" placeholder="{{ $label('مرجع الملف/الكتاب', 'Book/PDF/worksheet/exam reference', 'Référence source') }}" />
                                    <textarea wire:model="sourceNotes.{{ $source['id'] }}" class="rounded-lg border-gray-300 text-sm sm:col-span-2" rows="2" placeholder="{{ $label('ملاحظات الحقوق', 'Rights note', 'Note de droits') }}"></textarea>
                                </div>
                                <div class="mt-3 flex justify-end"><x-filament::button size="sm" wire:click="saveSource('{{ $source['id'] }}')">{{ $label('حفظ المصدر', 'Save source', 'Enregistrer') }}</x-filament::button></div>
                                @error('sources.'.$source['id'])<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
                            </article>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto rounded-xl border border-gray-200">
                        <table class="min-w-[980px] w-full text-sm">
                            <thead class="bg-gray-50 text-xs uppercase text-gray-500">
                                <tr>
                                    <th class="p-3 text-start">ID</th><th class="p-3 text-start">Type</th><th class="p-3 text-start">Language</th><th class="p-3 text-start">Source page</th><th class="p-3 text-start">Mapping</th><th class="p-3 text-start">Difficulty</th><th class="p-3 text-start">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @foreach ($detail['items'] as $item)
                                    <tr wire:key="qb-item-{{ $item['id'] }}">
                                        <td class="p-3"><div class="font-medium">{{ $item['external_id'] }}</div><div class="text-xs text-gray-400">{{ $item['status'] }}</div></td>
                                        <td class="p-3">{{ $item['type'] }}</td>
                                        <td class="p-3">{{ $item['language'] }}</td>
                                        <td class="p-3">{{ $item['source_page'] ?? '—' }}</td>
                                        <td class="p-3">
                                            <select wire:model="itemNodeIds.{{ $item['id'] }}" class="w-64 rounded-lg border-gray-300 text-xs">
                                                <option value="">{{ $label('اختر عقدة', 'Select node', 'Choisir un nœud') }}</option>
                                                @foreach ($nodeOptions as $nodeId => $nodeLabel)<option value="{{ $nodeId }}">{{ $nodeLabel }}</option>@endforeach
                                            </select>
                                            <div class="mt-1 text-xs {{ $item['scope_state'] === 'mapped' ? 'text-success-600' : 'text-warning-600' }}">{{ $item['scope_state'] }}</div>
                                        </td>
                                        <td class="p-3">
                                            <select wire:model="itemDifficulties.{{ $item['id'] }}" class="rounded-lg border-gray-300 text-xs">
                                                @foreach (['Easy', 'Medium', 'Hard', 'Revision', 'Exam-style'] as $difficulty)<option value="{{ $difficulty }}">{{ $difficulty }}</option>@endforeach
                                            </select>
                                        </td>
                                        <td class="p-3">
                                            <input wire:model="itemReasons.{{ $item['id'] }}" class="mb-2 w-48 rounded-lg border-gray-300 text-xs" placeholder="{{ $label('سبب التعديل', 'Change reason', 'Motif') }}" />
                                            <x-filament::button size="xs" color="gray" wire:click="reclassify('{{ $item['id'] }}')">{{ $label('حفظ', 'Save', 'Enregistrer') }}</x-filament::button>
                                            @error('items.'.$item['id'])<p class="mt-1 text-xs text-danger-600">{{ $message }}</p>@enderror
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="rounded-xl border border-gray-200 p-4">
                        <textarea wire:model="reasons.{{ $import['id'] }}" rows="2" class="w-full rounded-lg border-gray-300 text-sm" placeholder="{{ $label('سبب القرار عند الحاجة', 'Decision reason when required', 'Motif de décision si nécessaire') }}"></textarea>
                        <div class="mt-3 flex flex-wrap gap-2">
                            @if ($import['status'] === 'needs_review')
                                <x-filament::button wire:click="approve('{{ $import['id'] }}')" wire:confirm="{{ $label('تأكيد الاعتماد؟', 'Approve this import?', 'Approuver cet import ?') }}">Approve</x-filament::button>
                                <x-filament::button color="danger" wire:click="reject('{{ $import['id'] }}')" wire:confirm="{{ $label('تأكيد الرفض؟', 'Reject this import?', 'Rejeter cet import ?') }}">Reject</x-filament::button>
                            @elseif ($import['status'] === 'approved' || $import['status'] === 'suspended')
                                <x-filament::button wire:click="publish('{{ $import['id'] }}')" wire:confirm="{{ $label('نشر الأسئلة رسميًا؟', 'Publish canonical questions?', 'Publier les questions ?') }}">Publish</x-filament::button>
                            @elseif ($import['status'] === 'published')
                                <x-filament::button color="warning" wire:click="suspend('{{ $import['id'] }}')" wire:confirm="{{ $label('تعليق التسليم؟', 'Suspend delivery?', 'Suspendre la diffusion ?') }}">Suspend</x-filament::button>
                                <x-filament::button color="gray" wire:click="unpublish('{{ $import['id'] }}')" wire:confirm="{{ $label('إلغاء النشر وإرجاعه للمراجعة؟', 'Unpublish to approved state?', 'Dépublier ?') }}">Unpublish</x-filament::button>
                            @endif
                            @if ($import['status'] !== 'archived')
                                <x-filament::button color="danger" wire:click="archive('{{ $import['id'] }}')" wire:confirm="{{ $label('أرشفة مع حفظ التاريخ؟', 'Archive while preserving history?', 'Archiver en conservant l’historique ?') }}">Archive</x-filament::button>
                            @endif
                        </div>
                        @error('imports.'.$import['id'])<p class="mt-2 text-sm text-danger-600">{{ $message }}</p>@enderror
                    </div>
                </div>
            </section>
        @endif

        <x-filament::modal id="confirm-question-bank-bulk" width="lg">
            <x-slot name="heading">{{ $label('تأكيد الإجراء المجمع', 'Confirm bulk action', 'Confirmer l’action groupée') }}</x-slot>
            <div class="space-y-3">
                <p class="text-sm text-gray-600">{{ $pendingBulkAction }} · {{ count($selectedImportIds) }} imports</p>
                <textarea wire:model="bulkReason" rows="3" class="w-full rounded-lg border-gray-300 text-sm" placeholder="{{ $label('سبب إلزامي للتدقيق', 'Required audit reason', 'Motif obligatoire') }}"></textarea>
                @error('bulkReason')<p class="text-sm text-danger-600">{{ $message }}</p>@enderror
                <div class="flex justify-end gap-2">
                    <x-filament::button color="gray" wire:click="cancelBulk">{{ $label('إلغاء', 'Cancel', 'Annuler') }}</x-filament::button>
                    <x-filament::button wire:click="confirmBulk">{{ $label('تأكيد', 'Confirm', 'Confirmer') }}</x-filament::button>
                </div>
            </div>
        </x-filament::modal>
    </div>
</x-filament-panels::page>
