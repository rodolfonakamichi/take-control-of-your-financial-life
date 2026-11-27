@php
    $campo = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none';
    $rotuloSituacao = ['andamento' => null, 'futuro' => 'começa depois', 'concluido' => 'concluído', 'cancelado' => 'cancelado'];
@endphp
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <h1 class="text-lg font-semibold">Parcelamentos</h1>
        <button type="button" wire:click="novo" class="rounded-lg bg-teal-700 px-3 py-1.5 text-sm font-medium text-white hover:bg-teal-800">+ Novo</button>
    </div>

    <p class="text-sm text-slate-500">As parcelas entram sozinhas, uma por mês, numeradas como “Viagem (1/6)”.</p>

    {{-- CA-05.5: confirmação explícita antes de excluir tudo --}}
    @if ($emConfirmacao)
        <section role="alertdialog" aria-labelledby="titulo-exclusao" aria-describedby="texto-exclusao" class="rounded-xl bg-red-50 p-4 ring-2 ring-red-300">
            <h2 id="titulo-exclusao" class="font-semibold text-red-800">Excluir “{{ $emConfirmacao->descricao }}” por completo?</h2>
            <p id="texto-exclusao" class="mt-1 text-sm text-red-800">
                Todas as {{ count($emConfirmacao->parcelas) }} parcelas serão apagadas, <strong>inclusive as de meses passados</strong>,
                e os totais desses meses vão mudar. Isso não pode ser desfeito.
                Se a compra só deixou de existir daqui para frente, use “Cancelar parcelamento”.
            </p>
            <div class="mt-3 flex gap-2">
                <button type="button" wire:click="confirmarExclusao" class="rounded-lg bg-red-700 px-4 py-2 text-sm font-medium text-white hover:bg-red-800">Excluir tudo</button>
                <button type="button" wire:click="desistirDaExclusao" class="rounded-lg px-4 py-2 text-sm font-medium text-slate-700 ring-1 ring-slate-300 hover:bg-white">Manter</button>
            </div>
        </section>
    @endif

    @if ($acao)
        <section aria-labelledby="titulo-formulario" class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-teal-200">
            <h2 id="titulo-formulario" class="mb-3 font-semibold">
                @switch($acao)
                    @case('novo') Novo parcelamento @break
                    @case('valor') Novo valor da parcela de {{ $selecionado?->descricao }} @break
                    @case('renomear') Renomear {{ $selecionado?->descricao }} @break
                @endswitch
            </h2>

            <form wire:submit="salvar" class="space-y-3">
                @if (in_array($acao, ['novo', 'renomear']))
                    <div>
                        <label for="descricao" class="mb-1 block text-sm font-medium">Descrição</label>
                        <input id="descricao" type="text" wire:model="descricao" maxlength="255" autocomplete="off" autofocus placeholder="Ex.: Viagem" class="{{ $campo }}">
                        <p class="mt-1 text-xs text-slate-500">Sem a numeração: o “(1/6)” é colocado automaticamente.</p>
                        @error('descricao') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($acao === 'novo')
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label for="valor" class="mb-1 block text-sm font-medium">Valor da parcela (R$)</label>
                            <input id="valor" type="text" inputmode="decimal" wire:model="valor" autocomplete="off" placeholder="0,00" class="{{ $campo }} tabular-nums">
                            @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                        <div>
                            <label for="parcelas" class="mb-1 block text-sm font-medium">Parcelas</label>
                            <input id="parcelas" type="number" inputmode="numeric" min="1" max="999" wire:model="parcelas" placeholder="Ex.: 6" class="{{ $campo }} tabular-nums">
                            @error('parcelas') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="primeira" class="mb-1 block text-sm font-medium">Mês da primeira parcela</label>
                        <input id="primeira" type="month" wire:model="primeira" class="{{ $campo }}">
                        @error('primeira') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                @endif

                @if ($acao === 'valor')
                    <div>
                        <label for="valor" class="mb-1 block text-sm font-medium">Novo valor da parcela (R$)</label>
                        <input id="valor" type="text" inputmode="decimal" wire:model="valor" autocomplete="off" class="{{ $campo }} tabular-nums">
                        @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                        <p class="mt-1 text-xs text-slate-500">Vale para as parcelas de {{ $mesAtual->formatar() }} em diante que ainda não foram pagas. As anteriores não mudam.</p>
                    </div>
                @endif

                <div class="flex gap-2 pt-1">
                    <button type="submit" class="flex-1 rounded-lg bg-teal-700 px-4 py-2.5 font-medium text-white hover:bg-teal-800">Salvar</button>
                    <button type="button" wire:click="cancelarFormulario" class="rounded-lg px-4 py-2.5 font-medium text-slate-600 ring-1 ring-slate-300 hover:bg-slate-50">Cancelar</button>
                </div>
            </form>
        </section>
    @endif

    @if ($itens === [])
        <p class="rounded-xl bg-white p-4 text-sm text-slate-500 ring-1 ring-slate-200">Nenhum parcelamento cadastrado.</p>
    @else
        <ul class="divide-y divide-slate-100 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
            @foreach ($itens as $item)
                <li wire:key="parcelamento-{{ $item['id'] }}" @class(['px-4 py-3', 'opacity-60' => $item['ordem'] === 1])>
                    <div class="flex items-start gap-3">
                        <div class="min-w-0 flex-1">
                            <p class="truncate text-sm font-medium">
                                {{ $item['descricao'] }}
                                @if ($rotuloSituacao[$item['situacao']])
                                    <span class="ml-1 rounded bg-slate-100 px-1.5 py-0.5 text-xs font-normal text-slate-500">{{ $rotuloSituacao[$item['situacao']] }}</span>
                                @endif
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $item['totalParcelas'] }}× de {{ $item['valorParcela']->formatar() }} · {{ $item['primeira']->formatar() }} a {{ $item['ultima']->formatar() }}
                            </p>
                            @if ($item['parcelaAtual'])
                                <p class="text-xs text-teal-700">Este mês: parcela {{ $item['parcelaAtual'] }}/{{ $item['totalParcelas'] }}</p>
                            @endif
                            @if ($item['situacao'] === 'cancelado')
                                <p class="text-xs text-slate-500">Cancelado em {{ $item['canceladoEm']->format('d/m/Y') }}</p>
                            @endif
                        </div>
                        @if ($item['quantidadeAVencer'] > 0)
                            <div class="text-right">
                                <p class="text-sm font-medium tabular-nums">{{ $item['aVencer']->formatar() }}</p>
                                <p class="text-xs text-slate-500">{{ $item['quantidadeAVencer'] }} {{ $item['quantidadeAVencer'] === 1 ? 'parcela' : 'parcelas' }} a vencer</p>
                            </div>
                        @endif
                    </div>
                    <div class="mt-2 flex flex-wrap gap-1">
                        @if ($item['situacao'] !== 'cancelado')
                            <button type="button" wire:click="alterarValor({{ $item['id'] }})" class="rounded-md px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50" aria-label="Mudar o valor da parcela de {{ $item['descricao'] }}">Mudar valor</button>
                        @endif
                        <button type="button" wire:click="renomear({{ $item['id'] }})" class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" aria-label="Renomear {{ $item['descricao'] }}">Renomear</button>
                        @if (in_array($item['situacao'], ['andamento', 'futuro'], true))
                            <button type="button" wire:click="cancelarParcelamento({{ $item['id'] }})" wire:confirm="Cancelar “{{ $item['descricao'] }}”? Somem as parcelas de {{ $mesAtual->formatar() }} em diante ainda não pagas; as anteriores ficam." class="rounded-md px-2 py-1 text-xs font-medium text-slate-600 hover:bg-slate-100" aria-label="Cancelar o parcelamento {{ $item['descricao'] }}">Cancelar parcelamento</button>
                        @endif
                        <button type="button" wire:click="excluir({{ $item['id'] }})" class="rounded-md px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50" aria-label="Excluir {{ $item['descricao'] }} por completo">Excluir tudo</button>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
