<?php

namespace App\Domain\Guardado;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use DomainException;

/**
 * RV-07: a operação deixaria o total guardado negativo em algum mês.
 */
final class GuardadoFicariaNegativo extends DomainException
{
    public function __construct(public readonly Competencia $competencia, public readonly Dinheiro $total)
    {
        parent::__construct(sprintf(
            'O total guardado ficaria negativo em %s (%s).',
            $competencia->formatar(),
            $total->formatar(),
        ));
    }
}
