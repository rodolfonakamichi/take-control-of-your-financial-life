<?php

use App\Domain\Competencia;

// T03 — competência (ano, mês), aritmética de meses e RD-11 (dia inexistente vira o último dia do mês).

it('cria a partir de ano e mês e de texto AAAA-MM', function () {
    $c = Competencia::deTexto('2026-03');

    expect($c->ano)->toBe(2026)
        ->and($c->mes)->toBe(3)
        ->and((string) $c)->toBe('2026-03')
        ->and($c->igual(Competencia::de(2026, 3)))->toBeTrue();
});

it('recusa competências inválidas', function (callable $criar) {
    $criar();
})->with([
    'mês 0' => [fn () => Competencia::de(2026, 0)],
    'mês 13' => [fn () => Competencia::de(2026, 13)],
    'ano curto' => [fn () => Competencia::de(26, 1)],
    'texto sem zero' => [fn () => Competencia::deTexto('2026-3')],
    'texto com barra' => [fn () => Competencia::deTexto('03/2026')],
])->throws(InvalidArgumentException::class);

it('soma e subtrai meses atravessando a virada do ano', function (string $de, int $meses, string $para) {
    expect((string) Competencia::deTexto($de)->somarMeses($meses))->toBe($para);
})->with([
    ['2025-10', 5, '2026-03'],
    ['2025-12', 1, '2026-01'],
    ['2026-01', -1, '2025-12'],
    ['2026-03', -14, '2025-01'],
    ['2026-06', 0, '2026-06'],
    ['2026-01', 24, '2028-01'],
]);

it('tem atalhos para o mês anterior e o próximo', function () {
    $c = Competencia::de(2026, 1);

    expect((string) $c->anterior())->toBe('2025-12')
        ->and((string) $c->proxima())->toBe('2026-02');
});

it('conta os meses entre duas competências', function () {
    $outubro = Competencia::de(2025, 10);
    $marco = Competencia::de(2026, 3);

    expect($outubro->mesesAte($marco))->toBe(5)
        ->and($marco->mesesAte($outubro))->toBe(-5);
});

it('compara competências', function () {
    $dez = Competencia::de(2025, 12);
    $jan = Competencia::de(2026, 1);

    expect($dez->antesDe($jan))->toBeTrue()
        ->and($jan->depoisDe($dez))->toBeTrue()
        ->and($dez->comparar($dez))->toBe(0)
        ->and(strcmp((string) $dez, (string) $jan))->toBeLessThan(0); // ordena como texto no banco
});

it('verifica se está dentro de um intervalo, com fim aberto', function () {
    $inicio = Competencia::de(2026, 1);
    $fim = Competencia::de(2026, 8);

    expect(Competencia::de(2026, 1)->entre($inicio, $fim))->toBeTrue()
        ->and(Competencia::de(2026, 8)->entre($inicio, $fim))->toBeTrue()
        ->and(Competencia::de(2026, 9)->entre($inicio, $fim))->toBeFalse()
        ->and(Competencia::de(2025, 12)->entre($inicio, $fim))->toBeFalse()
        ->and(Competencia::de(2040, 5)->entre($inicio, null))->toBeTrue();
});

it('lista os 12 meses de um ano', function () {
    $meses = Competencia::mesesDoAno(2026);

    expect($meses)->toHaveCount(12)
        ->and((string) $meses[0])->toBe('2026-01')
        ->and((string) $meses[11])->toBe('2026-12');
});

it('sabe quantos dias tem o mês, inclusive em ano bissexto', function (string $c, int $dias) {
    expect(Competencia::deTexto($c)->diasNoMes())->toBe($dias);
})->with([
    ['2026-01', 31],
    ['2026-02', 28],
    ['2028-02', 29],
    ['2026-04', 30],
]);

it('resolve dia inexistente para o último dia do mês (RD-11)', function (string $c, int $dia, string $data) {
    expect(Competencia::deTexto($c)->dia($dia)->format('Y-m-d'))->toBe($data);
})->with([
    ['2026-03', 10, '2026-03-10'],
    ['2026-02', 31, '2026-02-28'],
    ['2028-02', 30, '2028-02-29'],
    ['2026-04', 31, '2026-04-30'],
    ['2026-01', 31, '2026-01-31'],
]);

it('recusa dia fora de 1 a 31', function (int $dia) {
    Competencia::de(2026, 1)->dia($dia);
})->with([0, 32])->throws(InvalidArgumentException::class);

it('cria a partir de uma data', function () {
    expect((string) Competencia::deData(new DateTimeImmutable('2026-11-27')))->toBe('2026-11');
});

it('formata para exibição em português', function () {
    $c = Competencia::de(2026, 3);

    expect($c->formatar())->toBe('03/2026')
        ->and($c->nomeDoMes())->toBe('Março');
});

it('sabe se há mês anterior e seguinte dentro do calendário', function () {
    expect(Competencia::de(1900, 1)->temAnterior())->toBeFalse()
        ->and(Competencia::de(1900, 1)->temProxima())->toBeTrue()
        ->and(Competencia::de(9999, 12)->temProxima())->toBeFalse()
        ->and(Competencia::de(9999, 12)->temAnterior())->toBeTrue()
        ->and(Competencia::de(2026, 1)->temAnterior())->toBeTrue()
        ->and(Competencia::de(2026, 12)->temProxima())->toBeTrue();
});
