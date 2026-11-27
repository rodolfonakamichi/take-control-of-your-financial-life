<?php

namespace App\Application\Mes;

use App\Domain\Guardado\MovimentoGuardado;

final readonly class ItemMovimento
{
    public function __construct(public int $id, public MovimentoGuardado $movimento) {}
}
