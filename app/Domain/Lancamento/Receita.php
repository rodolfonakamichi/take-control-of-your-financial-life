<?php

namespace App\Domain\Lancamento;

use App\Domain\Dinheiro;

/**
 * Uma receita de um mês (Salário, Outros ou Comissão).
 */
final readonly class Receita
{
    use ValidaLancamento;

    public string $descricao;

    public function __construct(
        string $descricao,
        public Dinheiro $valor,
        public CategoriaReceita $categoria = CategoriaReceita::Salario,
    ) {
        $this->descricao = self::validar($descricao, $valor);
    }
}
