{{-- Layout das páginas autenticadas. A navegação completa (barra inferior, botão +) vem na T10. --}}
@props(['titulo' => null])
<x-layouts.base :titulo="$titulo">
    <header class="border-b border-slate-200 bg-white">
        <div class="mx-auto flex max-w-3xl items-center justify-between px-4 py-3">
            <a href="{{ route('inicio') }}" class="font-semibold text-teal-700">{{ config('app.name') }}</a>
            <nav class="flex items-center gap-4 text-sm">
                <a href="{{ route('configuracoes.senha') }}" class="text-slate-600 hover:text-slate-900">Senha</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="text-slate-600 hover:text-slate-900">Sair</button>
                </form>
            </nav>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-6">
        {{ $slot }}
    </main>
</x-layouts.base>
