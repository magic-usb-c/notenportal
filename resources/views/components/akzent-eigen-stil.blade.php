{{-- Eigene Akzentfarbe (Profil «Darstellung»): server-berechnete Tokens (App\Support\Farbe),
     nie der rohe Hex-Wert. Nur ausgeben, wenn tatsächlich eine gültige eigene Farbe aktiv ist. --}}
@props(['hex' => null])
@if($hex)
<style id="np-akzent-eigen">{!! \App\Support\Farbe::styleBlock($hex) !!}</style>
@endif
