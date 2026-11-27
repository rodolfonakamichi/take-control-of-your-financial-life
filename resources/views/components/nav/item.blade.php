@props(['href', 'ativo' => false, 'icone', 'rotulo'])
<a href="{{ $href }}" @if ($ativo) aria-current="page" @endif
   {{ $attributes->class([
       'flex flex-col items-center justify-center gap-0.5 py-2 text-xs font-medium',
       'text-teal-700' => $ativo,
       'text-slate-500 hover:text-slate-800' => ! $ativo,
   ]) }}>
    <x-nav.icone :nome="$icone" />
    <span>{{ $rotulo }}</span>
</a>
