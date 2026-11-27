@php
    $campo = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none';
    $rotuloSituacao = ['ativa' => null, 'futura' => 'começa depois', 'encerrada' => 'encerrada'];
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-semibold">Despesas fixas</h1>
        <button type="button" wire:click="nova" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800">+ Nova</button>
    </div>

    <p class="text-sm text-slate-500">Aparecem sozinhas em todos os meses entre o início e o fim. Para mudar o valor de um mês só, use “Editar” na tela do mês.</p>

    @if ($acao)
        <section aria-labelledby="titulo-formulario" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-teal-200">
            <h2 id="titulo-formulario" class="mb-3 font-semibold">
                @switch($acao)
                    @case('nova') Nova despesa fixa @break
                    @case('valor') Novo valor de {{ $selecionada?->descricao }} @break
                    @case('encerrar') Encerrar {{ $selecionada?->descricao }} @break
                    @case('renomear') Renomear {{ $selecionada?->descricao }} @break
                @endswitch
            </h2>

            <form wire:submit="salvar" class="space-y-3">
                @if (in_array($acao, ['nova', 'renomear']))
                    <div>
                        <label for="descricao" class="mb-1 block text-sm font-medium">Descrição</label>
                        <input id="descricao" type="text" wire:model="descricao" maxlength="255" autocomplete="off" autofocus placeholder="Ex.: Internet" class="{{ $campo }}">
                        @error('descricao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if (in_array($acao, ['nova', 'valor']))
                    <div>
                        <label for="valor" class="mb-1 block text-sm font-medium">{{ $acao === 'valor' ? 'Novo valor (R$)' : 'Valor por mês (R$)' }}</label>
                        <input id="valor" type="text" inputmode="decimal" wire:model="valor" autocomplete="off" placeholder="0,00" class="{{ $campo }} tabular-nums">
                        @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($acao === 'nova')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="inicio" class="mb-1 block text-sm font-medium">Primeiro mês</label>
                            <input id="inicio" type="month" wire:model="inicio" class="{{ $campo }}">
                            @error('inicio') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="fim" class="mb-1 block text-sm font-medium">Último mês <span class="font-normal text-slate-400">(opcional)</span></label>
                            <input id="fim" type="month" wire:model="fim" class="{{ $campo }}">
                            @error('fim') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                @endif

                @if ($acao === 'valor')
                    <div>
                        <label for="aPartirDe" class="mb-1 block text-sm font-medium">A partir de</label>
                        <input id="aPartirDe" type="month" wire:model="aPartirDe" class="{{ $campo }}">
                        @error('aPartirDe') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">Os meses anteriores não mudam. Meses com valor editado à mão mantêm o valor editado.</p>
                    </div>
                @endif

                @if ($acao === 'encerrar')
                    <div>
                        <label for="ultimoMes" class="mb-1 block text-sm font-medium">Último mês em que aparece</label>
                        <input id="ultimoMes" type="month" wire:model="ultimoMes" class="{{ $campo }}">
                        @error('ultimoMes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 rounded-lg bg-teal-700 px-4 py-2.5 font-medium text-white hover:bg-teal-800">Salvar</button>
                    <button type="button" wire:click="cancelar" class="rounded-lg px-4 py-2.5 font-medium text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    @if ($itens === [])
        <p class="rounded-xl bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Nenhuma despesa fixa cadastrada.</p>
    @else
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            @foreach ($itens as $item)
                <li wire:key="recorrencia-{{ $item['id'] }}" @class(['px-4 py-3', 'opacity-60' => $item['situacao'] === 'encerrada'])>
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ $item['descricao'] }}
                                @if ($rotuloSituacao[$item['situacao']])
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-500">{{ $rotuloSituacao[$item['situacao']] }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-slate-500">
                                desde {{ $item['inicio']->formatar() }}{{ $item['fim'] ? ' até '.$item['fim']->formatar() : ', sem fim' }}
                            </p>
                            @foreach ($item['proximas'] as $vigencia)
                                <p class="text-xs text-teal-700">{{ $vigencia->valor->formatar() }} a partir de {{ $vigencia->desde->formatar() }}</p>
                            @endforeach
                        </div>
                        <span class="text-sm font-medium tabular-nums">{{ $item['valor']->formatar() }}</span>
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        <button type="button" wire:click="alterarValor({{ $item['id'] }})" class="rounded-md px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50" aria-label="Mudar o valor de {{ $item['descricao'] }}">Mudar valor</button>
                        <button type="button" wire:click="encerrar({{ $item['id'] }})" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" aria-label="Encerrar {{ $item['descricao'] }}">Encerrar</button>
                        <button type="button" wire:click="renomear({{ $item['id'] }})" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" aria-label="Renomear {{ $item['descricao'] }}">Renomear</button>
                        <button type="button" wire:click="excluir({{ $item['id'] }})" wire:confirm="Excluir “{{ $item['descricao'] }}” de todos os meses, inclusive os passados?" class="rounded-md px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50" aria-label="Excluir {{ $item['descricao'] }}">Excluir</button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
