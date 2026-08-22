<x-filament-panels::page>
    @php($locale = app()->getLocale())
    @php($rtl = $locale === 'ar')

    <div class="space-y-6" dir="{{ $rtl ? 'rtl' : 'ltr' }}" data-testid="modrik-auth-providers">
        <x-admin.operational-banner
            :severity="collect($providers)->contains(fn ($provider) => ($provider['transport_status'] ?? '') === 'pending') ? 'warning' : 'success'"
            :title="$locale === 'ar' ? 'حالة مزودي المصادقة' : ($locale === 'fr' ? 'État des fournisseurs d’authentification' : 'Authentication provider status')"
            :message="$locale === 'ar' ? 'التفعيل لا يعني أن النقل الخارجي جاهز. Google وApple يظلان Fail-Closed حتى يتوفر Adapter معتمد.' : ($locale === 'fr' ? 'L’activation ne signifie pas que le transport externe est prêt. Google et Apple restent fail-closed tant qu’un adaptateur approuvé n’existe pas.' : 'Enablement does not imply external transport readiness. Google and Apple remain fail-closed until an approved adapter exists.')"
        >
            <div class="modrik-code mt-2 text-xs" dir="ltr">APP_ENV={{ $environment }}</div>
        </x-admin.operational-banner>

        <div class="grid gap-4 xl:grid-cols-3">
            @foreach ($providers as $name => $provider)
                <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm" aria-labelledby="provider-{{ $name }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-primary-600">{{ strtoupper($name) }}</p>
                            <h2 id="provider-{{ $name }}" class="mt-2 text-lg font-semibold text-gray-950">
                                {{ $name === 'email' ? ($locale === 'ar' ? 'البريد وكلمة المرور' : ($locale === 'fr' ? 'E-mail et mot de passe' : 'Email & Password')) : ucfirst($name) }}
                            </h2>
                        </div>
                        <x-filament::badge :color="$provider['enabled'] ? 'success' : 'gray'">
                            {{ $provider['enabled'] ? ($locale === 'ar' ? 'مفعّل' : ($locale === 'fr' ? 'Activé' : 'Enabled')) : ($locale === 'ar' ? 'متوقف' : ($locale === 'fr' ? 'Désactivé' : 'Disabled')) }}
                        </x-filament::badge>
                    </div>

                    <dl class="mt-5 space-y-3 text-sm">
                        @foreach ($provider as $key => $value)
                            @continue(in_array($key, ['enabled'], true))
                            <div class="flex min-w-0 items-start justify-between gap-4 border-t border-gray-100 pt-3">
                                <dt class="text-gray-500">{{ str_replace('_', ' ', ucfirst($key)) }}</dt>
                                <dd class="min-w-0 text-end font-medium text-gray-900">
                                    @if (is_bool($value))
                                        <x-filament::badge :color="$value ? 'success' : 'gray'">{{ $value ? 'Set' : 'Not Set' }}</x-filament::badge>
                                    @elseif ($key === 'transport_status')
                                        <x-filament::badge :color="$value === 'available' ? 'success' : 'warning'">{{ $value }}</x-filament::badge>
                                    @else
                                        <span class="modrik-code break-all text-xs" dir="ltr">{{ $value }}</span>
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                </section>
            @endforeach
        </div>

        <div class="flex flex-wrap justify-end gap-2">
            <x-filament::button tag="a" href="/admin/system-settings">
                {{ $locale === 'ar' ? 'إدارة التفعيل والإصدارات' : ($locale === 'fr' ? 'Gérer l’activation et les versions' : 'Manage enablement & versions') }}
            </x-filament::button>
        </div>
    </div>
</x-filament-panels::page>
