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
                @if ($formulario === 'recorrencia')
                    {{ $descricao }}: valor só em {{ $mes->competencia->nomeDoMes() }} de {{ $mes->competencia->ano }}
                @elseif ($formulario === 'guardado')
                    {{ $movimentoId ? 'Editar movimento do guardado' : 'Guardar ou retirar' }}
                @else
                    {{ $editandoId ? 'Editar' : 'Nova' }} {{ $formulario === 'receita' ? 'receita' : 'despesa' }}
                @endif
            </h2>

            <form wire:submit="salvar" class="space-y-3">
                @if ($formulario === 'guardado')
                    <fieldset>
                        <legend class="mb-1 text-sm font-medium">O que você fez</legend>
                        <div class="grid grid-cols-2 gap-2">
                            @foreach (['guardar' => 'Guardei', 'retirar' => 'Retirei'] as $opcao => $rotulo)
                                <label @class([
                                    'flex cursor-pointer items-center justify-center rounded-lg px-3 py-2 text-sm font-medium ring-1',
                                    'bg-teal-50 text-teal-800 ring-teal-600' => $tipoMovimento === $opcao,
                                    'text-slate-600 ring-slate-300' => $tipoMovimento !== $opcao,
                                ])>
                                    <input type="radio" wire:model.live="tipoMovimento" value="{{ $opcao }}" class="sr-only">
                                    {{ $rotulo }}
                                </label>
                            @endforeach
                        </div>
                        @error('tipoMovimento') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </fieldset>
                @endif

                @if ($formulario === 'recorrencia')
                    <p class="text-sm text-slate-500">Os outros meses continuam com o valor padrão. Para mudar daqui para frente, use <a href="{{ route('recorrencias') }}" class="font-medium text-teal-700 hover:underline">Despesas fixas</a>.</p>
                @else
                <div>
                    <label for="descricao" class="mb-1 block text-sm font-medium">Descrição @if ($formulario === 'guardado')<span class="font-normal text-slate-400">(opcional)</span>@endif</label>
                    <input id="descricao" type="text" wire:model="descricao" autocomplete="off" autofocus maxlength="255"
                           placeholder="{{ match ($formulario) { 'receita' => 'Ex.: Salário', 'guardado' => 'Ex.: CDB, poupança, resgate', default => 'Ex.: Mercado' } }}" class="{{ $campo }}">
                    @error('descricao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                @endif

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
                        @elseif ($item->origem->value === 'parcelamento')
                            <a href="{{ route('parcelamentos') }}" class="shrink-0 rounded-md px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50" aria-label="Gerenciar o parcelamento {{ $item->descricao }}">Gerenciar</a>
                        @elseif ($item->origem->value === 'recorrencia')
                            <div class="flex shrink-0 gap-1">
                                <button type="button" wire:click="editarRecorrencia({{ $item->recorrenciaId }})" class="rounded-md px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50" aria-label="Editar o valor de {{ $item->descricao }} só neste mês">Editar</button>
                                @if ($item->valorEditadoNoMes)
                                    <button type="button" wire:click="voltarAoPadrao({{ $item->recorrenciaId }})" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" aria-label="Voltar {{ $item->descricao }} ao valor padrão">Padrão</button>
                                @endif
                            </div>
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

    {{-- Guardado (RF-06) --}}
    <section aria-labelledby="titulo-guardado">
        <div class="mb-2 flex items-center justify-between">
            <h2 id="titulo-guardado" class="font-semibold">Guardado</h2>
            <button type="button" wire:click="abrir('guardado')" class="text-sm font-medium text-teal-700 hover:underline">+ Guardar ou retirar</button>
        </div>

        @error('movimentos')
            <p class="mb-2 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700" role="alert">{{ $message }}</p>
        @enderror

        @if ($mes->movimentos === [])
            <p class="rounded-xl bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Nada guardado nem retirado neste mês.</p>
        @else
            <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
                @foreach ($mes->movimentos as $item)
                    @php($rotuloMovimento = $item->movimento->descricao ?: ($item->movimento->tipo->value === 'guardar' ? 'Guardado' : 'Retirado'))
                    <li class="flex items-center gap-3 px-4 py-3" wire:key="movimento-{{ $item->id }}">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm">{{ $rotuloMovimento }}</p>
                            <span class="text-xs text-slate-400">{{ $item->movimento->tipo->value === 'guardar' ? 'guardei' : 'retirei' }}</span>
                        </div>
                        <span @class(['text-sm font-medium tabular-nums', 'text-amber-700' => $item->movimento->tipo->value === 'retirar'])>{{ $item->movimento->efeito()->formatar() }}</span>
                        <div class="flex shrink-0 gap-1">
                            <button type="button" wire:click="editarMovimento({{ $item->id }})" class="rounded-md px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50" aria-label="Editar {{ $rotuloMovimento }}">Editar</button>
                            <button type="button" wire:click="excluirMovimento({{ $item->id }})" wire:confirm="Excluir “{{ $rotuloMovimento }}”?" class="rounded-md px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50" aria-label="Excluir {{ $rotuloMovimento }}">Excluir</button>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>
