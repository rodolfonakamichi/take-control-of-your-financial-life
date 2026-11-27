<?php

namespace App\Domain;

use InvalidArgumentException;

/**
 * Valor monetário em reais, guardado como inteiro de centavos (RNF-01).
 * Pode ser negativo (ex.: Restante, Guardado no mês); a obrigação de ser
 * positivo é de quem usa (ex.: valor de lançamento — RV-01).
 */
final readonly class Dinheiro
{
    private function __construct(public int $centavos) {}

    public static function centavos(int $centavos): self
    {
        return new self($centavos);
    }

    public static function zero(): self
    {
        return new self(0);
    }

    /**
     * Aceita o formato digitado pelo usuário ("1.200,00", "3.000", "R$ 85", "-20,5")
     * e o formato decimal com ponto ("1200.00", vindo da planilha).
     * Sem vírgula, um ponto seguido de exatamente 3 dígitos é milhar; de 1 ou 2, é decimal.
     */
    public static function deTexto(string $texto): self
    {
        $limpo = trim(str_replace(['R$', ' ', "\u{00A0}"], '', $texto));

        if (str_contains($limpo, ',')) {
            // "1.234,56": ponto é milhar, vírgula é decimal.
            $limpo = str_replace(['.', ','], ['', '.'], $limpo);
        } elseif (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $limpo)) {
            // "3.000" ou "1.234.567": sem vírgula, pontos seguidos de 3 dígitos são milhar.
            $limpo = str_replace('.', '', $limpo);
        }

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $limpo, $m)) {
            throw new InvalidArgumentException("Valor monetário inválido: \"{$texto}\".");
        }

        $centavos = (int) $m[2] * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return new self($m[1] === '-' ? -$centavos : $centavos);
    }

    /**
     * Para números vindos de planilha (float). Arredonda para o centavo mais próximo.
     */
    public static function deDecimal(float $valor): self
    {
        return new self((int) round($valor * 100));
    }

    /**
     * @param  iterable<self>  $valores
     */
    public static function somarTodos(iterable $valores): self
    {
        $total = 0;

        foreach ($valores as $valor) {
            $total += $valor->centavos;
        }

        return new self($total);
    }

    public function somar(self $outro): self
    {
        return new self($this->centavos + $outro->centavos);
    }

    public function subtrair(self $outro): self
    {
        return new self($this->centavos - $outro->centavos);
    }

    public function negar(): self
    {
        return new self(-$this->centavos);
    }

    /**
     * Divide em N parcelas iguais; os centavos que sobram vão para a primeira (RD-14).
     *
     * @return list<self>
     */
    public function dividirEmParcelas(int $quantidade): array
    {
        if ($quantidade < 1) {
            throw new InvalidArgumentException('A quantidade de parcelas deve ser pelo menos 1.');
        }

        if ($this->centavos < 0) {
            throw new InvalidArgumentException('Não é possível parcelar um valor negativo.');
        }

        $base = intdiv($this->centavos, $quantidade);
        $resto = $this->centavos - $base * $quantidade;

        $parcelas = array_fill(0, $quantidade, new self($base));
        $parcelas[0] = new self($base + $resto);

        return $parcelas;
    }

    public function ehZero(): bool
    {
        return $this->centavos === 0;
    }

    public function ehPositivo(): bool
    {
        return $this->centavos > 0;
    }

    public function ehNegativo(): bool
    {
        return $this->centavos < 0;
    }

    public function igual(self $outro): bool
    {
        return $this->centavos === $outro->centavos;
    }

    public function maiorQue(self $outro): bool
    {
        return $this->centavos > $outro->centavos;
    }

    public function menorQue(self $outro): bool
    {
        return $this->centavos < $outro->centavos;
    }

    /**
     * "R$ 1.234,56" / "-R$ 1.234,56" (RNF-02).
     */
    public function formatar(): string
    {
        $absoluto = abs($this->centavos);
        $reais = number_format(intdiv($absoluto, 100), 0, '', '.');
        $centavos = str_pad((string) ($absoluto % 100), 2, '0', STR_PAD_LEFT);

        return ($this->centavos < 0 ? '-' : '')."R$ {$reais},{$centavos}";
    }

    public function __toString(): string
    {
        return $this->formatar();
    }
}
