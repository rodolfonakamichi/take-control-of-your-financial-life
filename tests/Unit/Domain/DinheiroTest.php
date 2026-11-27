<?php

use App\Domain\Dinheiro;

// T03 — RNF-01 (centavos inteiros), RNF-02 (formato BRL), RD-14 (parcelas com resto na primeira).

it('guarda o valor em centavos inteiros', function () {
    expect(Dinheiro::centavos(120000)->centavos)->toBe(120000)
        ->and(Dinheiro::zero()->ehZero())->toBeTrue();
});

it('lê valores digitados no formato brasileiro', function (string $texto, int $centavos) {
    expect(Dinheiro::deTexto($texto)->centavos)->toBe($centavos);
})->with([
    ['1.200,00', 120000],
    ['R$ 1.200,00', 120000],
    ['147,93', 14793],
    ['85', 8500],
    ['20,5', 2050],
    ['0,01', 1],
    ['-20,50', -2050],
    ['1200.00', 120000],
    ['147.93', 14793],
    ['1.234.567,89', 123456789],
    // Sem vírgula: ponto com 3 dígitos depois é milhar; com 1 ou 2 dígitos é decimal.
    ['3.000', 300000],
    ['R$ 3.000', 300000],
    ['1.234.567', 123456700],
    ['12.500', 1250000],
    ['3.5', 350],
]);

it('recusa texto que não é valor monetário', function (string $texto) {
    Dinheiro::deTexto($texto);
})->with(['', 'abc', '1,234', '12,3,4', '1.5.0', '1.23.456', '1.2345', '.50'])->throws(InvalidArgumentException::class);

it('converte decimais da planilha sem erro de ponto flutuante', function (float $valor, int $centavos) {
    expect(Dinheiro::deDecimal($valor)->centavos)->toBe($centavos);
})->with([
    [147.93, 14793],
    [0.1 + 0.2, 30],
    [2483.32, 248332],
    [64.99, 6499],
    [-1987.65, -198765],
]);

it('soma e subtrai sem perder centavos', function () {
    $despesas = Dinheiro::somarTodos([
        Dinheiro::deTexto('1200,00'),
        Dinheiro::deTexto('147,93'),
        Dinheiro::deTexto('60,00'),
        Dinheiro::deTexto('80,00'),
        Dinheiro::deTexto('129,90'),
        Dinheiro::deTexto('95,50'),
        Dinheiro::deTexto('250,00'),
        Dinheiro::deTexto('120,00'),
        Dinheiro::deTexto('300,00'),
        Dinheiro::deTexto('99,99'),
    ]);

    expect($despesas->centavos)->toBe(248332)
        ->and(Dinheiro::deTexto('3500,00')->subtrair($despesas)->centavos)->toBe(101668);
});

it('representa valores negativos', function () {
    $restante = Dinheiro::deTexto('100,00')->subtrair(Dinheiro::deTexto('250,00'));

    expect($restante->ehNegativo())->toBeTrue()
        ->and($restante->negar()->centavos)->toBe(15000)
        ->and($restante->menorQue(Dinheiro::zero()))->toBeTrue();
});

it('formata como moeda brasileira', function (int $centavos, string $esperado) {
    expect(Dinheiro::centavos($centavos)->formatar())->toBe($esperado);
})->with([
    [0, 'R$ 0,00'],
    [5, 'R$ 0,05'],
    [120000, 'R$ 1.200,00'],
    [123456789, 'R$ 1.234.567,89'],
    [-198765, '-R$ 1.987,65'],
]);

it('divide em parcelas iguais quando a divisão é exata (RD-14)', function () {
    $parcelas = Dinheiro::deTexto('600,00')->dividirEmParcelas(3);

    expect(array_map(fn (Dinheiro $p) => $p->centavos, $parcelas))->toBe([20000, 20000, 20000]);
});

it('coloca os centavos que sobram na primeira parcela (RD-14)', function () {
    $parcelas = Dinheiro::deTexto('100,00')->dividirEmParcelas(3);

    expect(array_map(fn (Dinheiro $p) => $p->centavos, $parcelas))->toBe([3334, 3333, 3333])
        ->and(Dinheiro::somarTodos($parcelas)->centavos)->toBe(10000);
});

it('recusa parcelamento inválido', function (int $centavos, int $quantidade) {
    Dinheiro::centavos($centavos)->dividirEmParcelas($quantidade);
})->with([[10000, 0], [-100, 2]])->throws(InvalidArgumentException::class);
