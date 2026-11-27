@props(['rotulo', 'valor', 'destaque' => false, 'id'])
{{-- Um total do resumo do mês: só leitura (CA-03.2). O destaque (Restante) fica vermelho quando negativo (CA-03.3). --}}
@php($negativo = $valor->ehNegativo())
<div @class([
    'rounded-xl p-3 shadow-sm',
    'bg-white ring-1 ring-slate-200' => ! $destaque,
    'bg-white ring-2 ring-teal-200' => $destaque && ! $negativo,
    'bg-red-50 ring-2 ring-red-300' => $destaque && $negativo,
]) @if ($destaque && $negativo) data-negativo @endif>
    <dt class="text-xs text-slate-500">{{ $rotulo }}</dt>
    <dd id="{{ $id }}" @class([
        'mt-0.5 font-semibold tabular-nums',
        'text-lg' => $destaque,
        'text-red-700' => $negativo,
    ])>{{ $valor->formatar() }}</dd>
</div>
