<div class="space-y-6">
    <h1 class="text-lg font-semibold">Guardado inicial</h1>

    <div class="rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <p class="text-xs text-slate-500">Total guardado hoje</p>
        <p id="total-guardado-atual" class="text-2xl font-semibold tabular-nums">{{ $totalAtual->formatar() }}</p>
        <p class="mt-1 text-xs text-slate-500">Poupança e investimentos: o guardado inicial mais tudo o que foi guardado e retirado nos meses.</p>
    </div>

    <form wire:submit="salvar" class="max-w-sm space-y-3 rounded-xl bg-white p-4 shadow-sm ring-1 ring-slate-200">
        <div>
            <label for="valor" class="mb-1 block text-sm font-medium">Quanto você já tinha guardado antes de começar a usar o sistema (R$)</label>
            <input id="valor" type="text" inputmode="decimal" wire:model="valor" autocomplete="off" placeholder="0,00"
                   class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-base tabular-nums focus:border-teal-600 focus:ring-2 focus:ring-teal-600/20 focus:outline-none">
            @error('valor') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        @if ($salvo)
            <p class="rounded-lg bg-teal-50 px-3 py-2 text-sm text-teal-800" role="status">Guardado inicial atualizado.</p>
        @endif

        <button type="submit" class="w-full rounded-lg bg-teal-700 px-4 py-2.5 font-medium text-white hover:bg-teal-800">Salvar</button>
    </form>
</div>
