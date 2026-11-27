<?php

namespace App\Application\Mes;

enum OrigemDespesa: string
{
    case Avulso = 'avulso';
    case Recorrencia = 'recorrencia';
    case Parcelamento = 'parcelamento';
}
