<x-filament-panels::page>
    <div
        dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}"
        class="space-y-6"
        style="--modrik-brand: {{ config('brand.colors.primary') }}; --modrik-navy: {{ config('brand.colors.navy') }};"
    >
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex flex-wrap items-center gap-2" aria-label="{{ __('admin.preparation.progress') }}">
                @foreach ([1 => 'scope', 2 => 'academic', 3 => 'generation', 4 => 'bundle'] as $number => $label)
                    <x-filament::badge :color="$step >= $number ? 'primary' : 'gray'">
                        {{ $number }}. {{ __('admin.preparation.steps.'.$label) }}
                    </x-filament::badge>
                @endforeach
            </div>
            <div class="flex items-center gap-2" aria-label="{{ __('admin.language') }}">
                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $label)
                    <x-filament::button
                        size="sm"
                        :color="app()->getLocale() === $locale ? 'primary' : 'gray'"
                        wire:click="setLocale('{{ $locale }}')"
                    >
                        {{ $label }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>

        @if ($preparationRequestId !== null)
            <div
                wire:dirty
                wire:target="locales,trackReference,boardReference,syllabusVersion,yearLevel,subjectReferences,contentTypes,includeAnswerExplanations,maximumQuestionsPerQuiz"
                class="rounded-xl border p-4 text-sm"
                style="border-color: var(--modrik-brand)"
                role="status"
            >
                <strong>{{ __('admin.preparation.settings_changed_title') }}</strong>
                <span>{{ __('admin.preparation.settings_changed_body') }}</span>
            </div>
        @endif

        @if (($requestResult['status'] ?? null) === 'superseded')
            <x-filament::section>
                <div role="alert" class="space-y-2">
                    <strong>{{ __('admin.preparation.stale_title') }}</strong>
                    <p>{{ __('admin.preparation.stale_body') }}</p>
                    @if (! empty($requestResult['superseded_by_request_id']))
                        <p class="font-mono text-xs break-all">{{ $requestResult['superseded_by_request_id'] }}</p>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($step === 1)
            <x-filament::section :heading="__('admin.preparation.steps.scope')" :description="__('admin.preparation.scope_help')">
                <div class="grid gap-6 lg:grid-cols-2">
                    <fieldset class="space-y-3">
                        <legend class="font-medium">{{ __('admin.preparation.locales') }}</legend>
                        @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $label)
                            <label class="flex min-h-11 items-center gap-3 rounded-lg border px-3 py-2">
                                <input type="checkbox" value="{{ $locale }}" wire:model="locales" class="rounded border-gray-300">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                        @error('locales') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </fieldset>

                    <fieldset class="space-y-3">
                        <legend class="font-medium">{{ __('admin.preparation.content_types') }}</legend>
                        @foreach (['lesson', 'practice_quiz', 'mock_exam'] as $type)
                            <label class="flex min-h-11 items-center gap-3 rounded-lg border px-3 py-2">
                                <input type="checkbox" value="{{ $type }}" wire:model="contentTypes" class="rounded border-gray-300">
                                <span>{{ __('admin.content_types.'.$type) }}</span>
                            </label>
                        @endforeach
                        @error('contentTypes') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </fieldset>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-filament::button wire:click="nextStep" wire:loading.attr="disabled">
                        {{ __('admin.actions.continue') }}
                    </x-filament::button>
                    @if (config('modrik.fixture.enabled'))
                        <x-filament::button color="gray" wire:click="loadSyntheticFixture" wire:loading.attr="disabled">
                            {{ __('admin.actions.load_fixture') }}
                        </x-filament::button>
                    @endif
                </div>
            </x-filament::section>
        @endif

        @if ($step === 2)
            <x-filament::section :heading="__('admin.preparation.steps.academic')" :description="__('admin.preparation.academic_help')">
                <div class="grid gap-5 lg:grid-cols-2">
                    <label class="space-y-2">
                        <span class="font-medium">{{ __('admin.fields.track_reference') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="trackReference" autocomplete="off" />
                        </x-filament::input.wrapper>
                        @error('trackReference') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </label>
                    <label class="space-y-2">
                        <span class="font-medium">{{ __('admin.fields.year_level') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="yearLevel" autocomplete="off" />
                        </x-filament::input.wrapper>
                        @error('yearLevel') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </label>
                    <label class="space-y-2">
                        <span class="font-medium">{{ __('admin.fields.board_reference') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="boardReference" autocomplete="off" />
                        </x-filament::input.wrapper>
                        <span class="text-xs text-gray-500">{{ __('admin.fields.pending_owner_input') }}</span>
                    </label>
                    <label class="space-y-2">
                        <span class="font-medium">{{ __('admin.fields.syllabus_version') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="text" wire:model="syllabusVersion" autocomplete="off" />
                        </x-filament::input.wrapper>
                        <span class="text-xs text-gray-500">{{ __('admin.fields.pending_owner_input') }}</span>
                    </label>
                </div>

                <label class="mt-5 block space-y-2">
                    <span class="font-medium">{{ __('admin.fields.subject_references') }}</span>
                    <textarea
                        wire:model="subjectReferences"
                        rows="5"
                        class="block w-full rounded-xl border-gray-300 bg-white px-3 py-2 text-sm shadow-sm dark:border-white/10 dark:bg-white/5"
                        placeholder="{{ __('admin.fields.subject_references_placeholder') }}"
                    ></textarea>
                    <span class="text-xs text-gray-500">{{ __('admin.fields.one_reference_per_line') }}</span>
                    @error('subjectReferences') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                </label>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-filament::button color="gray" wire:click="previousStep">{{ __('admin.actions.back') }}</x-filament::button>
                    <x-filament::button wire:click="nextStep" wire:loading.attr="disabled">{{ __('admin.actions.continue') }}</x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if ($step === 3)
            <x-filament::section :heading="__('admin.preparation.steps.generation')" :description="__('admin.preparation.generation_help')">
                <div class="grid gap-5 lg:grid-cols-2">
                    <label class="space-y-2">
                        <span class="font-medium">{{ __('admin.fields.maximum_questions') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="number" min="1" max="200" wire:model="maximumQuestionsPerQuiz" />
                        </x-filament::input.wrapper>
                        @error('maximumQuestionsPerQuiz') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                    </label>
                    <label class="flex min-h-11 items-center gap-3 rounded-xl border p-4">
                        <input type="checkbox" wire:model="includeAnswerExplanations" class="rounded border-gray-300">
                        <span>{{ __('admin.fields.include_explanations') }}</span>
                    </label>
                </div>

                <div class="mt-5 rounded-xl border p-4 text-sm">
                    <strong>{{ __('admin.preparation.ai_boundary_title') }}</strong>
                    <p>{{ __('admin.preparation.ai_boundary_body') }}</p>
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <x-filament::button color="gray" wire:click="previousStep">{{ __('admin.actions.back') }}</x-filament::button>
                    <x-filament::button wire:click="generate" wire:loading.attr="disabled" wire:target="generate">
                        {{ $preparationRequestId === null ? __('admin.actions.generate') : __('admin.actions.regenerate') }}
                    </x-filament::button>
                </div>
            </x-filament::section>
        @endif

        @if ($step === 4)
            <div class="grid gap-6 xl:grid-cols-2">
                <x-filament::section :heading="__('admin.preparation.request_summary')">
                    @if ($requestResult === [])
                        <div class="py-8 text-center text-sm text-gray-500">{{ __('admin.preparation.no_request') }}</div>
                    @else
                        <dl class="space-y-3 text-sm">
                            <div><dt class="font-medium">{{ __('admin.fields.request_id') }}</dt><dd class="break-all font-mono">{{ $requestResult['preparation_request_id'] ?? '' }}</dd></div>
                            <div><dt class="font-medium">{{ __('admin.fields.schema_version') }}</dt><dd>{{ $requestResult['schema_version'] ?? '' }}</dd></div>
                            <div><dt class="font-medium">{{ __('admin.fields.settings_hash') }}</dt><dd class="break-all font-mono text-xs">{{ $requestResult['settings_hash'] ?? '' }}</dd></div>
                            <div><dt class="font-medium">{{ __('admin.fields.status') }}</dt><dd><x-filament::badge color="primary">{{ $requestResult['status'] ?? 'ready' }}</x-filament::badge></dd></div>
                        </dl>
                        <div class="mt-5 flex flex-wrap gap-3">
                            <x-filament::button color="gray" wire:click="downloadPrompt">{{ __('admin.actions.download_prompt') }}</x-filament::button>
                            <x-filament::button color="gray" wire:click="downloadBundle">{{ __('admin.actions.download_bundle') }}</x-filament::button>
                            <x-filament::button color="gray" wire:click="previousStep">{{ __('admin.actions.edit_settings') }}</x-filament::button>
                        </div>
                    @endif
                </x-filament::section>

                <x-filament::section :heading="__('admin.preparation.returned_zip')" :description="__('admin.preparation.returned_zip_help')">
                    @if ($preparationRequestId !== null)
                        <div class="space-y-4">
                            <input
                                type="file"
                                wire:model="returnedZip"
                                accept=".zip,application/zip"
                                class="block w-full rounded-xl border p-3 text-sm"
                                aria-describedby="zip-help"
                            >
                            <p id="zip-help" class="text-xs text-gray-500">{{ __('admin.preparation.zip_binding_help') }}</p>
                            @error('returnedZip') <p class="text-sm text-danger-600" role="alert">{{ $message }}</p> @enderror
                            <x-filament::button wire:click="uploadReturnedZip" wire:loading.attr="disabled" wire:target="returnedZip,uploadReturnedZip">
                                {{ __('admin.actions.validate_zip') }}
                            </x-filament::button>
                        </div>
                    @else
                        <p class="text-sm text-gray-500">{{ __('admin.messages.generate_first') }}</p>
                    @endif
                </x-filament::section>
            </div>

            @if ($requestResult !== [])
                <x-filament::section :heading="__('admin.preparation.prompt')">
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-xl border bg-gray-50 p-4 text-xs dark:bg-white/5" dir="ltr">{{ $requestResult['prompt'] ?? '' }}</pre>
                </x-filament::section>
                <x-filament::section :heading="__('admin.preparation.bundle')">
                    <pre class="max-h-96 overflow-auto whitespace-pre-wrap rounded-xl border bg-gray-50 p-4 text-xs dark:bg-white/5" dir="ltr">{{ json_encode($requestResult['bundle'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                </x-filament::section>
            @endif

            @if ($validationResult !== [])
                <x-filament::section :heading="__('admin.preparation.validation_result')">
                    @if (($validationResult['accepted'] ?? false) === true)
                        <div class="space-y-4" role="status" aria-live="polite">
                            <x-filament::badge color="success">{{ __('admin.messages.zip_validated') }}</x-filament::badge>
                            <dl class="grid gap-3 text-sm md:grid-cols-3">
                                <div><dt class="font-medium">{{ __('admin.fields.import_id') }}</dt><dd class="break-all font-mono text-xs">{{ $validationResult['data']['preparation_import_id'] ?? '' }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.files') }}</dt><dd>{{ $validationResult['data']['validated_file_count'] ?? 0 }}</dd></div>
                                <div><dt class="font-medium">{{ __('admin.fields.records') }}</dt><dd>{{ $validationResult['data']['validated_record_count'] ?? 0 }}</dd></div>
                            </dl>
                            <x-filament::button tag="a" :href="\App\Filament\Pages\ContentReviewQueue::getUrl()">
                                {{ __('admin.actions.open_review_queue') }}
                            </x-filament::button>
                        </div>
                    @else
                        <div role="alert" class="space-y-3">
                            <x-filament::badge color="danger">{{ __('admin.messages.zip_rejected') }}</x-filament::badge>
                            @foreach (($validationResult['errors'] ?? []) as $error)
                                <div class="rounded-lg border p-3 text-sm">
                                    <strong>{{ $error['code'] ?? 'VALIDATION_ERROR' }}</strong>
                                    <p>{{ $error['message'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </x-filament::section>
            @endif
        @endif

        <div wire:loading.flex class="items-center gap-2 text-sm" aria-live="polite">
            <x-filament::loading-indicator class="h-5 w-5" />
            <span>{{ __('admin.messages.working') }}</span>
        </div>
    </div>
</x-filament-panels::page>
