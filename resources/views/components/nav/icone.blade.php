@props(['nome'])
{{-- Ícones de traço simples (24×24), herdam a cor do texto. --}}
<svg {{ $attributes->merge(['class' => 'size-6', 'fill' => 'none', 'viewBox' => '0 0 24 24', 'stroke' => 'currentColor', 'stroke-width' => '1.8', 'aria-hidden' => 'true']) }}>
    @switch($nome)
        @case('inicio')
            <path stroke-linecap="round" stroke-linejoin="round" d="m3 10.5 9-7 9 7V20a1 1 0 0 1-1 1h-5v-6h-6v6H4a1 1 0 0 1-1-1v-9.5Z"/>
            @break
        @case('mes')
            <rect x="3.5" y="5" width="17" height="15.5" rx="2"/><path stroke-linecap="round" d="M3.5 9.5h17M8 3v4M16 3v4"/>
            @break
        @case('ano')
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 20V10m5.33 10V4m5.34 16v-7M20 20V7"/>
            @break
        @case('mais')
            <path stroke-linecap="round" d="M4 7h16M4 12h16M4 17h16"/>
            @break
        @case('mais-sinal')
            <path stroke-linecap="round" d="M12 5v14M5 12h14"/>
            @break
        @case('anterior')
            <path stroke-linecap="round" stroke-linejoin="round" d="m15 18-6-6 6-6"/>
            @break
        @case('proximo')
            <path stroke-linecap="round" stroke-linejoin="round" d="m9 18 6-6-6-6"/>
            @break
    @endswitch
</svg>
