<?php

namespace App\Application\Ano;

use App\Application\Mes\MesMontado;
use App\Application\Mes\MontarMes;
use App\Domain\Competencia;
use App\Domain\Resumo\ResumoAnual;

/**
 * RF-07: monta os 12 meses com a mesma MontarMes da tela do mês (os números são os mesmos)
 * e soma os totais do ano (RD-08).
 */
final class MontarAno
{
    public function __construct(private MontarMes $montarMes) {}

    public function executar(int $ano): AnoMontado
    {
        $meses = [];
        foreach (Competencia::mesesDoAno($ano) as $competencia) {
            $meses[$competencia->mes] = $this->montarMes->executar($competencia);
        }

        return new AnoMontado(
            $ano,
            $meses,
            ResumoAnual::de($ano, array_map(fn (MesMontado $m) => $m->resumo, $meses)),
            $meses[12]->totalGuardado,
        );
    }
}
