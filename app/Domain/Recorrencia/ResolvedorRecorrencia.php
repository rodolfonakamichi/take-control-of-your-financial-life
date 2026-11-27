<?php

namespace App\Domain\Recorrencia;

use App\Domain\Competencia;

/**
 * Monta as despesas que as recorrências geram num mês (RD-07).
 */
final class ResolvedorRecorrencia
{
    /**
     * @param  iterable<Recorrencia>  $recorrencias
     * @return list<OcorrenciaRecorrencia> uma por recorrência ativa no mês
     */
    public function ocorrenciasDoMes(Competencia $competencia, iterable $recorrencias): array
    {
        $ocorrencias = [];

        foreach ($recorrencias as $recorrencia) {
            $ocorrencia = $recorrencia->ocorrenciaEm($competencia);

            if ($ocorrencia !== null) {
                $ocorrencias[] = $ocorrencia;
            }
        }

        return $ocorrencias;
    }
}
