<?php

namespace App\Domain\Parcelamento;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * RD-06: a parcela k (1 ≤ k ≤ N) cai em primeira + (k−1) meses.
 */
final class GeradorParcelas
{
    /**
     * @return list<Parcela>
     */
    public static function gerar(Competencia $primeira, int $quantidade, Dinheiro $valorParcela): array
    {
        if ($quantidade < 1) {
            throw new InvalidArgumentException('O parcelamento precisa de pelo menos 1 parcela.');
        }

        if (! $valorParcela->ehPositivo()) {
            throw new InvalidArgumentException('O valor da parcela deve ser maior que zero.');
        }

        $parcelas = [];
        for ($k = 1; $k <= $quantidade; $k++) {
            $parcelas[] = new Parcela($k, $quantidade, $primeira->somarMeses($k - 1), $valorParcela);
        }

        return $parcelas;
    }

    /**
     * As despesas que os parcelamentos geram num mês, com a descrição "Texto (k/N)".
     *
     * @param  iterable<Parcelamento>  $parcelamentos
     * @return list<array{parcelamento: Parcelamento, parcela: Parcela}>
     */
    public static function doMes(Competencia $competencia, iterable $parcelamentos): array
    {
        $doMes = [];

        foreach ($parcelamentos as $parcelamento) {
            $parcela = $parcelamento->parcelaEm($competencia);

            if ($parcela !== null) {
                $doMes[] = ['parcelamento' => $parcelamento, 'parcela' => $parcela];
            }
        }

        return $doMes;
    }
}
