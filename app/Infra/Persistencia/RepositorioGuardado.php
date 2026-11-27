<?php

namespace App\Infra\Persistencia;

use App\Domain\Dinheiro;
use App\Domain\Guardado\LivroGuardado;
use App\Domain\Guardado\MovimentoGuardado;
use App\Domain\Guardado\TipoMovimento;
use App\Models\MovimentoGuardado as MovimentoModel;

/**
 * Monta o LivroGuardado a partir de movimentos_guardado + guardado inicial das configurações.
 */
final class RepositorioGuardado
{
    public function __construct(private Configuracoes $configuracoes) {}

    public function livro(): LivroGuardado
    {
        $movimentos = MovimentoModel::query()
            ->orderBy('competencia')
            ->orderBy('id')
            ->get()
            ->map(fn (MovimentoModel $m) => [$m->competencia, self::paraDominio($m)]);

        return LivroGuardado::de($this->configuracoes->guardadoInicial(), $movimentos);
    }

    public static function paraDominio(MovimentoModel $model): MovimentoGuardado
    {
        return new MovimentoGuardado(
            TipoMovimento::from($model->tipo),
            Dinheiro::centavos($model->valor_centavos),
            $model->descricao,
        );
    }
}
