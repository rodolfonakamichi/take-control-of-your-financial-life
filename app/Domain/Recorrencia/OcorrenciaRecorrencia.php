<?php

namespace App\Domain\Recorrencia;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Lancamento\Despesa;

/**
 * A despesa que uma recorrência gera num mês (calculada, não gravada — design §3.2).
 */
final readonly class OcorrenciaRecorrencia
{
    public function __construct(
        public ?int $recorrenciaId,
        public string $descricao,
        public Competencia $competencia,
        public Dinheiro $valor,
        public bool $valorEditadoNoMes,
    ) {}

    public function paraDespesa(): Despesa
    {
        return new Despesa($this->descricao, $this->valor);
    }
}
