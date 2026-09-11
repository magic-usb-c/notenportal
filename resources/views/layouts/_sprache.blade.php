{{-- Sprachwechsel im Benutzermenü (nur mit eingeschalteter Sprachwahl); Sprachname in der Zielsprache --}}
@php($ziel = app()->getLocale() === 'en' ? 'de' : 'en')
<form method="POST" action="{{ route('profile.locale') }}">
    @csrf
    @method('PUT')
    <input type="hidden" name="locale" value="{{ $ziel }}">
    <button type="submit" lang="{{ $ziel }}" class="{{ $klasse }}">{{ $ziel === 'en' ? 'English' : 'Deutsch' }}</button>
</form>
