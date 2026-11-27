<?php

namespace App\Application\Mes;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Resumo\ResumoMensal;

/**
 * Um mês pronto para exibir: itens + resumo (design §2.2).
 */
final readonly class MesMontado
{
    /**
     * @param  list<ItemDespesa>  $despesas
     * @param  list<ItemReceita>  $receitas
     * @param  list<ItemMovimento>  $movimentos
     */
    public function __construct(
        public Competencia $competencia,
        public array $despesas,
        public array $receitas,
        public array $movimentos,
        public ResumoMensal $resumo,
        public Dinheiro $totalGuardado,
        public bool $ehProjecao,
    ) {}
}
