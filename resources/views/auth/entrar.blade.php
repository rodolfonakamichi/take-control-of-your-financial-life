<x-layouts.base titulo="Entrar">
    <main class="flex min-h-full items-center justify-center px-4 py-12">
        <div class="w-full max-w-sm">
            <h1 class="mb-1 text-center text-2xl font-semibold text-teal-700">{{ config('app.name') }}</h1>
            <p class="mb-8 text-center text-sm text-slate-500">Digite a senha para continuar</p>

            @unless ($senhaDefinida)
                <div class="mb-6 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm text-amber-900" role="alert">
                    A senha ainda não foi definida. No computador onde o sistema roda, execute:
                    <code class="mt-2 block rounded bg-amber-100 px-2 py-1 font-mono text-xs">docker compose run --rm app php artisan financas:definir-senha</code>
                </div>
            @endunless

            <form method="POST" action="{{ route('login.store') }}" class="space-y-4 rounded-xl bg-white p-6 shadow-sm ring-1 ring-slate-200">
                @csrf

                <div>
                    <label for="senha" class="mb-1 block text-sm font-medium">Senha</label>
                    <input id="senha" name="senha" type="password" required autofocus autocomplete="current-password"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-base focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none"
                           aria-describedby="senha-erro" @error('senha') aria-invalid="true" @enderror>
                    @error('senha')
                        <p id="senha-erro" class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="lembrar" value="1" class="size-4 rounded border-slate-300 text-teal-600">
                    Lembrar neste aparelho por 30 dias
                </label>

                <button type="submit" class="w-full rounded-lg bg-teal-700 px-4 py-2.5 font-medium text-white hover:bg-teal-800 focus:ring-2 focus:ring-teal-600/40 focus:outline-none">
                    Entrar
                </button>
            </form>
        </div>
    </main>
</x-layouts.base>
