<x-filament-panels::page>
    @php
        $locale = app()->getLocale();
        $rtl = $locale === 'ar';
        $label = static fn (string $ar, string $en, string $fr): string => $rtl ? $ar : ($locale === 'fr' ? $fr : $en);
        $preview = $this->preview();
    @endphp

    <div dir="{{ $rtl ? 'rtl' : 'ltr' }}" class="space-y-6" data-testid="modrik-system-factory-reset">
        <x-admin.operational-banner
            severity="danger"
            :title="$label('إجراء مدمر ودائم', 'Destructive and permanent action', 'Action destructive et permanente')"
            :message="$label(
                'هذا الإجراء يمسح كل بيانات التطبيق التي أُضيفت بعد التثبيت: المسارات الأكاديمية، المنهج، الدروس، الأسئلة، الاختبارات، المحاولات، التقدم، الـMastery، خطط المراجعة، طلبات إعداد المحتوى وعمليات الاستيراد، الإشعارات، السجلات التشغيلية والحسابات الأخرى. لا يمكن التراجع عنه من داخل MODRIK.',
                'This deletes all application data added after installation: academic tracks, curriculum, lessons, questions, quizzes, attempts, progress, mastery, revision plans, content preparation/import history, notifications, operational data, and all other accounts. MODRIK cannot undo it.',
                'Cette action supprime toutes les données applicatives ajoutées après l’installation et ne peut pas être annulée depuis MODRIK.'
            )"
        />

        <section class="modrik-panel">
            <div class="modrik-panel-header">
                <div>
                    <h2 class="modrik-panel-title">{{ $label('ما سيتم الاحتفاظ به', 'What is preserved', 'Éléments conservés') }}</h2>
                    <p class="modrik-panel-subtitle">
                        {{ $label(
                            'حتى يظل النظام قابلًا للدخول والتحديث بعد المسح.',
                            'These items remain so the installation stays accessible and updateable.',
                            'Ces éléments restent afin que l’installation demeure accessible et actualisable.'
                        ) }}
                    </p>
                </div>
            </div>
            <div class="modrik-panel-body">
                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                    @foreach ([
                        $label('حساب المدير الحالي', 'Current Admin account', 'Administrateur courant'),
                        $label('إعدادات النظام', 'System settings', 'Paramètres système'),
                        $label('إعدادات SMTP', 'SMTP configuration', 'Configuration SMTP'),
                        $label('سجل تحديثات النظام والمخطط', 'Update history & schema migrations', 'Historique des mises à jour et migrations'),
                    ] as $item)
                        <div class="rounded-xl border border-success-200 bg-success-50 p-4 text-sm font-semibold text-success-900">{{ $item }}</div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="modrik-panel">
            <div class="modrik-panel-header">
                <div>
                    <h2 class="modrik-panel-title">{{ $label('ملخص البيانات الحالية', 'Current data summary', 'Résumé des données actuelles') }}</h2>
                    <p class="modrik-panel-subtitle">{{ $label('هذه الأرقام ستعود إلى الصفر بعد إعادة التهيئة.', 'These application-data counts will return to zero after the reset.', 'Ces compteurs reviendront à zéro après la réinitialisation.') }}</p>
                </div>
            </div>
            <div class="modrik-panel-body">
                <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    @foreach ([
                        'other_accounts' => $label('حسابات أخرى', 'Other accounts', 'Autres comptes'),
                        'academic_tracks' => $label('مسارات أكاديمية', 'Academic tracks', 'Parcours'),
                        'curriculum_nodes' => $label('عناصر المنهج', 'Curriculum nodes', 'Éléments programme'),
                        'lessons' => $label('دروس', 'Lessons', 'Leçons'),
                        'questions' => $label('أسئلة', 'Questions', 'Questions'),
                        'quizzes' => $label('اختبارات', 'Quizzes', 'Quiz'),
                        'attempts' => $label('محاولات', 'Attempts', 'Tentatives'),
                        'preparation_requests' => $label('طلبات إعداد', 'Preparation requests', 'Demandes'),
                        'preparation_imports' => $label('عمليات استيراد', 'Preparation imports', 'Imports'),
                    ] as $key => $copy)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="text-xs font-semibold text-gray-500">{{ $copy }}</div>
                            <div class="mt-2 text-2xl font-bold text-gray-950">{{ $preview[$key] ?? 0 }}</div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="modrik-panel border-danger-300">
            <div class="modrik-panel-header">
                <div>
                    <h2 class="modrik-panel-title text-danger-700">{{ $label('تنفيذ إعادة التهيئة بالكامل', 'Run full factory reset', 'Exécuter la réinitialisation complète') }}</h2>
                    <p class="modrik-panel-subtitle">{{ $label('ثلاثة تأكيدات مطلوبة قبل تفعيل زر المسح.', 'Three confirmations are required before deletion.', 'Trois confirmations sont requises avant la suppression.') }}</p>
                </div>
            </div>
            <div class="modrik-panel-body space-y-5">
                <label class="block max-w-xl space-y-2">
                    <span class="text-sm font-semibold">{{ $label('1. اكتب بريد حساب المدير الحالي', '1. Enter the current Admin email', '1. Saisissez l’e-mail administrateur') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input type="email" wire:model="confirmationEmail" autocomplete="off" />
                    </x-filament::input.wrapper>
                    @error('confirmationEmail') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </label>

                <label class="block max-w-xl space-y-2">
                    <span class="text-sm font-semibold">{{ $label('2. اكتب العبارة التالية بالضبط: RESET MODRIK', '2. Type exactly: RESET MODRIK', '2. Tapez exactement : RESET MODRIK') }}</span>
                    <x-filament::input.wrapper>
                        <x-filament::input wire:model="confirmationPhrase" autocomplete="off" />
                    </x-filament::input.wrapper>
                    @error('confirmationPhrase') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                </label>

                @if ($this->requiresPassword())
                    <label class="block max-w-xl space-y-2">
                        <span class="text-sm font-semibold">{{ $label('3. كلمة مرور المدير الحالية', '3. Current Admin password', '3. Mot de passe administrateur actuel') }}</span>
                        <x-filament::input.wrapper>
                            <x-filament::input type="password" wire:model="currentPassword" autocomplete="current-password" />
                        </x-filament::input.wrapper>
                        @error('currentPassword') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror
                    </label>
                @endif

                <label class="flex max-w-2xl items-start gap-3 rounded-xl border border-danger-200 bg-danger-50 p-4">
                    <input type="checkbox" wire:model="acknowledgePermanentDeletion" class="mt-1 rounded border-danger-300 text-danger-600 focus:ring-danger-500" />
                    <span class="text-sm font-medium text-danger-900">
                        {{ $label(
                            'أفهم أن كل المحتوى والماتيريال والتقدم والحسابات الأخرى سيتم حذفها نهائيًا وأنني سأبدأ من نظام فارغ.',
                            'I understand that all content, learning material, progress, and other accounts will be permanently deleted and I will start with an empty application.',
                            'Je comprends que le contenu, les données d’apprentissage et les autres comptes seront définitivement supprimés.'
                        ) }}
                    </span>
                </label>
                @error('acknowledgePermanentDeletion') <p class="text-sm text-danger-600">{{ $message }}</p> @enderror

                <div>
                    <x-filament::button
                        color="danger"
                        size="lg"
                        wire:click="factoryReset"
                        wire:loading.attr="disabled"
                        wire:target="factoryReset"
                        wire:confirm="{{ $label('تأكيد نهائي: هل تريد مسح بيانات MODRIK والبدء من جديد؟', 'Final confirmation: delete MODRIK application data and start again?', 'Confirmation finale : supprimer les données MODRIK et recommencer ?') }}"
                    >
                        {{ $label('إعادة تهيئة MODRIK بالكامل', 'Factory reset MODRIK', 'Réinitialiser MODRIK') }}
                    </x-filament::button>
                </div>
            </div>
        </section>

        @if ($lastResult !== [])
            <x-admin.operational-banner
                severity="success"
                :title="$label('اكتملت إعادة التهيئة', 'Factory reset completed', 'Réinitialisation terminée')"
                :message="$label(
                    'النظام أصبح فارغًا من بيانات التطبيق القديمة ويمكنك الآن إنشاء المسارات والمحتوى من البداية.',
                    'The old application data is gone. You can now recreate academic tracks and content from scratch.',
                    'Les anciennes données applicatives ont été supprimées. Vous pouvez repartir de zéro.'
                )"
            >
                <div class="mt-3 flex flex-wrap gap-2 text-xs">
                    <x-filament::badge color="success">{{ $label('جداول تم تنظيفها', 'Tables cleared', 'Tables vidées') }}: {{ $lastResult['tables_cleared'] ?? 0 }}</x-filament::badge>
                    <x-filament::badge color="success">{{ $label('صفوف تم حذفها', 'Rows deleted', 'Lignes supprimées') }}: {{ $lastResult['rows_deleted'] ?? 0 }}</x-filament::badge>
                </div>
            </x-admin.operational-banner>
        @endif
    </div>
</x-filament-panels::page>
