<?php

namespace App\Application\Mes;

use App\Domain\Dinheiro;
use App\Domain\Lancamento\Despesa;

/**
 * Uma linha de despesa na tela do mês, venha de onde vier.
 */
final readonly class ItemDespesa
{
    public function __construct(
        public OrigemDespesa $origem,
        public string $descricao,
        public Dinheiro $valor,
        public ?int $lancamentoId = null,
        public ?int $recorrenciaId = null,
        public ?int $parcelamentoId = null,
        public ?int $parcelaNumero = null,
        public ?int $totalParcelas = null,
        public bool $valorEditadoNoMes = false,
        public bool $paga = false,
    ) {}

    public function paraDespesa(): Despesa
    {
        return new Despesa($this->descricao, $this->valor);
    }
}
