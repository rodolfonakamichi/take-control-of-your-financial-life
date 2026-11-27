<?php

use App\Domain\Dinheiro;
use App\Domain\Guardado\GuardadoFicariaNegativo;
use App\Domain\Guardado\LivroGuardado;
use App\Domain\Guardado\MovimentoGuardado;

// T05 — RD-03, RD-05 (total guardado encadeado entre meses e anos) e RV-07 (nunca negativo).

function guardar(string $valor, ?string $descricao = null): MovimentoGuardado
{
    return MovimentoGuardado::guardar(reais($valor), $descricao);
}

function retirar(string $valor, ?string $descricao = null): MovimentoGuardado
{
    return MovimentoGuardado::retirar(reais($valor), $descricao);
}

it('CA-06.2 acumula o total guardado mês a mês', function () {
    $livro = LivroGuardado::de(Dinheiro::zero(), [
        [mes('2026-01'), guardar('100,00')],
        [mes('2026-03'), guardar('250,00')],
    ]);

    expect($livro->totalAte(mes('2026-01'))->formatar())->toBe('R$ 100,00')
        ->and($livro->totalAte(mes('2026-02'))->formatar())->toBe('R$ 100,00')
        ->and($livro->totalAte(mes('2026-03'))->formatar())->toBe('R$ 350,00');
});

it('CA-06.3 encadeia o total guardado na virada do ano', function () {
    $livro = LivroGuardado::de(Dinheiro::zero(), [
        [mes('2025-06'), guardar('600,00')],
        [mes('2025-12'), guardar('400,00')],
        [mes('2026-01'), guardar('100,00')],
    ]);

    expect($livro->totalAte(mes('2025-12'))->formatar())->toBe('R$ 1.000,00')
        ->and($livro->totalAte(mes('2026-01'))->formatar())->toBe('R$ 1.100,00');
});

it('CA-06.5 recusa retirada maior que o total guardado no mês', function () {
    $livro = LivroGuardado::de(Dinheiro::zero(), [[mes('2026-01'), guardar('300,00')]]);

    $livro->adicionar(mes('2026-02'), retirar('300,01'));
})->throws(GuardadoFicariaNegativo::class, 'O total guardado ficaria negativo em 02/2026 (-R$ 0,01).');

it('CA-06.5 recusa retirada que deixaria um mês futuro negativo', function () {
    // jan +500, mar −400. Retirar 200 em fev deixa fev com 300 (ok), mas mar com −100.
    $livro = LivroGuardado::de(Dinheiro::zero(), [
        [mes('2026-01'), guardar('500,00')],
        [mes('2026-03'), retirar('400,00')],
    ]);

    try {
        $livro->adicionar(mes('2026-02'), retirar('200,00'));
        $this->fail('Deveria ter recusado a retirada.');
    } catch (GuardadoFicariaNegativo $e) {
        expect((string) $e->competencia)->toBe('2026-03')
            ->and($e->total->formatar())->toBe('-R$ 100,00');
    }
});

it('CA-06.5 aceita retirada que zera o total guardado', function () {
    $livro = LivroGuardado::de(Dinheiro::zero(), [[mes('2026-01'), guardar('300,00')]])
        ->adicionar(mes('2026-02'), retirar('300,00'));

    expect($livro->totalAte(mes('2026-02'))->ehZero())->toBeTrue();
});

it('recusa remover um guardar antigo se isso deixar um mês negativo (RV-07)', function () {
    $livro = LivroGuardado::de(Dinheiro::zero(), [
        [mes('2026-01'), guardar('500,00')],
        [mes('2026-04'), retirar('200,00')],
    ]);

    $livro->remover(mes('2026-01'), guardar('500,00'));
})->throws(GuardadoFicariaNegativo::class, '04/2026');

it('remove um movimento e recalcula os totais seguintes', function () {
    $livro = LivroGuardado::de(Dinheiro::zero(), [
        [mes('2026-01'), guardar('500,00', 'CDB')],
        [mes('2026-01'), guardar('100,00', 'Poupança')],
        [mes('2026-02'), guardar('50,00')],
    ])->remover(mes('2026-01'), guardar('100,00', 'Poupança'));

    expect($livro->guardadoNoMes(mes('2026-01'))->formatar())->toBe('R$ 500,00')
        ->and($livro->totalAte(mes('2026-02'))->formatar())->toBe('R$ 550,00');
});

it('acusa remoção de movimento que não existe', function () {
    LivroGuardado::de(Dinheiro::zero(), [[mes('2026-01'), guardar('500,00')]])
        ->remover(mes('2026-01'), guardar('499,00'));
})->throws(InvalidArgumentException::class, 'não encontrado');

it('é imutável: adicionar devolve um livro novo', function () {
    $original = LivroGuardado::de(Dinheiro::zero(), [[mes('2026-01'), guardar('100,00')]]);
    $novo = $original->adicionar(mes('2026-02'), guardar('50,00'));

    expect($original->totalAte(mes('2026-02'))->formatar())->toBe('R$ 100,00')
        ->and($novo->totalAte(mes('2026-02'))->formatar())->toBe('R$ 150,00');
});

it('usa o guardado inicial antes do primeiro movimento (RD-05)', function () {
    $livro = LivroGuardado::de(reais('2.000,00'), [[mes('2026-05'), retirar('500,00')]]);

    expect($livro->totalAte(mes('2020-01'))->formatar())->toBe('R$ 2.000,00')
        ->and($livro->totalAte(mes('2026-04'))->formatar())->toBe('R$ 2.000,00')
        ->and($livro->totalAte(mes('2026-05'))->formatar())->toBe('R$ 1.500,00');
});

it('permite retirar do guardado inicial até zerar, mas não além (RV-07)', function () {
    $livro = LivroGuardado::de(reais('1.000,00'));

    expect($livro->adicionar(mes('2026-01'), retirar('1.000,00'))->totalAte(mes('2026-01'))->ehZero())->toBeTrue();

    $livro->adicionar(mes('2026-01'), retirar('1.000,01'));
})->throws(GuardadoFicariaNegativo::class);

it('revalida o histórico ao trocar o guardado inicial', function () {
    $livro = LivroGuardado::de(reais('1.000,00'), [[mes('2026-01'), retirar('800,00')]]);

    expect($livro->comGuardadoInicial(reais('900,00'))->totalAte(mes('2026-01'))->formatar())->toBe('R$ 100,00');

    $livro->comGuardadoInicial(reais('700,00'));
})->throws(GuardadoFicariaNegativo::class, '01/2026');

it('recusa guardado inicial negativo', function () {
    LivroGuardado::de(reais('-1,00'));
})->throws(InvalidArgumentException::class);

it('recusa histórico que já começa negativo', function () {
    LivroGuardado::de(Dinheiro::zero(), [[mes('2026-01'), retirar('10,00')]]);
})->throws(GuardadoFicariaNegativo::class);

it('soma guardar e retirar do mesmo mês, em qualquer ordem (RD-03)', function () {
    // A ordem dentro do mês não importa: o total é verificado ao fim de cada mês.
    $livro = LivroGuardado::de(Dinheiro::zero(), [
        [mes('2026-01'), retirar('100,00')],
        [mes('2026-01'), guardar('300,00')],
    ]);

    expect($livro->guardadoNoMes(mes('2026-01'))->formatar())->toBe('R$ 200,00')
        ->and($livro->guardadoNoMes(mes('2026-02'))->ehZero())->toBeTrue();
});

it('aceita movimentos fora de ordem cronológica', function () {
    $livro = LivroGuardado::de(Dinheiro::zero(), [
        [mes('2026-03'), retirar('100,00')],
        [mes('2025-11'), guardar('300,00')],
    ]);

    expect($livro->totalAte(mes('2026-03'))->formatar())->toBe('R$ 200,00');
});

it('gera a série mensal do total guardado atravessando o ano', function () {
    $livro = LivroGuardado::de(reais('50,00'), [
        [mes('2025-10'), guardar('100,00')],
        [mes('2025-12'), guardar('200,00')],
        [mes('2026-02'), retirar('150,00')],
    ]);

    $serie = array_map(fn (Dinheiro $d) => $d->formatar(), $livro->serie(mes('2025-11'), mes('2026-02')));

    expect($serie)->toBe([
        '2025-11' => 'R$ 150,00',
        '2025-12' => 'R$ 350,00',
        '2026-01' => 'R$ 350,00',
        '2026-02' => 'R$ 200,00',
    ]);
});

it('recusa série com fim antes do início', function () {
    LivroGuardado::de(Dinheiro::zero())->serie(mes('2026-02'), mes('2026-01'));
})->throws(InvalidArgumentException::class);
