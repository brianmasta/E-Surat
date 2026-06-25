@props(['class' => 'h-11 w-11'])

<img src="{{ asset('esurat-256x256.png') }}" alt="Logo E-Surat" {{ $attributes->merge(['class' => $class.' rounded-lg object-cover shadow-sm ring-1 ring-white/10']) }}>
