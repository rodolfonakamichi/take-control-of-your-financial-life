<?php

namespace App\Infra\Tempo;

use App\Domain\Competencia;
use DateTimeImmutable;
use DateTimeZone;

final class RelogioDoSistema implements Relogio
{
    public function agora(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone(config('app.timezone')));
    }

    public function mesAtual(): Competencia
    {
        return Competencia::deData($this->agora());
    }
}
