<?php

namespace App\Domain\Lancamento;

/**
 * As três linhas de "Recebidos" da planilha.
 */
enum CategoriaReceita: string
{
    case Salario = 'salario';
    case Outros = 'outros';
    case Comissao = 'comissao';

    public function rotulo(): string
    {
        return match ($this) {
            self::Salario => 'Salário',
            self::Outros => 'Outros',
            self::Comissao => 'Comissão',
        };
    }
}
