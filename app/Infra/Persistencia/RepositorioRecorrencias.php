<?php

namespace App\Infra\Persistencia;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Recorrencia\Recorrencia;
use App\Domain\Recorrencia\Vigencia;
use App\Models\Recorrencia as RecorrenciaModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recorrencia (domínio) <-> recorrencias + recorrencia_valores + recorrencia_ocorrencias.
 */
final class RepositorioRecorrencias
{
    /**
     * @return list<Recorrencia>
     */
    public function ativasEm(Competencia $competencia): array
    {
        $mes = (string) $competencia;

        return $this->carregar(
            RecorrenciaModel::query()
                ->where('inicio', '<=', $mes)
                ->where(fn (Builder $q) => $q->whereNull('fim')->orWhere('fim', '>=', $mes)),
        );
    }

    /**
     * @return list<Recorrencia>
     */
    public function todas(): array
    {
        return $this->carregar(RecorrenciaModel::query());
    }

    public function porId(int $id): ?Recorrencia
    {
        return $this->carregar(RecorrenciaModel::query()->whereKey($id))[0] ?? null;
    }

    /**
     * Grava a recorrência e devolve-a com o id.
     */
    public function salvar(Recorrencia $recorrencia): Recorrencia
    {
        return DB::transaction(function () use ($recorrencia) {
            $model = $recorrencia->id === null
                ? new RecorrenciaModel
                : RecorrenciaModel::query()->findOrFail($recorrencia->id);

            $model->fill([
                'descricao' => $recorrencia->descricao,
                'inicio' => $recorrencia->inicio,
                'fim' => $recorrencia->fim,
            ])->save();

            $model->valores()->delete();
            foreach ($recorrencia->vigencias as $vigencia) {
                $model->valores()->create([
                    'vigente_desde' => $vigencia->desde,
                    'valor_centavos' => $vigencia->valor->centavos,
                ]);
            }

            // Na v1.0 a ocorrência só existe para guardar o valor editado no mês.
            $model->ocorrencias()->whereNotIn('competencia', array_keys($recorrencia->excecoes))->delete();
            foreach ($recorrencia->excecoes as $competencia => $valor) {
                $model->ocorrencias()->updateOrCreate(
                    ['competencia' => $competencia],
                    ['valor_centavos' => $valor->centavos],
                );
            }

            return $this->porId($model->id);
        });
    }

    public function excluir(int $id): void
    {
        RecorrenciaModel::query()->whereKey($id)->delete();
    }

    /**
     * @return list<Recorrencia>
     */
    private function carregar(Builder $query): array
    {
        return $query
            ->with(['valores', 'ocorrencias' => fn ($q) => $q->whereNotNull('valor_centavos')])
            ->orderBy('descricao')
            ->orderBy('id')
            ->get()
            ->map(fn (RecorrenciaModel $m) => Recorrencia::reconstituir(
                $m->id,
                $m->descricao,
                $m->inicio,
                $m->fim,
                $m->valores->map(fn ($v) => new Vigencia($v->vigente_desde, Dinheiro::centavos($v->valor_centavos)))->all(),
                $m->ocorrencias->mapWithKeys(fn ($o) => [(string) $o->competencia => Dinheiro::centavos($o->valor_centavos)])->all(),
            ))
            ->all();
    }
}
