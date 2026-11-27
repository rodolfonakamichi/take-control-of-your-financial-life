<?php

use App\Domain\Competencia;
use App\Domain\Guardado\MovimentoGuardado;
use App\Domain\Lancamento\CategoriaReceita;
use App\Domain\Lancamento\Despesa;
use App\Domain\Lancamento\Receita;
use App\Domain\Resumo\CalculadoraResumo;
use App\Domain\Resumo\ResumoMensal;

// T04 — RD-01..RD-04 (resumo do mês).

function resumoDoMes(array $despesas = [], array $receitas = [], array $movimentos = [], string $mes = '2026-03'): ResumoMensal
{
    return (new CalculadoraResumo)->calcular(Competencia::deTexto($mes), $despesas, $receitas, $movimentos);
}

it('soma as despesas do mês em Contas a pagar (RD-01)', function () {
    $r = resumoDoMes(despesas: [
        new Despesa('Aluguel', reais('1.200,00')),
        new Despesa('Internet', reais('99,90')),
        new Despesa('Academia', reais('120,10')),
    ]);

    expect($r->contasAPagar->centavos)->toBe(142000);
});

it('CA-02.1 soma Salário e Comissão em Recebidos', function () {
    $r = resumoDoMes(receitas: [
        new Receita('Salário', reais('4.000,00'), CategoriaReceita::Salario),
        new Receita('Comissão', reais('200,00'), CategoriaReceita::Comissao),
    ]);

    expect($r->recebidos->formatar())->toBe('R$ 4.200,00')
        ->and($r->recebidosDe(CategoriaReceita::Salario)->formatar())->toBe('R$ 4.000,00')
        ->and($r->recebidosDe(CategoriaReceita::Comissao)->formatar())->toBe('R$ 200,00')
        ->and($r->recebidosDe(CategoriaReceita::Outros)->ehZero())->toBeTrue();
});

it('CA-02.2 soma várias receitas da mesma categoria no mesmo mês', function () {
    $r = resumoDoMes(receitas: [
        new Receita('Adiantamento de férias', reais('1.500,00'), CategoriaReceita::Outros),
        new Receita('Saldo de férias', reais('800,00'), CategoriaReceita::Outros),
        new Receita('Salário', reais('2.000,00'), CategoriaReceita::Salario),
    ]);

    expect($r->recebidosDe(CategoriaReceita::Outros)->formatar())->toBe('R$ 2.300,00')
        ->and($r->recebidos->formatar())->toBe('R$ 4.300,00');
});

it('CA-03.1 calcula Contas a pagar, Recebidos, Guardado no mês e Restante', function () {
    $r = resumoDoMes(
        despesas: [new Despesa('Aluguel', reais('2.000,00')), new Despesa('Mercado', reais('800,00'))],
        receitas: [new Receita('Salário', reais('4.000,00'))],
        movimentos: [MovimentoGuardado::guardar(reais('200,00'))],
    );

    expect($r->contasAPagar->formatar())->toBe('R$ 2.800,00')
        ->and($r->recebidos->formatar())->toBe('R$ 4.000,00')
        ->and($r->guardadoNoMes->formatar())->toBe('R$ 200,00')
        ->and($r->restante->formatar())->toBe('R$ 1.000,00');
});

it('CA-03.4 não leva o Restante de um mês para o seguinte', function () {
    $calculadora = new CalculadoraResumo;

    $janeiro = $calculadora->calcular(
        Competencia::deTexto('2026-01'),
        [new Despesa('Aluguel', reais('1.000,00'))],
        [new Receita('Salário', reais('3.000,00'))],
    );
    $fevereiro = $calculadora->calcular(
        Competencia::deTexto('2026-02'),
        [new Despesa('Aluguel', reais('1.000,00'))],
        [new Receita('Salário', reais('1.500,00'))],
    );

    expect($janeiro->restante->formatar())->toBe('R$ 2.000,00')
        ->and($fevereiro->restante->formatar())->toBe('R$ 500,00');
});

it('CA-06.4 retirar do guardado aumenta o Restante e guardar diminui', function () {
    $despesas = [new Despesa('Contas', reais('3.000,00'))];
    $receitas = [new Receita('Salário', reais('4.000,00'))];

    $comRetirada = resumoDoMes($despesas, $receitas, [MovimentoGuardado::retirar(reais('500,00'), 'Resgate')]);
    $comGuardar = resumoDoMes($despesas, $receitas, [MovimentoGuardado::guardar(reais('500,00'))]);

    expect($comRetirada->guardadoNoMes->formatar())->toBe('-R$ 500,00')
        ->and($comRetirada->restante->formatar())->toBe('R$ 1.500,00')
        ->and($comGuardar->guardadoNoMes->formatar())->toBe('R$ 500,00')
        ->and($comGuardar->restante->formatar())->toBe('R$ 500,00');
});

it('soma guardar e retirar do mesmo mês no Guardado no mês (RD-03)', function () {
    $r = resumoDoMes(movimentos: [
        MovimentoGuardado::guardar(reais('300,00')),
        MovimentoGuardado::retirar(reais('120,00')),
        MovimentoGuardado::guardar(reais('20,00')),
    ]);

    expect($r->guardadoNoMes->formatar())->toBe('R$ 200,00');
});

it('dá Restante negativo quando as contas passam do que foi recebido (RD-04)', function () {
    $r = resumoDoMes(
        despesas: [new Despesa('Aluguel', reais('1.200,00'))],
        receitas: [],
    );

    expect($r->restante->formatar())->toBe('-R$ 1.200,00')
        ->and($r->restanteNegativo())->toBeTrue();
});

it('zera tudo num mês sem lançamentos', function () {
    $r = resumoDoMes();

    expect($r->contasAPagar->ehZero())->toBeTrue()
        ->and($r->recebidos->ehZero())->toBeTrue()
        ->and($r->guardadoNoMes->ehZero())->toBeTrue()
        ->and($r->restante->ehZero())->toBeTrue()
        ->and($r->restanteNegativo())->toBeFalse()
        ->and((string) $r->competencia)->toBe('2026-03');
});

it('aceita qualquer iterável, não só arrays', function () {
    $despesas = (function () {
        yield new Despesa('Luz', reais('150,00'));
        yield new Despesa('Água', reais('50,00'));
    })();

    expect(resumoDoMes(despesas: iterator_to_array($despesas))->contasAPagar->formatar())->toBe('R$ 200,00');
});
