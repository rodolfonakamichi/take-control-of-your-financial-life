<?php

namespace App\Infra\Persistencia;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Parcelamento\Parcela;
use App\Domain\Parcelamento\Parcelamento;
use App\Infra\Tempo\Relogio;
use App\Models\Lancamento;
use App\Models\Parcelamento as ParcelamentoModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Parcelamento (domínio) <-> parcelamentos + lancamentos (origem = parcelamento).
 * A descrição gravada na parcela é a do parcelamento, sem a numeração (que é gerada na exibição).
 */
final class RepositorioParcelamentos
{
    public function __construct(private Relogio $relogio) {}

    /**
     * @return list<Parcelamento>
     */
    public function comParcelaEm(Competencia $competencia): array
    {
        return $this->carregar(
            ParcelamentoModel::query()->whereHas('parcelas', fn (Builder $q) => $q->where('competencia', (string) $competencia)),
        );
    }

    /**
     * @return list<Parcelamento>
     */
    public function todos(): array
    {
        return $this->carregar(ParcelamentoModel::query());
    }

    public function porId(int $id): ?Parcelamento
    {
        return $this->carregar(ParcelamentoModel::query()->whereKey($id))[0] ?? null;
    }

    public function salvar(Parcelamento $parcelamento): Parcelamento
    {
        return DB::transaction(function () use ($parcelamento) {
            $model = $parcelamento->id === null
                ? new ParcelamentoModel
                : ParcelamentoModel::query()->findOrFail($parcelamento->id);

            $model->fill([
                'descricao' => $parcelamento->descricao,
                'valor_parcela_centavos' => $parcelamento->valorParcela->centavos,
                'total_parcelas' => $parcelamento->totalParcelas,
                'primeira_competencia' => $parcelamento->primeiraCompetencia,
                'cancelado_em' => $parcelamento->canceladoEm,
            ])->save();

            $existentes = $model->parcelas()->get()->keyBy('parcela_numero');
            $numeros = array_map(fn (Parcela $p) => $p->numero, $parcelamento->parcelas);

            $model->parcelas()->whereNotIn('parcela_numero', $numeros)->delete();

            foreach ($parcelamento->parcelas as $parcela) {
                $linha = $existentes->get($parcela->numero) ?? new Lancamento([
                    'tipo' => Lancamento::DESPESA,
                    'origem' => Lancamento::PARCELAMENTO,
                    'parcelamento_id' => $model->id,
                    'parcela_numero' => $parcela->numero,
                ]);

                $linha->fill([
                    'descricao' => $parcelamento->descricao,
                    'valor_centavos' => $parcela->valor->centavos,
                    'competencia' => $parcela->competencia,
                    'pago_em' => $parcela->paga ? ($linha->pago_em ?? $this->relogio->agora()) : null,
                ])->save();
            }

            return $this->porId($model->id);
        });
    }

    /**
     * Exclusão completa, inclusive parcelas passadas (CA-05.5 pede confirmação na tela).
     */
    public function excluir(int $id): void
    {
        ParcelamentoModel::query()->whereKey($id)->delete();
    }

    /**
     * @return list<Parcelamento>
     */
    private function carregar(Builder $query): array
    {
        return $query
            ->with('parcelas')
            ->orderBy('descricao')
            ->orderBy('id')
            ->get()
            ->map(fn (ParcelamentoModel $m) => Parcelamento::reconstituir(
                $m->id,
                $m->descricao,
                Dinheiro::centavos($m->valor_parcela_centavos),
                $m->total_parcelas,
                $m->primeira_competencia,
                $m->parcelas->map(fn (Lancamento $l) => new Parcela(
                    $l->parcela_numero,
                    $m->total_parcelas,
                    $l->competencia,
                    Dinheiro::centavos($l->valor_centavos),
                    $l->pago_em !== null,
                ))->all(),
                $m->cancelado_em,
            ))
            ->all();
    }
}
