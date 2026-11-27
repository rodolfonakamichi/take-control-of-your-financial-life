@php
    $resumoAno = $visao->resumo;
@endphp
<x-layouts.app :titulo="'Ano de '.$ano">
    <nav aria-label="Escolher ano" class="flex items-center justify-between gap-2">
        @if ($ano > \App\Domain\Competencia::ANO_MINIMO)
            <a href="{{ route('ano', $ano - 1) }}" class="rounded-full p-2 text-slate-600 hover:bg-slate-200" aria-label="Ano anterior: {{ $ano - 1 }}">
                <x-nav.icone nome="anterior" />
            </a>
        @else
            <span class="size-10" aria-hidden="true"></span>
        @endif
        <div class="text-center">
            <h1 class="text-lg font-semibold">{{ $ano }}</h1>
            @if ($ano !== $mesAtual->ano)
                <a href="{{ route('ano') }}" class="text-xs text-teal-700 hover:underline">Ir para o ano atual</a>
            @endif
        </div>
        @if ($ano < \App\Domain\Competencia::ANO_MAXIMO)
            <a href="{{ route('ano', $ano + 1) }}" class="rounded-full p-2 text-slate-600 hover:bg-slate-200" aria-label="Próximo ano: {{ $ano + 1 }}">
                <x-nav.icone nome="proximo" />
            </a>
        @else
            <span class="size-10" aria-hidden="true"></span>
        @endif
    </nav>

    {{-- CA-07.2: totais do ano (RD-08) --}}
    <section aria-labelledby="titulo-totais-ano" class="mt-4">
        <h2 id="titulo-totais-ano" class="mb-2 text-sm font-medium text-slate-500">Total do ano</h2>
        <dl class="grid grid-cols-2 gap-2">
            <x-mes.total id="ano-contas" rotulo="Contas a pagar" :valor="$resumoAno->contasAPagar" />
            <x-mes.total id="ano-recebidos" rotulo="Recebidos" :valor="$resumoAno->recebidos" />
            <x-mes.total id="ano-guardado" rotulo="Guardado no ano" :valor="$resumoAno->guardadoNoAno" />
            <x-mes.total id="ano-restante" rotulo="Restante no ano" :valor="$resumoAno->restante" destaque />
        </dl>
        <p class="mt-2 text-right text-xs text-slate-500">
            Total guardado ao fim de {{ $ano }}: <span id="ano-total-guardado" class="font-medium text-slate-700 tabular-nums">{{ $visao->totalGuardadoNoFim->formatar() }}</span>
        </p>
    </section>

    {{-- CA-07.1: os 12 meses; CA-07.3: cada um abre o detalhe --}}
    <section aria-labelledby="titulo-meses" class="mt-6">
        <h2 id="titulo-meses" class="mb-2 text-sm font-medium text-slate-500">Mês a mês</h2>
        <ol class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($visao->meses as $numero => $mes)
                @php($r = $mes->resumo)
                <li>
                    <a href="{{ route('mes', (string) $mes->competencia) }}" id="mes-{{ $numero }}"
                       @if ($mes->competencia->igual($mesAtual)) aria-current="date" @endif
                       @class([
                           'block rounded-xl bg-white p-3 shadow-sm hover:ring-teal-600',
                           'ring-2 ring-teal-600' => $mes->competencia->igual($mesAtual),
                           'ring-1 ring-slate-200' => ! $mes->competencia->igual($mesAtual),
                       ])>
                        <div class="flex items-start justify-between gap-2">
                            <p class="flex min-w-0 flex-wrap items-center gap-x-1.5 font-medium">
                                {{ $mes->competencia->nomeDoMes() }}
                                @if ($mes->ehProjecao)
                                    <span class="rounded bg-sky-50 px-1.5 py-0.5 text-xs font-normal text-sky-700">projeção</span>
                                @endif
                            </p>
                            <p data-campo="restante" @class(['shrink-0 font-semibold whitespace-nowrap tabular-nums', 'text-red-700' => $r->restanteNegativo()])>{{ $r->restante->formatar() }}</p>
                        </div>
                        <dl class="mt-2 grid grid-cols-2 gap-x-3 gap-y-0.5 text-xs">
                            <dt class="text-slate-500">Contas a pagar</dt>
                            <dd data-campo="contas" class="text-right tabular-nums">{{ $r->contasAPagar->formatar() }}</dd>
                            <dt class="text-slate-500">Recebidos</dt>
                            <dd data-campo="recebidos" class="text-right tabular-nums">{{ $r->recebidos->formatar() }}</dd>
                            <dt class="text-slate-500">Guardado no mês</dt>
                            <dd data-campo="guardado" class="text-right tabular-nums">{{ $r->guardadoNoMes->formatar() }}</dd>
                            <dt class="text-slate-500">Total guardado</dt>
                            <dd data-campo="total-guardado" class="text-right tabular-nums">{{ $mes->totalGuardado->formatar() }}</dd>
                        </dl>
                    </a>
                </li>
            @endforeach
        </ol>
    </section>
</x-layouts.app>
