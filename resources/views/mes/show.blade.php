<x-layouts.app :titulo="$mes->nomeDoMes().' de '.$mes->ano">
    <x-nav.seletor-mes :mes="$mes" :mes-atual="$mesAtual" />

    <section class="mt-6 rounded-xl border border-dashed border-slate-300 p-6 text-center text-sm text-slate-500">
        O resumo e os lançamentos do mês chegam na próxima tarefa (T11).
    </section>
</x-layouts.app>
