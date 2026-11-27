@php
    $resumo = $mes->resumo;
    $campo = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none';
    $tagOrigem = [
        'recorrencia' => 'fixa',
        'parcelamento' => 'parcela',
    ];
@endphp
<div class="mt-4 space-y-6">
    @if ($mes->ehProjecao)
        <p class="rounded-lg bg-sky-50 px-3 py-2 text-sm text-sky-800" role="note">
            <strong class="font-semibold">Projeção.</strong> Este mês ainda não chegou: mostra as despesas fixas e parcelas previstas.
        </p>
    @endif

    {{-- RF-03: resumo do mês (RD-01..RD-05) --}}
    <section aria-labelledby="titulo-resumo">
        <h2 id="titulo-resumo" class="sr-only">Resumo do mês</h2>
        <dl class="grid grid-cols-2 gap-2">
            <x-mes.total id="total-contas" rotulo="Contas a pagar" :valor="$resumo->contasAPagar" />
            <x-mes.total id="total-recebidos" rotulo="Recebidos" :valor="$resumo->recebidos" />
            <x-mes.total id="total-guardado-mes" rotulo="Guardado no mês" :valor="$resumo->guardadoNoMes" />
            <x-mes.total id="total-restante" rotulo="Restante" :valor="$resumo->restante" destaque />
        </dl>
        <p class="mt-2 text-right text-xs text-slate-500">
            Total guardado até este mês: <span id="total-guardado" class="font-medium text-slate-700 tabular-nums">{{ $mes->totalGuardado->formatar() }}</span>
        </p>
    </section>

    {{-- Formulário de lançar / editar (RF-01, RF-02) --}}
    @if ($formulario)
        <section aria-labelledby="titulo-formulario" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-teal-200">
            <h2 id="titulo-formulario" class="mb-3 font-semibold">
                {{ $editandoId ? 'Editar' : 'Nova' }} {{ $formulario === 'receita' ? 'receita' : 'despesa' }}
            </h2>

            <form wire:submit="salvar" class="space-y-3">
                <div>
                    <label for="descricao" class="mb-1 block text-sm font-medium">Descrição</label>
                    <input id="descricao" type="text" wire:model="descricao" autocomplete="off" autofocus maxlength="255"
                           placeholder="{{ $formulario === 'receita' ? 'Ex.: Salário' : 'Ex.: Mercado' }}" class="{{ $campo }}">
                    @error('descricao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="valor" class="mb-1 block text-sm font-medium">Valor (R$)</label>
                    <input id="valor" type="text" inputmode="decimal" wire:model="valor" autocomplete="off" placeholder="0,00" class="{{ $campo }} tabular-nums">
                    @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>

                @if ($formulario === 'receita')
                    <div>
                        <label for="categoria" class="mb-1 block text-sm font-medium">Tipo de receita</label>
                        <select id="categoria" wire:model="categoria" class="{{ $campo }}">
                            @foreach ($categorias as $c)
                                <option value="{{ $c->value }}">{{ $c->rotulo() }}</option>
                            @endforeach
                        </select>
                        @error('categoria') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 rounded-lg bg-teal-700 px-4 py-2.5 font-medium text-white hover:bg-teal-800">Salvar</button>
                    <button type="button" wire:click="cancelar" class="rounded-lg px-4 py-2.5 font-medium text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    {{-- Despesas --}}
    <section aria-labelledby="titulo-despesas">
        <div class="mb-2 flex items-center justify-between">
            <h2 id="titulo-despesas" class="font-semibold">Despesas</h2>
            <button type="button" wire:click="abrir('despesa')" class="text-sm font-medium text-teal-700 hover:underline">+ Despesa</button>
        </div>

        @if ($mes->despesas === [])
            <p class="rounded-xl bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Nenhuma despesa neste mês.</p>
        @else
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                @foreach ($mes->despesas as $item)
                    <li class="flex items-center gap-3 px-4 py-3" wire:key="despesa-{{ $item->origem->value }}-{{ $item->lancamentoId ?? $item->recorrenciaId ?? $item->parcelamentoId }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ $item->descricao }}</p>
                            @isset($tagOrigem[$item->origem->value])
                                <span class="text-xs text-slate-400">{{ $tagOrigem[$item->origem->value] }}{{ $item->valorEditadoNoMes ? ' · valor só deste mês' : '' }}</span>
                            @endisset
                        </div>
                        <span class="text-sm font-medium tabular-nums">{{ $item->valor->formatar() }}</span>
                        @if ($item->origem->value === 'avulso')
                            <x-mes.acoes :id="$item->lancamentoId" :descricao="$item->descricao" />
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Receitas --}}
    <section aria-labelledby="titulo-receitas">
        <div class="mb-2 flex items-center justify-between">
            <h2 id="titulo-receitas" class="font-semibold">Receitas</h2>
            <button type="button" wire:click="abrir('receita')" class="text-sm font-medium text-teal-700 hover:underline">+ Receita</button>
        </div>

        @if ($mes->receitas === [])
            <p class="rounded-xl bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Nenhuma receita neste mês.</p>
        @else
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                @foreach ($mes->receitas as $item)
                    <li class="flex items-center gap-3 px-4 py-3" wire:key="receita-{{ $item->lancamentoId }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ $item->descricao }}</p>
                            <span class="text-xs text-slate-400">{{ $item->categoria->rotulo() }}</span>
                        </div>
                        <span class="text-sm font-medium tabular-nums">{{ $item->valor->formatar() }}</span>
                        <x-mes.acoes :id="$item->lancamentoId" :descricao="$item->descricao" />
                    </li>
                @endforeach
            </ul>
        @endif
    </section>

    {{-- Guardado (o formulário chega na T14) --}}
    @if ($mes->movimentos !== [])
        <section aria-labelledby="titulo-guardado">
            <h2 id="titulo-guardado" class="mb-2 font-semibold">Guardado</h2>
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                @foreach ($mes->movimentos as $item)
                    <li class="flex items-center gap-3 px-4 py-3" wire:key="movimento-{{ $item->id }}">
                        <p class="min-w-0 flex-1 truncate text-sm">{{ $item->movimento->descricao ?: ($item->movimento->tipo->value === 'guardar' ? 'Guardado' : 'Retirado') }}</p>
                        <span class="text-sm font-medium tabular-nums">{{ $item->movimento->efeito()->formatar() }}</span>
                    </li>
                @endforeach
            </ul>
        </section>
    @endif
</div>
