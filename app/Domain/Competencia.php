<?php

namespace App\Domain;

use DateTimeImmutable;
use DateTimeInterface;
use InvalidArgumentException;

/**
 * Um mês de um ano: a unidade em que tudo é organizado (equivale a um bloco da planilha).
 * Representação textual e de banco: "AAAA-MM".
 */
final readonly class Competencia
{
    private const NOMES = [
        1 => 'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    private function __construct(public int $ano, public int $mes) {}

    public static function de(int $ano, int $mes): self
    {
        if ($mes < 1 || $mes > 12) {
            throw new InvalidArgumentException("Mês inválido: {$mes}.");
        }

        if ($ano < 1900 || $ano > 9999) {
            throw new InvalidArgumentException("Ano inválido: {$ano}.");
        }

        return new self($ano, $mes);
    }

    public static function deTexto(string $texto): self
    {
        if (! preg_match('/^(\d{4})-(\d{2})$/', $texto, $m)) {
            throw new InvalidArgumentException("Competência inválida: \"{$texto}\". Use AAAA-MM.");
        }

        return self::de((int) $m[1], (int) $m[2]);
    }

    public static function deData(DateTimeInterface $data): self
    {
        return self::de((int) $data->format('Y'), (int) $data->format('n'));
    }

    /**
     * @return list<self> os 12 meses do ano, de janeiro a dezembro
     */
    public static function mesesDoAno(int $ano): array
    {
        return array_map(fn (int $mes) => self::de($ano, $mes), range(1, 12));
    }

    public function somarMeses(int $meses): self
    {
        $indice = $this->indice() + $meses;

        return self::de(intdiv($indice, 12), $indice % 12 + 1);
    }

    public function anterior(): self
    {
        return $this->somarMeses(-1);
    }

    public function proxima(): self
    {
        return $this->somarMeses(1);
    }

    /**
     * Quantos meses faltam de $this até $outra (negativo se $outra for anterior).
     */
    public function mesesAte(self $outra): int
    {
        return $outra->indice() - $this->indice();
    }

    public function comparar(self $outra): int
    {
        return $this->indice() <=> $outra->indice();
    }

    public function igual(self $outra): bool
    {
        return $this->comparar($outra) === 0;
    }

    public function antesDe(self $outra): bool
    {
        return $this->comparar($outra) < 0;
    }

    public function depoisDe(self $outra): bool
    {
        return $this->comparar($outra) > 0;
    }

    public function entre(self $inicio, ?self $fim): bool
    {
        return ! $this->antesDe($inicio) && ($fim === null || ! $this->depoisDe($fim));
    }

    public function diasNoMes(): int
    {
        return (int) (new DateTimeImmutable(sprintf('%04d-%02d-01', $this->ano, $this->mes)))->format('t');
    }

    /**
     * Data do dia $dia neste mês; um dia que não existe no mês vira o último dia (RD-11).
     */
    public function dia(int $dia): DateTimeImmutable
    {
        if ($dia < 1 || $dia > 31) {
            throw new InvalidArgumentException("Dia inválido: {$dia}.");
        }

        $diaReal = min($dia, $this->diasNoMes());

        return new DateTimeImmutable(sprintf('%04d-%02d-%02d', $this->ano, $this->mes, $diaReal));
    }

    public function nomeDoMes(): string
    {
        return self::NOMES[$this->mes];
    }

    /**
     * "03/2026" (RNF-02).
     */
    public function formatar(): string
    {
        return sprintf('%02d/%04d', $this->mes, $this->ano);
    }

    /**
     * "2026-03": formato de banco e de URL.
     */
    public function __toString(): string
    {
        return sprintf('%04d-%02d', $this->ano, $this->mes);
    }

    private function indice(): int
    {
        return $this->ano * 12 + ($this->mes - 1);
    }
}
