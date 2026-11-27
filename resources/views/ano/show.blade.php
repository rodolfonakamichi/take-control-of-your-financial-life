<x-layouts.app :titulo="'Ano de '.$ano">
    <nav aria-label="Escolher ano" class="flex items-center justify-between gap-2">
        <a href="{{ route('ano', $ano - 1) }}" class="rounded-full p-2 text-slate-600 hover:bg-slate-200" aria-label="Ano anterior: {{ $ano - 1 }}">
            <x-nav.icone nome="anterior" />
        </a>
        <div class="text-center">
            <h1 class="text-lg font-semibold">{{ $ano }}</h1>
            @if ($ano !== $mesAtual->ano)
                <a href="{{ route('ano') }}" class="text-xs text-teal-700 hover:underline">Ir para o ano atual</a>
            @endif
        </div>
        <a href="{{ route('ano', $ano + 1) }}" class="rounded-full p-2 text-slate-600 hover:bg-slate-200" aria-label="Próximo ano: {{ $ano + 1 }}">
            <x-nav.icone nome="proximo" />
        </a>
    </nav>

    {{-- CA-07.3: cada mês abre o detalhe. Os totais por mês chegam na T15. --}}
    <ul class="mt-6 grid grid-cols-3 gap-2 sm:grid-cols-4">
        @foreach ($meses as $m)
            <li>
                <a href="{{ route('mes', (string) $m) }}"
                   @if ($m->igual($mesAtual)) aria-current="date" @endif
                   @class([
                       'block rounded-xl bg-white px-2 py-4 text-center text-sm font-medium shadow-sm ring-1 hover:ring-teal-600',
                       'ring-2 ring-teal-600 text-teal-800' => $m->igual($mesAtual),
                       'ring-slate-200' => ! $m->igual($mesAtual),
                   ])>
                    {{ $m->nomeDoMes() }}
                </a>
            </li>
        @endforeach
    </ul>
</x-layouts.app>
