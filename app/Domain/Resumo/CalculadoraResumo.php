<?php

namespace App\Domain\Resumo;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Guardado\MovimentoGuardado;
use App\Domain\Lancamento\CategoriaReceita;
use App\Domain\Lancamento\Despesa;
use App\Domain\Lancamento\Receita;

/**
 * RD-01..RD-04: calcula o resumo de um único mês.
 *
 * O Restante é só do mês: o cálculo não recebe nada de meses anteriores (CA-03.4).
 */
final class CalculadoraResumo
{
    /**
     * @param  iterable<Despesa>  $despesas
     * @param  iterable<Receita>  $receitas
     * @param  iterable<MovimentoGuardado>  $movimentos
     */
    public function calcular(
        Competencia $competencia,
        iterable $despesas,
        iterable $receitas,
        iterable $movimentos = [],
    ): ResumoMensal {
        // RD-01
        $contasAPagar = Dinheiro::zero();
        foreach ($despesas as $despesa) {
            $contasAPagar = $contasAPagar->somar($despesa->valor);
        }

        // RD-02
        $porCategoria = [];
        foreach (CategoriaReceita::cases() as $categoria) {
            $porCategoria[$categoria->value] = Dinheiro::zero();
        }
        foreach ($receitas as $receita) {
            $chave = $receita->categoria->value;
            $porCategoria[$chave] = $porCategoria[$chave]->somar($receita->valor);
        }
        $recebidos = Dinheiro::somarTodos($porCategoria);

        // RD-03
        $guardadoNoMes = Dinheiro::zero();
        foreach ($movimentos as $movimento) {
            $guardadoNoMes = $guardadoNoMes->somar($movimento->efeito());
        }

        // RD-04
        $restante = $recebidos->subtrair($contasAPagar)->subtrair($guardadoNoMes);

        return new ResumoMensal($competencia, $contasAPagar, $recebidos, $porCategoria, $guardadoNoMes, $restante);
    }
}
