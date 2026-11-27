<?php

namespace App\Application\Mes;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Lancamento\CategoriaReceita;
use App\Domain\Parcelamento\GeradorParcelas;
use App\Domain\Recorrencia\ResolvedorRecorrencia;
use App\Domain\Resumo\CalculadoraResumo;
use App\Infra\Persistencia\RepositorioGuardado;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Infra\Tempo\Relogio;
use App\Models\Lancamento;
use App\Models\MovimentoGuardado as MovimentoModel;

/**
 * Junta tudo o que pertence a um mês e calcula o resumo (design §2.2):
 * recorrências ativas, parcelas, lançamentos avulsos, receitas e movimentos do guardado.
 * Nada é gravado: o mês é sempre montado (RV-02, CA-08.3).
 */
final class MontarMes
{
    public function __construct(
        private RepositorioRecorrencias $recorrencias,
        private RepositorioParcelamentos $parcelamentos,
        private RepositorioGuardado $guardado,
        private Relogio $relogio,
        private CalculadoraResumo $calculadora = new CalculadoraResumo,
        private ResolvedorRecorrencia $resolvedor = new ResolvedorRecorrencia,
    ) {}

    public function executar(Competencia $competencia): MesMontado
    {
        $despesas = [
            ...$this->despesasDeRecorrencias($competencia),
            ...$this->despesasDeParcelas($competencia),
            ...$this->despesasAvulsas($competencia),
        ];
        $receitas = $this->receitas($competencia);
        $movimentos = $this->movimentos($competencia);

        $resumo = $this->calculadora->calcular(
            $competencia,
            array_map(fn (ItemDespesa $d) => $d->paraDespesa(), $despesas),
            array_map(fn (ItemReceita $r) => $r->paraReceita(), $receitas),
            array_map(fn (ItemMovimento $m) => $m->movimento, $movimentos),
        );

        return new MesMontado(
            $competencia,
            $despesas,
            $receitas,
            $movimentos,
            $resumo,
            $this->guardado->livro()->totalAte($competencia),
            $competencia->depoisDe($this->relogio->mesAtual()),
        );
    }

    /**
     * @return list<ItemDespesa>
     */
    private function despesasDeRecorrencias(Competencia $competencia): array
    {
        $ocorrencias = $this->resolvedor->ocorrenciasDoMes($competencia, $this->recorrencias->ativasEm($competencia));

        return array_map(fn ($o) => new ItemDespesa(
            origem: OrigemDespesa::Recorrencia,
            descricao: $o->descricao,
            valor: $o->valor,
            recorrenciaId: $o->recorrenciaId,
            valorEditadoNoMes: $o->valorEditadoNoMes,
        ), $ocorrencias);
    }

    /**
     * @return list<ItemDespesa>
     */
    private function despesasDeParcelas(Competencia $competencia): array
    {
        $doMes = GeradorParcelas::doMes($competencia, $this->parcelamentos->comParcelaEm($competencia));

        return array_map(fn (array $item) => new ItemDespesa(
            origem: OrigemDespesa::Parcelamento,
            descricao: $item['parcela']->descricaoCom($item['parcelamento']->descricao),
            valor: $item['parcela']->valor,
            parcelamentoId: $item['parcelamento']->id,
            parcelaNumero: $item['parcela']->numero,
            totalParcelas: $item['parcela']->total,
            paga: $item['parcela']->paga,
        ), $doMes);
    }

    /**
     * @return list<ItemDespesa>
     */
    private function despesasAvulsas(Competencia $competencia): array
    {
        return Lancamento::query()
            ->doMes((string) $competencia)
            ->where('tipo', Lancamento::DESPESA)
            ->where('origem', Lancamento::AVULSO)
            ->orderBy('id')
            ->get()
            ->map(fn (Lancamento $l) => new ItemDespesa(
                origem: OrigemDespesa::Avulso,
                descricao: $l->descricao,
                valor: Dinheiro::centavos($l->valor_centavos),
                lancamentoId: $l->id,
                paga: $l->pago_em !== null,
            ))
            ->all();
    }

    /**
     * @return list<ItemReceita>
     */
    private function receitas(Competencia $competencia): array
    {
        return Lancamento::query()
            ->doMes((string) $competencia)
            ->where('tipo', Lancamento::RECEITA)
            ->orderBy('id')
            ->get()
            ->map(fn (Lancamento $l) => new ItemReceita(
                $l->id,
                $l->descricao,
                Dinheiro::centavos($l->valor_centavos),
                CategoriaReceita::from($l->categoria_receita ?? CategoriaReceita::Salario->value),
            ))
            ->all();
    }

    /**
     * @return list<ItemMovimento>
     */
    private function movimentos(Competencia $competencia): array
    {
        return MovimentoModel::query()
            ->where('competencia', (string) $competencia)
            ->orderBy('id')
            ->get()
            ->map(fn (MovimentoModel $m) => new ItemMovimento($m->id, RepositorioGuardado::paraDominio($m)))
            ->all();
    }
}
