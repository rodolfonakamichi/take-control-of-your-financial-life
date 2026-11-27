<?php

namespace App\Domain\Resumo;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Lancamento\CategoriaReceita;

/**
 * Os totais de um mês, equivalentes ao rodapé de cada bloco da planilha.
 * Só leitura: é sempre calculado, nunca digitado (RV-02).
 */
final readonly class ResumoMensal
{
    /**
     * @param  array<string, Dinheiro>  $recebidosPorCategoria  chave = CategoriaReceita::value
     */
    public function __construct(
        public Competencia $competencia,
        public Dinheiro $contasAPagar,
        public Dinheiro $recebidos,
        public array $recebidosPorCategoria,
        public Dinheiro $guardadoNoMes,
        public Dinheiro $restante,
    ) {}

    public function recebidosDe(CategoriaReceita $categoria): Dinheiro
    {
        return $this->recebidosPorCategoria[$categoria->value] ?? Dinheiro::zero();
    }

    public function restanteNegativo(): bool
    {
        return $this->restante->ehNegativo();
    }
}
