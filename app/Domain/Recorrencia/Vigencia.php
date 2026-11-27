<?php

namespace App\Domain\Recorrencia;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * Valor padrão de uma recorrência a partir de um mês.
 */
final readonly class Vigencia
{
    public function __construct(public Competencia $desde, public Dinheiro $valor)
    {
        if (! $valor->ehPositivo()) {
            throw new InvalidArgumentException('O valor da recorrência deve ser maior que zero.');
        }
    }
}
