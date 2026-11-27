<?php

namespace App\Application\Lancamentos;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Lancamento\CategoriaReceita;
use App\Domain\Lancamento\Despesa;
use App\Domain\Lancamento\Receita;
use App\Models\Lancamento;
use InvalidArgumentException;

/**
 * RF-01 e RF-02: lançar, editar e excluir despesas e receitas avulsas de um mês.
 *
 * A validação (RV-01) é a do domínio: Despesa e Receita recusam descrição vazia e valor ≤ 0.
 * Parcelas e recorrências não passam por aqui (têm telas próprias — T12 e T13).
 */
final class LancamentosAvulsos
{
    /**
     * @throws InvalidArgumentException
     */
    public function lancarDespesa(Competencia $competencia, string $descricao, Dinheiro $valor): Lancamento
    {
        $despesa = new Despesa($descricao, $valor);

        return Lancamento::query()->create([
            'tipo' => Lancamento::DESPESA,
            'origem' => Lancamento::AVULSO,
            'competencia' => $competencia,
            'descricao' => $despesa->descricao,
            'valor_centavos' => $despesa->valor->centavos,
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function lancarReceita(Competencia $competencia, string $descricao, Dinheiro $valor, CategoriaReceita $categoria): Lancamento
    {
        $receita = new Receita($descricao, $valor, $categoria);

        return Lancamento::query()->create([
            'tipo' => Lancamento::RECEITA,
            'origem' => Lancamento::AVULSO,
            'competencia' => $competencia,
            'descricao' => $receita->descricao,
            'valor_centavos' => $receita->valor->centavos,
            'categoria_receita' => $receita->categoria->value,
        ]);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function editar(int $id, string $descricao, Dinheiro $valor, ?CategoriaReceita $categoria = null): Lancamento
    {
        $lancamento = $this->avulso($id);

        if ($lancamento->tipo === Lancamento::RECEITA) {
            $receita = new Receita($descricao, $valor, $categoria ?? CategoriaReceita::from($lancamento->categoria_receita));
            $lancamento->fill([
                'descricao' => $receita->descricao,
                'valor_centavos' => $receita->valor->centavos,
                'categoria_receita' => $receita->categoria->value,
            ]);
        } else {
            $despesa = new Despesa($descricao, $valor);
            $lancamento->fill(['descricao' => $despesa->descricao, 'valor_centavos' => $despesa->valor->centavos]);
        }

        $lancamento->save();

        return $lancamento;
    }

    public function excluir(int $id): void
    {
        $this->avulso($id)->delete();
    }

    private function avulso(int $id): Lancamento
    {
        $lancamento = Lancamento::query()->find($id);

        if ($lancamento === null || $lancamento->origem !== Lancamento::AVULSO) {
            throw new InvalidArgumentException('Lançamento não encontrado.');
        }

        return $lancamento;
    }
}
