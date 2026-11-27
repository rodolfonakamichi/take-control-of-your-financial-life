<?php

use App\Domain\Parcelamento\Parcelamento;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Models\Lancamento;

// T08 — Parcelamento (domínio) <-> parcelamentos + lancamentos.

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-01-15 09:00:00'));
    $this->repo = app(RepositorioParcelamentos::class);
});

function viagemGravada(): Parcelamento
{
    return app(RepositorioParcelamentos::class)->salvar(Parcelamento::novo('Viagem', reais('250,00'), 6, mes('2025-10')));
}

it('grava as parcelas como lançamentos de despesa, uma por mês', function () {
    $salvo = viagemGravada();

    $linhas = Lancamento::query()->orderBy('parcela_numero')->get();

    expect($salvo->id)->toBeInt()
        ->and($linhas)->toHaveCount(6)
        ->and($linhas->pluck('competencia')->map(fn ($c) => (string) $c)->all())
        ->toBe(['2025-10', '2025-11', '2025-12', '2026-01', '2026-02', '2026-03'])
        ->and($linhas->first()->only(['tipo', 'origem', 'descricao', 'valor_centavos', 'parcela_numero']))
        ->toBe(['tipo' => 'despesa', 'origem' => 'parcelamento', 'descricao' => 'Viagem', 'valor_centavos' => 25000, 'parcela_numero' => 1]);
});

it('lê de volta o mesmo parcelamento', function () {
    $salvo = viagemGravada();

    $lido = $this->repo->porId($salvo->id);

    expect($lido->descricao)->toBe('Viagem')
        ->and($lido->totalParcelas)->toBe(6)
        ->and($lido->parcelaEm(mes('2026-01'))?->numero)->toBe(4)
        ->and($lido->cancelado())->toBeFalse();
});

it('grava alteração de valor sem tocar o passado', function () {
    $salvo = viagemGravada();

    $this->repo->salvar($salvo->alterarValorParcela(reais('300,00'), mes('2026-01')));

    expect(Lancamento::query()->orderBy('parcela_numero')->pluck('valor_centavos')->all())
        ->toBe([25000, 25000, 25000, 30000, 30000, 30000]);
});

it('grava o cancelamento apagando só as parcelas canceladas', function () {
    $salvo = viagemGravada();

    $this->repo->salvar($salvo->cancelar(mes('2026-01'), new DateTimeImmutable('2026-01-15')));
    $lido = $this->repo->porId($salvo->id);

    expect(Lancamento::query()->pluck('parcela_numero')->all())->toBe([1, 2, 3])
        ->and($lido->cancelado())->toBeTrue()
        ->and($lido->parcelas)->toHaveCount(3);
});

it('grava a data de pagamento da parcela e a mantém', function () {
    $salvo = viagemGravada();

    $pago = $this->repo->salvar($salvo->marcarParcela(2, true));
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-02-20 09:00:00'));
    app(RepositorioParcelamentos::class)->salvar($pago->alterarValorParcela(reais('300,00'), mes('2026-02')));

    $parcela2 = Lancamento::query()->where('parcela_numero', 2)->first();

    expect($parcela2->pago_em->format('Y-m-d'))->toBe('2026-01-15')
        ->and($this->repo->porId($salvo->id)->parcela(2)->paga)->toBeTrue();
});

it('desmarca o pagamento', function () {
    $pago = $this->repo->salvar(viagemGravada()->marcarParcela(2, true));

    $this->repo->salvar($pago->marcarParcela(2, false));

    expect(Lancamento::query()->where('parcela_numero', 2)->value('pago_em'))->toBeNull();
});

it('encontra os parcelamentos com parcela num mês', function () {
    viagemGravada();
    $this->repo->salvar(Parcelamento::novo('Celular', reais('150,00'), 3, mes('2026-03')));

    $descricoes = fn (string $m) => array_map(fn (Parcelamento $p) => $p->descricao, $this->repo->comParcelaEm(mes($m)));

    expect($descricoes('2026-03'))->toBe(['Celular', 'Viagem'])
        ->and($descricoes('2026-04'))->toBe(['Celular'])
        ->and($descricoes('2026-06'))->toBe([]);
});

it('exclui o parcelamento por completo, com todas as parcelas', function () {
    $salvo = viagemGravada();

    $this->repo->excluir($salvo->id);

    expect(Lancamento::query()->count())->toBe(0)
        ->and($this->repo->todos())->toBeEmpty();
});
