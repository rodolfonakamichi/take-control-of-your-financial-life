<?php

namespace App\Application\Guardado;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Guardado\GuardadoFicariaNegativo;
use App\Domain\Guardado\LivroGuardado;
use App\Domain\Guardado\MovimentoGuardado;
use App\Domain\Guardado\TipoMovimento;
use App\Infra\Persistencia\Configuracoes;
use App\Infra\Persistencia\RepositorioGuardado;
use App\Models\MovimentoGuardado as MovimentoModel;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * RF-06: guardar, retirar e o guardado inicial.
 *
 * Toda mudança é validada no LivroGuardado antes de gravar (RV-07): se o total ficaria
 * negativo em algum mês, nada é gravado e GuardadoFicariaNegativo diz onde.
 */
final class GerenciarGuardado
{
    public function __construct(
        private RepositorioGuardado $repositorio,
        private Configuracoes $configuracoes,
    ) {}

    /**
     * CA-06.1
     *
     * @throws GuardadoFicariaNegativo|InvalidArgumentException
     */
    public function registrar(Competencia $competencia, TipoMovimento $tipo, Dinheiro $valor, ?string $descricao = null): MovimentoModel
    {
        $movimento = new MovimentoGuardado($tipo, $valor, self::limpar($descricao));

        return DB::transaction(function () use ($competencia, $movimento) {
            $this->repositorio->livro()->adicionar($competencia, $movimento);

            return MovimentoModel::query()->create([
                'competencia' => $competencia,
                'tipo' => $movimento->tipo->value,
                'valor_centavos' => $movimento->valor->centavos,
                'descricao' => $movimento->descricao,
            ]);
        });
    }

    /**
     * @throws GuardadoFicariaNegativo|InvalidArgumentException
     */
    public function editar(int $id, TipoMovimento $tipo, Dinheiro $valor, ?string $descricao = null): MovimentoModel
    {
        $novo = new MovimentoGuardado($tipo, $valor, self::limpar($descricao));

        return DB::transaction(function () use ($id, $novo) {
            $model = $this->carregar($id);

            $this->repositorio->livro()
                ->remover($model->competencia, RepositorioGuardado::paraDominio($model))
                ->adicionar($model->competencia, $novo);

            $model->fill([
                'tipo' => $novo->tipo->value,
                'valor_centavos' => $novo->valor->centavos,
                'descricao' => $novo->descricao,
            ])->save();

            return $model;
        });
    }

    /**
     * Excluir um "guardar" antigo também pode deixar meses seguintes negativos (RV-07).
     *
     * @throws GuardadoFicariaNegativo|InvalidArgumentException
     */
    public function excluir(int $id): void
    {
        DB::transaction(function () use ($id) {
            $model = $this->carregar($id);

            $this->repositorio->livro()->remover($model->competencia, RepositorioGuardado::paraDominio($model));

            $model->delete();
        });
    }

    /**
     * CA-06.6: o que já estava guardado antes do primeiro mês controlado.
     *
     * @throws GuardadoFicariaNegativo|InvalidArgumentException
     */
    public function definirGuardadoInicial(Dinheiro $valor): void
    {
        DB::transaction(function () use ($valor) {
            $this->repositorio->livro()->comGuardadoInicial($valor);
            $this->configuracoes->definirGuardadoInicial($valor);
        });
    }

    public function livro(): LivroGuardado
    {
        return $this->repositorio->livro();
    }

    private function carregar(int $id): MovimentoModel
    {
        return MovimentoModel::query()->find($id) ?? throw new InvalidArgumentException('Movimento não encontrado.');
    }

    private static function limpar(?string $descricao): ?string
    {
        $descricao = trim((string) $descricao);

        return $descricao === '' ? null : $descricao;
    }
}
