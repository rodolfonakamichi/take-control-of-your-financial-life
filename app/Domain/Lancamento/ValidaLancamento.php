<?php

namespace App\Domain\Lancamento;

use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * RV-01: um lançamento precisa de descrição e de valor maior que zero.
 */
trait ValidaLancamento
{
    private static function validar(string $descricao, Dinheiro $valor): string
    {
        $descricao = trim($descricao);

        if ($descricao === '') {
            throw new InvalidArgumentException('Informe a descrição do lançamento.');
        }

        if (! $valor->ehPositivo()) {
            throw new InvalidArgumentException('O valor do lançamento deve ser maior que zero.');
        }

        return $descricao;
    }
}
