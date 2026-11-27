@props(['mes', 'mesAtual'])
{{-- ‹ Março de 2026 › — navega mês a mês, atravessando anos. --}}
<nav aria-label="Escolher mês" class="flex items-center justify-between gap-2">
    @if ($mes->temAnterior())
        <a href="{{ route('mes', (string) $mes->anterior()) }}" class="rounded-full p-2 text-slate-600 hover:bg-slate-200" aria-label="Mês anterior: {{ $mes->anterior()->nomeDoMes() }} de {{ $mes->anterior()->ano }}">
            <x-nav.icone nome="anterior" />
        </a>
    @else
        <span class="size-10" aria-hidden="true"></span>
    @endif

    <div class="min-w-0 text-center">
        <h1 class="truncate text-lg font-semibold">{{ $mes->nomeDoMes() }} de {{ $mes->ano }}</h1>
        @unless ($mes->igual($mesAtual))
            <a href="{{ route('mes') }}" class="text-xs text-teal-700 hover:underline">Ir para o mês atual</a>
        @endunless
    </div>

    @if ($mes->temProxima())
        <a href="{{ route('mes', (string) $mes->proxima()) }}" class="rounded-full p-2 text-slate-600 hover:bg-slate-200" aria-label="Próximo mês: {{ $mes->proxima()->nomeDoMes() }} de {{ $mes->proxima()->ano }}">
            <x-nav.icone nome="proximo" />
        </a>
    @else
        <span class="size-10" aria-hidden="true"></span>
    @endif
</nav>
