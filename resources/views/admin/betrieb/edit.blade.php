<x-app-layout>
    <x-slot name="title">Betrieb</x-slot>
    <x-slot name="header">
        <div class="w-full flex items-center justify-between gap-4">
            <h2 class="font-semibold text-xl text-text">Betrieb</h2>
            <a href="{{ route('admin.einrichtung') }}" class="inline-flex items-center px-4 h-10 rounded-xl glass-btn text-text text-sm">Einrichtung</a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8">
            <form method="POST" action="{{ route('admin.betrieb.update') }}" class="glass rounded-2xl p-6 flex flex-col gap-6"
                  x-data="{ loading: false }" @submit="if (!$event.defaultPrevented) loading = true">
                @csrf
                @method('PUT')
                @include('admin.betrieb._felder', ['werte' => $werte])
                <div class="flex justify-end">
                    <button type="submit" :disabled="loading" class="inline-flex items-center px-5 h-10 rounded-xl bg-accent text-white text-sm font-semibold np-btn-primary disabled:opacity-60">Speichern</button>
                </div>
            </form>
        </div>
    </div>
</x-app-layout>
