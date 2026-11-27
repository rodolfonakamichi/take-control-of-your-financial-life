<x-layouts.app titulo="Mais">
    <h1 class="mb-4 text-lg font-semibold">Mais</h1>

    {{-- O guardado entra aqui na T14. --}}
    <ul class="divide-y divide-slate-200 overflow-hidden rounded-xl bg-white shadow-sm ring-1 ring-slate-200">
        <li>
            <a href="{{ route('recorrencias') }}" class="block px-4 py-3.5 text-sm hover:bg-slate-50">Despesas fixas</a>
        </li>
        <li>
            <a href="{{ route('parcelamentos') }}" class="block px-4 py-3.5 text-sm hover:bg-slate-50">Parcelamentos</a>
        </li>
        <li>
            <a href="{{ route('configuracoes.senha') }}" class="block px-4 py-3.5 text-sm hover:bg-slate-50">Trocar senha</a>
        </li>
        <li>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="block w-full px-4 py-3.5 text-left text-sm text-red-700 hover:bg-slate-50">Sair</button>
            </form>
        </li>
    </ul>
</x-layouts.app>
