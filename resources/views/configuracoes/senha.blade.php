<x-layouts.app titulo="Trocar senha">
    <h1 class="mb-6 text-xl font-semibold">Trocar senha</h1>

    @if (session('status'))
        <p class="mb-4 rounded-lg bg-teal-50 p-3 text-sm text-teal-800" role="status">{{ session('status') }}</p>
    @endif

    <form method="POST" action="{{ route('configuracoes.senha.update') }}" class="max-w-sm space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
        @csrf
        @method('PUT')

        @foreach ([
            ['senha_atual', 'Senha atual', 'current-password'],
            ['nova_senha', 'Nova senha', 'new-password'],
            ['nova_senha_confirmation', 'Repita a nova senha', 'new-password'],
        ] as [$campo, $rotulo, $autocomplete])
            <div>
                <label for="{{ $campo }}" class="mb-1 block text-sm font-medium">{{ $rotulo }}</label>
                <input id="{{ $campo }}" name="{{ $campo }}" type="password" required autocomplete="{{ $autocomplete }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none">
                @error($campo)
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
        @endforeach

        <p class="text-xs text-slate-500">Depois da troca, os outros aparelhos marcados para lembrar vão pedir a senha de novo.</p>

        <button type="submit" class="w-full rounded-lg bg-teal-700 px-4 py-2.5 font-medium text-white hover:bg-teal-800">Salvar nova senha</button>
    </form>
</x-layouts.app>
