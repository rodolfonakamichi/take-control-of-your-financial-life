<?php

namespace App\Domain\Parcelamento;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Lancamento\Despesa;

/**
 * A parcela k de N de um parcelamento, num mês.
 */
final readonly class Parcela
{
    public function __construct(
        public int $numero,
        public int $total,
        public Competencia $competencia,
        public Dinheiro $valor,
        public bool $paga = false,
    ) {}

    /**
     * RD-16: só parcelas do mês atual em diante e ainda não pagas podem ser alteradas ou canceladas.
     */
    public function podeSerAlterada(Competencia $mesAtual): bool
    {
        return ! $this->paga && ! $this->competencia->antesDe($mesAtual);
    }

    public function comValor(Dinheiro $valor): self
    {
        return new self($this->numero, $this->total, $this->competencia, $valor, $this->paga);
    }

    public function comPagamento(bool $paga): self
    {
        return new self($this->numero, $this->total, $this->competencia, $this->valor, $paga);
    }

    /**
     * "Viagem (1/6)" — a numeração é sempre gerada pelo sistema (CA-05.2).
     */
    public function descricaoCom(string $descricao): string
    {
        return "{$descricao} ({$this->numero}/{$this->total})";
    }

    public function paraDespesa(string $descricao): Despesa
    {
        return new Despesa($this->descricaoCom($descricao), $this->valor);
    }
}
