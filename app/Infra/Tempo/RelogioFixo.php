<?php

namespace App\Infra\Tempo;

use App\Domain\Competencia;
use DateTimeImmutable;

/**
 * Relógio parado numa data — para testes.
 */
final class RelogioFixo implements Relogio
{
    public function __construct(private DateTimeImmutable $agora) {}

    public static function em(string $data): self
    {
        return new self(new DateTimeImmutable($data));
    }

    public function agora(): DateTimeImmutable
    {
        return $this->agora;
    }

    public function mesAtual(): Competencia
    {
        return Competencia::deData($this->agora);
    }
}
