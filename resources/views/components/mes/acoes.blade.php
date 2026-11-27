@props(['id', 'descricao'])
<div class="flex shrink-0 gap-1">
    <button type="button" wire:click="editar({{ $id }})" class="rounded-md px-2 py-1 text-xs font-medium text-teal-700 hover:bg-teal-50" aria-label="Editar {{ $descricao }}">Editar</button>
    <button type="button" wire:click="excluir({{ $id }})" wire:confirm="Excluir “{{ $descricao }}”?" class="rounded-md px-2 py-1 text-xs font-medium text-red-700 hover:bg-red-50" aria-label="Excluir {{ $descricao }}">Excluir</button>
</div>
