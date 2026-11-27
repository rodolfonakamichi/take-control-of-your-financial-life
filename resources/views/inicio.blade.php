{{-- Tela inicial provisória: o conteúdo real (total guardado, resumo do mês) vem na T17. --}}
<x-layouts.app titulo="Início">
    <h1 class="text-lg font-semibold">Início</h1>
    <p class="mt-2 text-sm text-slate-600">O resumo do mês e o total guardado aparecem aqui na T17.</p>
    <a href="{{ route('mes') }}" class="mt-4 inline-block text-sm font-medium text-teal-700 hover:underline">Abrir o mês atual →</a>
</x-layouts.app>
