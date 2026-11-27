{{--
    Layout das páginas autenticadas, mobile-first (RNF-07, CA-16.4).
    Celular: barra inferior fixa com Início, Mês, +, Ano, Mais.
    Telas largas (md+): navegação no topo e + flutuante no canto.
--}}
@props(['titulo' => null])
@php
    $itens = [
        ['inicio', route('inicio'), 'inicio', 'Início'],
        ['mes', route('mes'), 'mes', 'Mês'],
        ['ano', route('ano'), 'ano', 'Ano'],
        ['mais', route('mais'), 'mais', 'Mais'],
    ];
    $ativo = fn (string $rota) => request()->routeIs($rota) || ($rota === 'mais' && request()->routeIs('configuracoes.*'));
@endphp
<x-layouts.base :titulo="$titulo">
    <header class="sticky top-0 z-20 border-b border-slate-200 bg-white/95 backdrop-blur">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
            <a href="{{ route('inicio') }}" class="font-semibold text-teal-700">{{ config('app.name') }}</a>

            <nav aria-label="Navegação principal" class="hidden items-center gap-1 md:flex">
                @foreach ($itens as [$rota, $href, $icone, $rotulo])
                    <a href="{{ $href }}" @if ($ativo($rota)) aria-current="page" @endif
                       @class([
                           'rounded-lg px-3 py-1.5 text-sm font-medium',
                           'bg-teal-50 text-teal-800' => $ativo($rota),
                           'text-slate-600 hover:bg-slate-100' => ! $ativo($rota),
                       ])>{{ $rotulo }}</a>
                @endforeach
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 pt-4 pb-28 md:pb-12">
        {{ $slot }}
    </main>

    {{-- Barra inferior (celular) --}}
    <nav aria-label="Navegação principal" class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-200 bg-white pb-[env(safe-area-inset-bottom)] md:hidden">
        <div class="mx-auto grid max-w-md grid-cols-5 items-end">
            @foreach (array_slice($itens, 0, 2) as [$rota, $href, $icone, $rotulo])
                <x-nav.item :href="$href" :icone="$icone" :rotulo="$rotulo" :ativo="$ativo($rota)" />
            @endforeach

            <div class="flex justify-center">
                <x-nav.novo class="-mt-6" />
            </div>

            @foreach (array_slice($itens, 2) as [$rota, $href, $icone, $rotulo])
                <x-nav.item :href="$href" :icone="$icone" :rotulo="$rotulo" :ativo="$ativo($rota)" />
            @endforeach
        </div>
    </nav>

    {{-- + flutuante (telas largas) --}}
    <div class="fixed right-6 bottom-6 z-30 hidden md:block">
        <x-nav.novo flutuante />
    </div>
</x-layouts.base>
