<x-layouts.app :titulo="$mes->nomeDoMes().' de '.$mes->ano">
    <x-nav.seletor-mes :mes="$mes" :mes-atual="$mesAtual" />

    <livewire:tela-mes :competencia="(string) $mes" :novo="$novo" :key="(string) $mes" />
</x-layouts.app>
