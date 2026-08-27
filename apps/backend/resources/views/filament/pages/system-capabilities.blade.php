<x-filament-panels::page>
    <div dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" class="space-y-6">
        @php
            $rows = $this->capabilities();
            $isAr = app()->getLocale() === 'ar';
            $isFr = app()->getLocale() === 'fr';
            $label = static fn (string $ar, string $en, string $fr): string => $isAr ? $ar : ($isFr ? $fr : $en);
            $labels = [
                'content_preparation' => $label('إعداد المحتوى والـPrompt والـBundle ورفع ZIP', 'Content preparation, prompt/bundle and returned ZIP', 'Préparation du contenu, prompt/bundle et ZIP retourné'),
                'preparation_history' => $label('سجل طلبات إعداد المحتوى واسترجاع الإعدادات', 'Preparation request history and saved settings', 'Historique des demandes et paramètres enregistrés'),
                'content_rights' => $label('مراجعة حقوق المحتوى والأدلة', 'Content rights and evidence review', 'Revue des droits et des preuves'),
                'content_review_publish' => $label('Dry-run والمراجعة والاستيراد والنشر وإعادة المحاولة', 'Dry-run, review, canonical import, publication and retry', 'Dry-run, revue, import canonique, publication et nouvelle tentative'),
                'student_auth_account' => $label('التسجيل والدخول والتحقق والاسترداد والجلسات والحساب', 'Registration, login, verification, recovery, sessions and account', 'Inscription, connexion, vérification, récupération, sessions et compte'),
                'academic_context' => $label('المسار الأكاديمي والتفعيل والتغيير/إعادة الضبط', 'Academic catalogue, activation and reset/change', 'Catalogue académique, activation et changement/réinitialisation'),
                'student_content_catalogue' => $label('كتالوج المحتوى المنشور حسب المسار والمادة والوحدات والدروس', 'Published student content catalogue by track, subject, unit, topic and lesson', 'Catalogue étudiant publié par parcours, matière, unité, thème et leçon'),
                'study_lessons' => $label('الدراسة وقراءة الدروس', 'Study and lesson reading', 'Étude et lecture des leçons'),
                'assessment_practice' => $label('التدريب والاختبارات والترتيب والتقييم المعتمد من الخادم', 'Practice/assessment with server-authoritative order and scoring', 'Exercices/évaluations avec ordre et notation autoritaires côté serveur'),
                'progress' => $label('التقدم ومستوى الإتقان', 'Progress and mastery', 'Progression et maîtrise'),
                'offline_sync' => $label('مزامنة الإجابات وإعادة المحاولة وحل التعارض', 'Offline answer sync, retry and conflict recovery', 'Synchronisation hors ligne, nouvelles tentatives et conflits'),
                'advertising_policy' => $label('سياسة أهلية الإعلانات والمناطق المحظورة', 'Advertising eligibility and no-ad policy', 'Éligibilité publicitaire et politique sans publicité'),
                'outbox_idempotency' => $label('Outbox وIdempotency ومعاملات النشر', 'Outbox, idempotency and publication transaction controls', 'Outbox, idempotence et contrôles transactionnels de publication'),
                'runtime_inspector' => $label('Runtime Inspector والتشخيص والـCorrelation', 'Runtime Inspector, diagnostics and correlation', 'Runtime Inspector, diagnostics et corrélation'),
            ];
            $descriptions = [
                'interactive' => $label('واجهة تشغيل مباشرة داخل لوحة الإدارة.', 'Direct operator surface in Admin.', 'Surface opérateur directe dans Admin.'),
                'student_surface' => $label('واجهة طالب مباشرة؛ الرابط يفتح مساحة الطالب.', 'Direct Student surface; link opens the Student workspace.', 'Surface Étudiant directe ; le lien ouvre l’espace Étudiant.'),
                'background' => $label('خدمة خلفية مقصودة؛ تظهر حالتها وأخطاؤها داخل تجربة Offline/Retry ولا يوجد زر يدوي يغيّر سلطة الخادم.', 'Intentional background service. Status/failures surface through Offline/Retry UX; there is no manual control that overrides server authority.', 'Service de fond intentionnel. Son état apparaît dans l’UX hors ligne/réessai sans contrôle manuel contournant l’autorité serveur.'),
                'policy' => $label('سياسة Backend مقصودة وليست إعدادًا للطالب. تعمل Fail-closed ولا تُمنح للواجهة كقرار يدوي.', 'Backend policy boundary, not a student setting. It fails closed and is intentionally not a manual client decision.', 'Frontière de politique Backend, pas un réglage étudiant. Elle échoue en mode fermé et n’est pas une décision manuelle du client.'),
                'internal' => $label('بنية تشغيل داخلية لحماية التكرار والمعاملات؛ تُراقب عبر الحالات/التشخيص ولا يجب تحويلها لأزرار عادية.', 'Internal reliability authority for replay/transactions. It is observable through state/diagnostics and must not become a normal mutation button.', 'Autorité interne de fiabilité pour répétition/transactions ; observable via état/diagnostics, sans bouton de mutation normal.'),
                'gated' => $label('موجود برمجيًا لكنه مغلق بالإعدادات/الصلاحيات الحالية. لا يظهر Runtime Inspector إلا عند تفعيله صراحة للمسؤول.', 'Implemented but gated by current configuration/authorization. Runtime Inspector appears only when explicitly enabled for Admin.', 'Implémenté mais protégé par configuration/autorisation. Runtime Inspector apparaît uniquement lorsqu’il est activé explicitement pour Admin.'),
            ];
            $colors = [
                'interactive' => 'success',
                'student_surface' => 'info',
                'background' => 'gray',
                'policy' => 'warning',
                'internal' => 'gray',
                'gated' => 'warning',
            ];
        @endphp

        <div class="flex flex-wrap items-center justify-between gap-3">
            <p class="max-w-4xl text-sm text-gray-600 dark:text-gray-300">
                {{ $label(
                    'الهدف من الصفحة إن أي وظيفة متكاملة يكون مكانها معروفًا. وجود خدمة بدون زر لا يعني أنها ناقصة: بعض الخدمات لازم تظل خلفية أو سياسة Backend حتى لا تنتقل السلطة للواجهة.',
                    'This page makes every implemented capability discoverable. A capability without a button is not automatically missing: some services must remain background or Backend policy so authority is not moved into the client.',
                    'Cette page rend chaque fonction implémentée repérable. L’absence de bouton ne signifie pas une fonction manquante : certains services doivent rester en arrière-plan ou sous autorité Backend.'
                ) }}
            </p>
            <div class="flex flex-wrap items-center gap-2">
                @foreach (['ar' => 'العربية', 'en' => 'English', 'fr' => 'Français'] as $locale => $localeLabel)
                    <x-filament::button size="sm" :color="app()->getLocale() === $locale ? 'primary' : 'gray'" wire:click="setLocale('{{ $locale }}')">
                        {{ $localeLabel }}
                    </x-filament::button>
                @endforeach
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            @foreach ($rows as $row)
                <x-filament::section wire:key="system-capability-{{ $row['module'] }}">
                    <div class="space-y-3">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <h3 class="font-semibold text-gray-950 dark:text-white">{{ $labels[$row['module']] }}</h3>
                            <x-filament::badge :color="$colors[$row['mode']] ?? 'gray'">
                                {{ match ($row['mode']) {
                                    'interactive' => $label('واجهة إدارة', 'Admin UI', 'UI Admin'),
                                    'student_surface' => $label('واجهة طالب', 'Student UI', 'UI Étudiant'),
                                    'background' => $label('خلفية', 'Background', 'Arrière-plan'),
                                    'policy' => $label('سياسة', 'Policy', 'Politique'),
                                    'internal' => $label('داخلي', 'Internal', 'Interne'),
                                    'gated' => $label('مقيد', 'Gated', 'Protégé'),
                                    default => $row['mode'],
                                } }}
                            </x-filament::badge>
                        </div>
                        <p class="text-sm text-gray-600 dark:text-gray-300">{{ $descriptions[$row['mode']] ?? '' }}</p>
                        @if (is_string($row['url']) && $row['url'] !== '')
                            <div>
                                <x-filament::button tag="a" :href="$row['url']" size="sm" icon="heroicon-o-arrow-top-right-on-square">
                                    {{ $label('فتح الوظيفة', 'Open capability', 'Ouvrir la fonction') }}
                                </x-filament::button>
                            </div>
                        @endif
                    </div>
                </x-filament::section>
            @endforeach
        </div>
    </div>
</x-filament-panels::page>
