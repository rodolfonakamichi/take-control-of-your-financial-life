<?php

namespace App\Domain\Lancamento;

use App\Domain\Dinheiro;

/**
 * Uma despesa de um mês (linha da seção "Financeiro" da planilha).
 */
final readonly class Despesa
{
    use ValidaLancamento;

    public string $descricao;

    public function __construct(string $descricao, public Dinheiro $valor)
    {
        $this->descricao = self::validar($descricao, $valor);
    }
}
