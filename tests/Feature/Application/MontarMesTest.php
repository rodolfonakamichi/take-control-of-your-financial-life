<?php

use App\Application\Mes\ItemDespesa;
use App\Application\Mes\MontarMes;
use App\Application\Mes\OrigemDespesa;
use App\Domain\Parcelamento\Parcelamento;
use App\Domain\Recorrencia\Recorrencia;
use App\Infra\Persistencia\Configuracoes;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Models\Lancamento;
use App\Models\MovimentoGuardado;

// T08 — montagem do mês (design §2.2).

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-03-10 12:00:00'));
});

function montar(string $competencia)
{
    return app(MontarMes::class)->executar(mes($competencia));
}

function despesaAvulsa(string $competencia, string $descricao, string $valor): Lancamento
{
    return Lancamento::query()->create([
        'tipo' => Lancamento::DESPESA, 'origem' => Lancamento::AVULSO,
        'competencia' => $competencia, 'descricao' => $descricao, 'valor_centavos' => reais($valor)->centavos,
    ]);
}

function receita(string $competencia, string $descricao, string $valor, string $categoria = 'salario'): Lancamento
{
    return Lancamento::query()->create([
        'tipo' => Lancamento::RECEITA, 'origem' => Lancamento::AVULSO, 'categoria_receita' => $categoria,
        'competencia' => $competencia, 'descricao' => $descricao, 'valor_centavos' => reais($valor)->centavos,
    ]);
}

function movimento(string $competencia, string $tipo, string $valor): MovimentoGuardado
{
    return MovimentoGuardado::query()->create([
        'competencia' => $competencia, 'tipo' => $tipo, 'valor_centavos' => reais($valor)->centavos,
    ]);
}

function cenarioCompleto(): void
{
    $recorrencias = app(RepositorioRecorrencias::class);
    $recorrencias->salvar(Recorrencia::nova('Aluguel', reais('1.200,00'), mes('2025-01')));
    $recorrencias->salvar(Recorrencia::nova('Internet', reais('50,00'), mes('2026-01'))->comValorNoMes(mes('2026-03'), reais('60,00')));
    $recorrencias->salvar(Recorrencia::nova('Curso antigo', reais('300,00'), mes('2025-01'), mes('2026-02')));

    app(RepositorioParcelamentos::class)->salvar(Parcelamento::novo('Viagem', reais('250,00'), 6, mes('2025-10')));

    despesaAvulsa('2026-03', 'Presente', '90,00');
    despesaAvulsa('2026-04', 'Mercado do mês seguinte', '400,00');
    receita('2026-03', 'Salário', '3.500,00');
    receita('2026-03', 'Comissão', '200,00', 'comissao');
    receita('2026-02', 'Salário de fevereiro', '3.500,00');

    app(Configuracoes::class)->definirGuardadoInicial(reais('1.000,00'));
    movimento('2026-01', 'guardar', '300,00');
    movimento('2026-03', 'guardar', '200,00');
    movimento('2026-03', 'retirar', '50,00');
}

it('junta recorrências, parcelas e avulsos do mês, nessa ordem', function () {
    cenarioCompleto();

    $despesas = array_map(
        fn (ItemDespesa $d) => [$d->origem, $d->descricao, $d->valor->formatar()],
        montar('2026-03')->despesas,
    );

    expect($despesas)->toBe([
        [OrigemDespesa::Recorrencia, 'Aluguel', 'R$ 1.200,00'],
        [OrigemDespesa::Recorrencia, 'Internet', 'R$ 60,00'],
        [OrigemDespesa::Parcelamento, 'Viagem (6/6)', 'R$ 250,00'],
        [OrigemDespesa::Avulso, 'Presente', 'R$ 90,00'],
    ]);
});

it('traz receitas e movimentos do guardado só do mês', function () {
    cenarioCompleto();

    $mes = montar('2026-03');

    expect(array_map(fn ($r) => $r->descricao, $mes->receitas))->toBe(['Salário', 'Comissão'])
        ->and(array_map(fn ($m) => $m->movimento->efeito()->formatar(), $mes->movimentos))->toBe(['R$ 200,00', '-R$ 50,00']);
});

it('calcula o resumo do mês a partir de tudo o que foi montado', function () {
    cenarioCompleto();

    $resumo = montar('2026-03')->resumo;

    // 1.200 + 60 + 250 + 90 = 1.600; 3.500 + 200 = 3.700; guardado 200 − 50 = 150.
    expect($resumo->contasAPagar->formatar())->toBe('R$ 1.600,00')
        ->and($resumo->recebidos->formatar())->toBe('R$ 3.700,00')
        ->and($resumo->guardadoNoMes->formatar())->toBe('R$ 150,00')
        ->and($resumo->restante->formatar())->toBe('R$ 1.950,00');
});

it('CA-03.5 informa o total guardado até o mês', function () {
    cenarioCompleto();

    expect(montar('2025-12')->totalGuardado->formatar())->toBe('R$ 1.000,00')
        ->and(montar('2026-02')->totalGuardado->formatar())->toBe('R$ 1.300,00')
        ->and(montar('2026-03')->totalGuardado->formatar())->toBe('R$ 1.450,00');
});

it('marca a origem e os detalhes de cada despesa', function () {
    cenarioCompleto();

    $porDescricao = collect(montar('2026-03')->despesas)->keyBy('descricao');

    expect($porDescricao['Internet']->valorEditadoNoMes)->toBeTrue()
        ->and($porDescricao['Internet']->recorrenciaId)->toBeInt()
        ->and($porDescricao['Aluguel']->valorEditadoNoMes)->toBeFalse()
        ->and($porDescricao['Viagem (6/6)']->parcelaNumero)->toBe(6)
        ->and($porDescricao['Viagem (6/6)']->totalParcelas)->toBe(6)
        ->and($porDescricao['Viagem (6/6)']->parcelamentoId)->toBeInt()
        ->and($porDescricao['Presente']->lancamentoId)->toBeInt();
});

it('CA-08.1 monta um mês futuro só com recorrências e parcelas, sem criar nada', function () {
    cenarioCompleto();
    $lancamentosAntes = Lancamento::query()->count();

    $despesas = array_map(fn (ItemDespesa $d) => $d->descricao, montar('2027-07')->despesas);

    expect($despesas)->toBe(['Aluguel', 'Internet'])
        ->and(Lancamento::query()->count())->toBe($lancamentosAntes);
});

it('CA-08.2 marca como projeção só os meses depois do atual', function () {
    expect(montar('2026-02')->ehProjecao)->toBeFalse()
        ->and(montar('2026-03')->ehProjecao)->toBeFalse()
        ->and(montar('2026-04')->ehProjecao)->toBeTrue()
        ->and(montar('2030-01')->ehProjecao)->toBeTrue();
});

it('monta um mês vazio com tudo zerado', function () {
    $mes = montar('2026-03');

    expect($mes->despesas)->toBe([])
        ->and($mes->receitas)->toBe([])
        ->and($mes->resumo->restante->ehZero())->toBeTrue()
        ->and($mes->totalGuardado->ehZero())->toBeTrue();
});
