<?php

namespace App\Application\Mes;

use App\Domain\Dinheiro;
use App\Domain\Lancamento\CategoriaReceita;
use App\Domain\Lancamento\Receita;

final readonly class ItemReceita
{
    public function __construct(
        public int $lancamentoId,
        public string $descricao,
        public Dinheiro $valor,
        public CategoriaReceita $categoria,
    ) {}

    public function paraReceita(): Receita
    {
        return new Receita($this->descricao, $this->valor, $this->categoria);
    }
}
