@props(['flutuante' => false])
{{--
    Botão + (CA-16.3): sempre visível, abre as ações de lançar.
    Usa <details>, sem JavaScript. Os formulários abrem na tela do mês pelo parâmetro ?novo=.
--}}
@php
    $acoes = [
        ['despesa', 'Despesa'],
        ['receita', 'Receita'],
        // 'Guardar ou retirar' volta na T14, com o formulário do guardado.
    ];
@endphp
<details {{ $attributes->class(['group relative']) }}>
    <summary aria-label="Novo lançamento"
             class="flex size-14 cursor-pointer list-none items-center justify-center rounded-full bg-teal-700 text-white shadow-lg ring-4 ring-slate-50 hover:bg-teal-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-700 [&::-webkit-details-marker]:hidden">
        <x-nav.icone nome="mais-sinal" class="size-7 transition-transform group-open:rotate-45" />
    </summary>
    <div @class([
        'absolute bottom-full mb-3 w-52 overflow-hidden rounded-xl bg-white shadow-xl ring-1 ring-slate-200',
        'left-1/2 -translate-x-1/2' => ! $flutuante,
        'right-0' => $flutuante,
    ])>
        <p class="px-4 pt-3 pb-1 text-xs font-medium tracking-wide text-slate-400 uppercase">Lançar</p>
        @foreach ($acoes as [$tipo, $rotulo])
            <a href="{{ route('mes', ['novo' => $tipo]) }}" class="block px-4 py-3 text-sm hover:bg-slate-50">{{ $rotulo }}</a>
        @endforeach
    </div>
</details>
