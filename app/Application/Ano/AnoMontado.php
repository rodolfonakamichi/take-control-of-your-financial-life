<?php

namespace App\Application\Ano;

use App\Application\Mes\MesMontado;
use App\Domain\Dinheiro;
use App\Domain\Resumo\ResumoAnual;

/**
 * O ano pronto para exibir (RF-07): os 12 meses e os totais anuais.
 */
final readonly class AnoMontado
{
    /**
     * @param  array<int, MesMontado>  $meses  chave 1..12
     */
    public function __construct(
        public int $ano,
        public array $meses,
        public ResumoAnual $resumo,
        public Dinheiro $totalGuardadoNoFim,
    ) {}
}
