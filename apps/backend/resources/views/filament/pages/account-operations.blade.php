<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')

    <div class="min-w-0 max-w-full space-y-6 overflow-x-hidden" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-account-operations">
        <x-admin.operational-banner
            severity="warning"
            :title="$locale === 'ar' ? 'حدود أمن الحساب' : ($locale === 'fr' ? 'Limites de sécurité des comptes' : 'Account security boundary')"
            :message="$locale === 'ar'
                ? 'هذه المساحة تعرض بيانات تشغيلية آمنة فقط. كلمات المرور ورموز الجلسات وموضوعات OAuth والأسرار لا يتم قراءتها أو عرضها. الأدوار الحالية ثابتة وتظل الصلاحيات تحت سلطة الخادم.'
                : ($locale === 'fr'
                    ? 'Cet espace n’affiche que des métadonnées opérationnelles sûres. Les mots de passe, jetons de session, sujets OAuth et secrets ne sont ni lus ni affichés. Les rôles restent fixes et l’autorisation reste côté serveur.'
                    : 'This workspace exposes safe operational metadata only. Passwords, session tokens, OAuth subjects and secrets are never read or rendered. Roles remain fixed and authorization stays server-side.')"
        />

        <section class="min-w-0 max-w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="account-filters-heading">
            <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">{{ $locale === 'ar' ? 'الاكتشاف' : ($locale === 'fr' ? 'Découverte' : 'Discovery') }}</p>
                    <h2 id="account-filters-heading" class="mt-1 text-lg font-semibold text-gray-950">{{ $locale === 'ar' ? 'البحث عن الحسابات' : ($locale === 'fr' ? 'Rechercher des comptes' : 'Find accounts') }}</h2>
                </div>
                <x-filament::badge color="gray">{{ count($accounts) }} {{ $locale === 'ar' ? 'نتيجة' : ($locale === 'fr' ? 'résultat(s)' : 'result(s)') }}</x-filament::badge>
            </div>

            <div class="mt-5 grid min-w-0 gap-4 md:grid-cols-2 xl:grid-cols-5">
                <label class="block min-w-0">
                    <span class="text-sm font-medium text-gray-700">{{ $locale === 'ar' ? 'الاسم أو البريد' : ($locale === 'fr' ? 'Nom ou e-mail' : 'Name or email') }}</span>
                    <input type="search" wire:model.live.debounce.300ms="search" class="mt-2 block w-full max-w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500" placeholder="{{ $locale === 'ar' ? 'مثال: Ahmed' : ($locale === 'fr' ? 'Exemple : Ahmed' : 'Example: Ahmed') }}" />
                </label>
                <label class="block min-w-0">
                    <span class="text-sm font-medium text-gray-700">{{ $locale === 'ar' ? 'الدور' : ($locale === 'fr' ? 'Rôle' : 'Role') }}</span>
                    <select wire:model.live="roleFilter" class="mt-2 block w-full max-w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="all">{{ $locale === 'ar' ? 'كل الأدوار' : ($locale === 'fr' ? 'Tous les rôles' : 'All roles') }}</option>
                        <option value="admin">Admin</option><option value="content_team">Content Team</option><option value="student">Student</option>
                    </select>
                </label>
                <label class="block min-w-0">
                    <span class="text-sm font-medium text-gray-700">{{ $locale === 'ar' ? 'حالة الحساب' : ($locale === 'fr' ? 'État du compte' : 'Account status') }}</span>
                    <select wire:model.live="statusFilter" class="mt-2 block w-full max-w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="all">{{ $locale === 'ar' ? 'كل الحالات' : ($locale === 'fr' ? 'Tous les états' : 'All statuses') }}</option>
                        <option value="active">{{ $locale === 'ar' ? 'نشط' : ($locale === 'fr' ? 'Actif' : 'Active') }}</option>
                        <option value="deleted">{{ $locale === 'ar' ? 'محذوف منطقيًا' : ($locale === 'fr' ? 'Supprimé logiquement' : 'Tombstoned') }}</option>
                    </select>
                </label>
                <label class="block min-w-0">
                    <span class="text-sm font-medium text-gray-700">{{ $locale === 'ar' ? 'طريقة تسجيل الدخول' : ($locale === 'fr' ? 'Fournisseur de connexion' : 'Sign-in provider') }}</span>
                    <select wire:model.live="providerFilter" class="mt-2 block w-full max-w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="all">{{ $locale === 'ar' ? 'كل الطرق' : ($locale === 'fr' ? 'Tous les fournisseurs' : 'All providers') }}</option>
                        <option value="password">Email / Password</option><option value="google">Google</option><option value="apple">Apple</option>
                    </select>
                </label>
                <label class="block min-w-0">
                    <span class="text-sm font-medium text-gray-700">{{ $locale === 'ar' ? 'حالة الجلسة' : ($locale === 'fr' ? 'État de session' : 'Session state') }}</span>
                    <select wire:model.live="sessionFilter" class="mt-2 block w-full max-w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500">
                        <option value="all">{{ $locale === 'ar' ? 'كل حالات الجلسات' : ($locale === 'fr' ? 'Tous les états' : 'All session states') }}</option>
                        <option value="active">{{ $locale === 'ar' ? 'نشطة' : ($locale === 'fr' ? 'Active' : 'Active') }}</option>
                        <option value="revoked">{{ $locale === 'ar' ? 'ملغاة' : ($locale === 'fr' ? 'Révoquée' : 'Revoked') }}</option>
                        <option value="expired">{{ $locale === 'ar' ? 'منتهية' : ($locale === 'fr' ? 'Expirée' : 'Expired') }}</option>
                        <option value="none">{{ $locale === 'ar' ? 'بدون جلسات' : ($locale === 'fr' ? 'Sans session' : 'No sessions') }}</option>
                    </select>
                </label>
            </div>
        </section>

        <div wire:loading.flex class="min-w-0 max-w-full items-center gap-2 rounded-xl border border-primary-100 bg-primary-50 p-3 text-sm text-primary-800" role="status">
            <x-filament::loading-indicator class="h-5 w-5 shrink-0" />
            <span class="min-w-0 break-words">{{ $locale === 'ar' ? 'جارٍ تحديث بيانات التشغيل…' : ($locale === 'fr' ? 'Actualisation des données opérationnelles…' : 'Refreshing operational data…') }}</span>
        </div>

        @if ($accounts === [])
            <x-admin.empty-state
                :title="$locale === 'ar' ? 'لا توجد حسابات مطابقة' : ($locale === 'fr' ? 'Aucun compte correspondant' : 'No matching accounts')"
                :message="$locale === 'ar' ? 'غيّر البحث أو المرشحات. لا يتم إنشاء بيانات افتراضية لملء هذه الشاشة.' : ($locale === 'fr' ? 'Modifiez la recherche ou les filtres. Aucune donnée fictive n’est créée pour remplir cet écran.' : 'Change the search or filters. No synthetic account data is created to populate this screen.')"
            />
        @else
            <section class="min-w-0 max-w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="accounts-heading">
                <div class="flex min-w-0 flex-wrap items-center justify-between gap-3">
                    <h2 id="accounts-heading" class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'الحسابات' : ($locale === 'fr' ? 'Comptes' : 'Accounts') }}</h2>
                    <span class="text-xs text-gray-500">{{ $locale === 'ar' ? 'بيانات تشغيلية آمنة' : ($locale === 'fr' ? 'Métadonnées opérationnelles sûres' : 'Safe operational metadata') }}</span>
                </div>
                <div class="mt-4 grid min-w-0 gap-3 md:grid-cols-2 2xl:grid-cols-3">
                    @foreach ($accounts as $account)
                        <article wire:key="account-{{ $account['id'] }}" class="min-w-0 rounded-xl border border-gray-100 bg-gray-50 p-4">
                            <div class="flex min-w-0 flex-wrap items-start justify-between gap-3">
                                <div class="min-w-0 flex-1">
                                    <h3 class="break-words font-semibold text-gray-950">{{ $account['name'] }}</h3>
                                    <p class="mt-1 break-all text-sm text-gray-600" dir="ltr">{{ $account['email'] }}</p>
                                    <p class="mt-1 text-xs text-gray-500">{{ strtoupper($account['locale']) }}</p>
                                </div>
                                <div class="flex min-w-0 flex-wrap justify-end gap-2">
                                    <x-filament::badge color="info">{{ str_replace('_', ' ', $account['role']) }}</x-filament::badge>
                                    <x-filament::badge :color="$account['account_status'] === 'active' ? 'success' : 'danger'">{{ $account['account_status'] }}</x-filament::badge>
                                </div>
                            </div>

                            <div class="mt-4 flex min-w-0 flex-wrap gap-2">
                                <x-filament::badge :color="$account['verified'] ? 'success' : 'warning'">{{ $account['verified'] ? ($locale === 'ar' ? 'بريد موثّق' : ($locale === 'fr' ? 'E-mail vérifié' : 'Verified email')) : ($locale === 'ar' ? 'غير موثّق' : ($locale === 'fr' ? 'Non vérifié' : 'Unverified')) }}</x-filament::badge>
                                <x-filament::badge :color="$account['password_enabled'] ? 'success' : 'gray'">{{ $account['password_enabled'] ? ($locale === 'ar' ? 'كلمة مرور مفعلة' : ($locale === 'fr' ? 'Mot de passe actif' : 'Password enabled')) : ($locale === 'ar' ? 'بدون كلمة مرور' : ($locale === 'fr' ? 'Sans mot de passe' : 'Password disabled')) }}</x-filament::badge>
                                @foreach ($account['providers'] as $provider)
                                    <x-filament::badge color="gray">{{ ucfirst($provider) }}</x-filament::badge>
                                @endforeach
                            </div>

                            <dl class="mt-4 grid min-w-0 grid-cols-2 gap-3 text-xs">
                                <div class="min-w-0 rounded-lg bg-white p-3">
                                    <dt class="text-gray-500">{{ $locale === 'ar' ? 'جلسات نشطة' : ($locale === 'fr' ? 'Sessions actives' : 'Active sessions') }}</dt>
                                    <dd class="mt-1 text-lg font-semibold text-gray-950">{{ $account['active_session_count'] }}</dd>
                                </div>
                                <div class="min-w-0 rounded-lg bg-white p-3">
                                    <dt class="text-gray-500">{{ $locale === 'ar' ? 'آخر نشاط' : ($locale === 'fr' ? 'Dernière activité' : 'Last activity') }}</dt>
                                    <dd class="mt-1 break-all text-gray-800" dir="ltr">{{ $account['last_activity_at'] ?? ($locale === 'ar' ? 'لا يوجد' : ($locale === 'fr' ? 'Aucune' : 'None')) }}</dd>
                                </div>
                            </dl>

                            <div class="mt-4 flex justify-end">
                                <x-filament::button size="sm" color="gray" wire:click="selectAccount('{{ $account['id'] }}')">{{ $locale === 'ar' ? 'عرض آمن' : ($locale === 'fr' ? 'Voir' : 'Inspect') }}</x-filament::button>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        @endif

        @if ($selectedAccount !== null)
            <section class="min-w-0 max-w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="selected-account-heading" data-testid="modrik-account-detail">
                <div class="flex min-w-0 flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">{{ $locale === 'ar' ? 'تفاصيل تشغيلية' : ($locale === 'fr' ? 'Détails opérationnels' : 'Operational detail') }}</p><h2 id="selected-account-heading" class="mt-1 break-words text-xl font-semibold text-gray-950">{{ $selectedAccount['name'] }}</h2><p class="mt-1 break-all text-sm text-gray-600" dir="ltr">{{ $selectedAccount['email'] }}</p></div>
                    <x-filament::button size="sm" color="gray" wire:click="clearSelection">{{ $locale === 'ar' ? 'إغلاق' : ($locale === 'fr' ? 'Fermer' : 'Close') }}</x-filament::button>
                </div>

                <div class="mt-5 grid min-w-0 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="min-w-0 rounded-xl bg-gray-50 p-4"><div class="text-xs text-gray-500">{{ $locale === 'ar' ? 'الدور' : ($locale === 'fr' ? 'Rôle' : 'Role') }}</div><div class="mt-1 break-words font-semibold text-gray-950">{{ str_replace('_', ' ', $selectedAccount['role']) }}</div></div>
                    <div class="min-w-0 rounded-xl bg-gray-50 p-4"><div class="text-xs text-gray-500">{{ $locale === 'ar' ? 'الحالة' : ($locale === 'fr' ? 'État' : 'Status') }}</div><div class="mt-1 break-words font-semibold text-gray-950">{{ $selectedAccount['account_status'] }}</div></div>
                    <div class="min-w-0 rounded-xl bg-gray-50 p-4"><div class="text-xs text-gray-500">{{ $locale === 'ar' ? 'التحقق' : ($locale === 'fr' ? 'Vérification' : 'Verification') }}</div><div class="mt-1 break-words font-semibold text-gray-950">{{ $selectedAccount['verified'] ? 'Verified' : 'Pending' }}</div></div>
                    <div class="min-w-0 rounded-xl bg-gray-50 p-4"><div class="text-xs text-gray-500">{{ $locale === 'ar' ? 'اللغة' : ($locale === 'fr' ? 'Langue' : 'Locale') }}</div><div class="mt-1 break-words font-semibold text-gray-950">{{ strtoupper($selectedAccount['locale']) }}</div></div>
                </div>

                <dl class="mt-4 grid min-w-0 gap-3 rounded-xl border border-gray-100 bg-gray-50 p-4 text-xs sm:grid-cols-3" data-testid="modrik-account-lifecycle-traceability">
                    <div class="min-w-0"><dt class="font-medium text-gray-500">{{ $locale === 'ar' ? 'تم الإنشاء' : ($locale === 'fr' ? 'Créé' : 'Created') }}</dt><dd class="mt-1 break-all text-gray-800" dir="ltr">{{ $selectedAccount['created_at'] }}</dd></div>
                    <div class="min-w-0"><dt class="font-medium text-gray-500">{{ $locale === 'ar' ? 'آخر تحديث' : ($locale === 'fr' ? 'Mis à jour' : 'Updated') }}</dt><dd class="mt-1 break-all text-gray-800" dir="ltr">{{ $selectedAccount['updated_at'] }}</dd></div>
                    <div class="min-w-0"><dt class="font-medium text-gray-500">{{ $locale === 'ar' ? 'حذف منطقي' : ($locale === 'fr' ? 'Suppression logique' : 'Tombstoned') }}</dt><dd class="mt-1 break-all text-gray-800" dir="ltr">{{ $selectedAccount['deleted_at'] ?? ($locale === 'ar' ? 'لا' : ($locale === 'fr' ? 'Non' : 'No')) }}</dd></div>
                </dl>

                <div class="mt-6 grid min-w-0 gap-6 xl:grid-cols-2">
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'هويات تسجيل الدخول المرتبطة' : ($locale === 'fr' ? 'Identités liées' : 'Linked sign-in identities') }}</h3>
                        <p class="mt-1 break-words text-xs text-gray-500">{{ $locale === 'ar' ? 'يظهر اسم المزود والحالة فقط؛ لا تظهر subject أو relay أو tokens.' : ($locale === 'fr' ? 'Seuls le fournisseur et son état sont visibles ; aucun subject, relay ou jeton.' : 'Provider name and status only; subject, relay and tokens stay hidden.') }}</p>
                        <div class="mt-3 space-y-2">@forelse ($selectedAccount['providers'] as $provider)<div class="flex min-w-0 flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-100 p-3"><span class="min-w-0 break-words font-medium text-gray-900">{{ ucfirst($provider['provider']) }}</span><x-filament::badge :color="$provider['status'] === 'linked' ? 'success' : 'gray'">{{ $provider['status'] }}</x-filament::badge></div>@empty<p class="rounded-xl bg-gray-50 p-3 text-sm text-gray-600">{{ $locale === 'ar' ? 'لا توجد هوية مزود مرتبطة.' : ($locale === 'fr' ? 'Aucune identité fournisseur liée.' : 'No linked provider identity.') }}</p>@endforelse</div>
                    </div>
                    <div class="min-w-0">
                        <h3 class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'أحداث الأمان الأخيرة' : ($locale === 'fr' ? 'Événements de sécurité récents' : 'Recent security events') }}</h3>
                        <p class="mt-1 break-words text-xs text-gray-500">{{ $locale === 'ar' ? 'أنواع أحداث وتوقيتات آمنة فقط؛ لا يتم عرض context hashes.' : ($locale === 'fr' ? 'Types et horodatages sûrs uniquement ; aucun hash de contexte.' : 'Safe event types and timestamps only; context hashes remain hidden.') }}</p>
                        <div class="mt-3 space-y-2">@forelse ($selectedAccount['security_events'] as $event)<div class="flex min-w-0 flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-100 p-3 text-sm"><span class="min-w-0 break-words font-medium text-gray-900">{{ str_replace('_', ' ', $event['event_type']) }}</span><time class="max-w-full break-all text-xs text-gray-500" dir="ltr">{{ $event['created_at'] }}</time></div>@empty<p class="rounded-xl bg-gray-50 p-3 text-sm text-gray-600">{{ $locale === 'ar' ? 'لا توجد أحداث مسجلة.' : ($locale === 'fr' ? 'Aucun événement enregistré.' : 'No recorded events.') }}</p>@endforelse</div>
                    </div>
                </div>

                <div class="mt-6 min-w-0 max-w-full">
                    <h3 class="font-semibold text-gray-950">{{ $locale === 'ar' ? 'الجلسات' : ($locale === 'fr' ? 'Sessions' : 'Sessions') }}</h3>
                    <p class="mt-1 break-words text-xs text-gray-500">{{ $locale === 'ar' ? 'لا يتم تحميل token_hash أو IP hash أو user-agent hash لهذه الشاشة.' : ($locale === 'fr' ? 'Aucun token_hash, hash IP ou hash user-agent n’est chargé pour cet écran.' : 'token_hash, IP hash and user-agent hash are not loaded for this screen.') }}</p>
                    <div class="mt-3 max-w-full overflow-hidden rounded-xl border border-gray-100">
                        <table class="w-full table-fixed divide-y divide-gray-100 text-sm">
                            <thead class="bg-gray-50 text-gray-600"><tr><th class="w-1/5 px-3 py-2 text-start">{{ $locale === 'ar' ? 'النوع' : ($locale === 'fr' ? 'Type' : 'Type') }}</th><th class="w-1/5 px-3 py-2 text-start">{{ $locale === 'ar' ? 'الحالة' : ($locale === 'fr' ? 'État' : 'State') }}</th><th class="w-[30%] px-3 py-2 text-start">{{ $locale === 'ar' ? 'آخر استخدام' : ($locale === 'fr' ? 'Dernière utilisation' : 'Last used') }}</th><th class="w-[30%] px-3 py-2 text-start">{{ $locale === 'ar' ? 'الانتهاء' : ($locale === 'fr' ? 'Expiration' : 'Expires') }}</th></tr></thead>
                            <tbody class="divide-y divide-gray-100">@forelse ($selectedAccount['sessions'] as $session)<tr wire:key="safe-session-{{ $session['id'] }}"><td class="break-words px-3 py-3">{{ $session['name'] ?? 'session' }}</td><td class="break-words px-3 py-3"><x-filament::badge :color="$session['state'] === 'active' ? 'success' : ($session['state'] === 'revoked' ? 'danger' : 'gray')">{{ $session['state'] }}</x-filament::badge></td><td class="break-all px-3 py-3 text-xs" dir="ltr">{{ $session['last_used_at'] }}</td><td class="break-all px-3 py-3 text-xs" dir="ltr">{{ $session['expires_at'] }}</td></tr>@empty<tr><td colspan="4" class="px-3 py-6 text-center text-gray-500">{{ $locale === 'ar' ? 'لا توجد جلسات مسجلة.' : ($locale === 'fr' ? 'Aucune session enregistrée.' : 'No recorded sessions.') }}</td></tr>@endforelse</tbody>
                        </table>
                    </div>
                </div>

                <div class="mt-6 min-w-0 max-w-full rounded-2xl border border-danger-200 bg-danger-50 p-5" data-testid="modrik-session-recovery">
                    <h3 class="break-words font-semibold text-danger-950">{{ $locale === 'ar' ? 'استرداد أمني: إلغاء كل الجلسات' : ($locale === 'fr' ? 'Récupération de sécurité : révoquer toutes les sessions' : 'Security recovery: revoke all sessions') }}</h3>
                    <p class="mt-1 break-words text-sm text-danger-800">{{ $locale === 'ar' ? 'يستخدم هذا الإجراء خدمة المصادقة الرسمية ويسجل السبب والمشغل والنتيجة في سجل غير قابل للتغيير من الواجهة.' : ($locale === 'fr' ? 'Cette action utilise le service Auth canonique et journalise la raison, l’opérateur et le résultat.' : 'This action uses the canonical Auth service and audits the reason, operator and result.') }}</p>
                    <label class="mt-4 block min-w-0"><span class="text-sm font-medium text-danger-950">{{ $locale === 'ar' ? 'سبب محدد (مطلوب)' : ($locale === 'fr' ? 'Raison précise (requise)' : 'Specific reason (required)') }}</span><textarea wire:model="revokeReason" rows="2" maxlength="500" class="mt-2 block w-full max-w-full rounded-lg border-danger-300 text-sm shadow-sm focus:border-danger-500 focus:ring-danger-500" placeholder="{{ $locale === 'ar' ? 'مثال: نشاط تسجيل دخول مشتبه به — إلغاء الجلسات كإجراء احترازي' : ($locale === 'fr' ? 'Exemple : activité de connexion suspecte — révocation préventive' : 'Example: suspected sign-in activity — revoke sessions as a precaution') }}"></textarea>@error('revokeReason') <span class="mt-1 block break-words text-xs text-danger-700">{{ $message }}</span> @enderror</label>
                    <div class="mt-4 flex min-w-0 flex-wrap justify-end"><x-filament::button color="danger" wire:click="revokeAllSessions" wire:confirm="{{ $locale === 'ar' ? 'تأكيد إلغاء كل الجلسات النشطة لهذا الحساب؟' : ($locale === 'fr' ? 'Confirmer la révocation de toutes les sessions actives de ce compte ?' : 'Confirm revocation of every active session for this account?') }}">{{ $locale === 'ar' ? 'إلغاء كل الجلسات' : ($locale === 'fr' ? 'Révoquer toutes les sessions' : 'Revoke all sessions') }}</x-filament::button></div>
                </div>
            </section>
        @endif

        <section class="min-w-0 max-w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="role-matrix-heading">
            <h2 id="role-matrix-heading" class="break-words font-semibold text-gray-950">{{ $locale === 'ar' ? 'مصفوفة الأدوار الحالية — للقراءة فقط' : ($locale === 'fr' ? 'Matrice des rôles actuels — lecture seule' : 'Current role matrix — read only') }}</h2>
            <p class="mt-1 break-words text-sm text-gray-600">{{ $locale === 'ar' ? 'المستودع يستخدم أدوارًا ثابتة حاليًا؛ لا يتم اختراع محرر صلاحيات دقيق من هذه الشاشة.' : ($locale === 'fr' ? 'Le dépôt utilise actuellement des rôles fixes ; aucun éditeur de permissions arbitraires n’est inventé ici.' : 'The repository currently uses fixed roles; this surface does not invent an arbitrary fine-grained permission editor.') }}</p>
            <div class="mt-4 grid min-w-0 gap-3 lg:grid-cols-3">@foreach ($roleMatrix as $role)<article class="min-w-0 rounded-xl border border-gray-100 p-4"><div class="break-words font-semibold text-gray-950">{{ str_replace('_', ' ', $role['role']) }}</div><div class="mt-3 flex min-w-0 flex-wrap gap-2 text-xs"><x-filament::badge :color="$role['admin_panel'] ? 'success' : 'gray'">Admin {{ $role['admin_panel'] ? '✓' : '—' }}</x-filament::badge><x-filament::badge :color="$role['content_operations'] ? 'success' : 'gray'">Content {{ $role['content_operations'] ? '✓' : '—' }}</x-filament::badge><x-filament::badge :color="$role['student_learning'] ? 'success' : 'gray'">Student {{ $role['student_learning'] ? '✓' : '—' }}</x-filament::badge></div><p class="mt-3 break-words text-xs text-gray-500">{{ $role['notes'] }}</p></article>@endforeach</div>
        </section>

        <section class="min-w-0 max-w-full rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="account-audit-heading">
            <h2 id="account-audit-heading" class="break-words font-semibold text-gray-950">{{ $locale === 'ar' ? 'سجل إجراءات الحسابات' : ($locale === 'fr' ? 'Journal des opérations de compte' : 'Account operation audit') }}</h2>
            <div class="mt-4 min-w-0 space-y-3">@forelse ($audits as $audit)<div class="min-w-0 rounded-xl border border-gray-100 p-4"><div class="flex min-w-0 flex-wrap items-center justify-between gap-2"><span class="min-w-0 break-words font-semibold text-gray-900">{{ str_replace('.', ' · ', $audit['action']) }}</span><time class="max-w-full break-all text-xs text-gray-500" dir="ltr">{{ $audit['occurred_at'] }}</time></div><p class="mt-2 break-words text-sm text-gray-700">{{ $audit['reason'] }}</p><p class="mt-2 break-words text-xs text-gray-500">{{ $audit['actor_name'] }} → {{ $audit['target_name'] }}</p></div>@empty<p class="rounded-xl bg-gray-50 p-4 text-sm text-gray-600">{{ $locale === 'ar' ? 'لا توجد إجراءات إدارية حساسة مسجلة بعد.' : ($locale === 'fr' ? 'Aucune opération administrative sensible enregistrée.' : 'No sensitive administrative account operation has been recorded yet.') }}</p>@endforelse</div>
        </section>

        <div class="flex min-w-0 max-w-full flex-wrap justify-end gap-2">
            <x-filament::button tag="a" color="gray" href="/admin/system-capabilities">{{ $locale === 'ar' ? 'قدرات النظام' : ($locale === 'fr' ? 'Capacités système' : 'System Capabilities') }}</x-filament::button>
            <x-filament::button tag="a" color="gray" href="/admin/operations-control-center">{{ $locale === 'ar' ? 'مركز التحكم التشغيلي' : ($locale === 'fr' ? 'Centre de contrôle opérationnel' : 'Operations Control Center') }}</x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
