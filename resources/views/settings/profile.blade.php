<x-app-layout>
    <x-slot name="title">{{ __('Mein Profil') }}</x-slot>
    <x-slot name="header">
        <x-seitenkopf :titel="__('Mein Profil')" schmal>
            @include('settings._tabs')
        </x-seitenkopf>
    </x-slot>

    <div class="py-6">
        <div class="mx-auto np-seite px-8">
        <div class="flex np-spalte flex-col gap-8">

            @if($lernender)
                <section>
                    <h2 class="mb-2 px-1 text-sm font-semibold text-text">{{ __('Lehrausbildung') }}</h2>
                    <div class="np-karte np-gruppe">
                        <x-einstellung :label="__('Lehrberuf')">
                            <span class="text-sm text-muted">
                                {{ $lehrberuf->name ?? '–' }}@if($lehrberuf?->kuerzel) ({{ $lehrberuf->kuerzel }})@endif
                            </span>
                        </x-einstellung>
                        <x-einstellung :label="__('Lehrbeginn')">
                            <span class="text-sm text-muted tabular-nums">{{ $lernender->lehrbeginn?->format('d.m.Y') ?? '–' }}</span>
                        </x-einstellung>
                        @if($lernender->lehrende)
                            <x-einstellung :label="__('Ende der Lehre')">
                                <span class="text-sm text-muted tabular-nums">{{ $lernender->lehrende->format('d.m.Y') }}</span>
                            </x-einstellung>
                        @endif
                    </div>
                </section>
            @endif

            @include('profile.partials.update-profile-information-form')

            @if(\App\Http\Middleware\SetLocale::wahlAktiv())
                @include('profile.partials.sprache')
            @endif

            @include('profile.partials.update-password-form')

        </div>
        </div>
    </div>
</x-app-layout>
