<?php

namespace App\Domain\Guardado;

enum TipoMovimento: string
{
    case Guardar = 'guardar';
    case Retirar = 'retirar';
}
