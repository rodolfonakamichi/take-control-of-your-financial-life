<?php

namespace App\Infra\Tempo;

use App\Domain\Competencia;
use DateTimeImmutable;

/**
 * A data de hoje, injetável para que os testes controlem "o mês atual" (design §2).
 */
interface Relogio
{
    public function agora(): DateTimeImmutable;

    public function mesAtual(): Competencia;
}
