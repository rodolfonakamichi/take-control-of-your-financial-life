<?php

use App\Domain\Competencia;
use App\Domain\Recorrencia\OcorrenciaRecorrencia;
use App\Domain\Recorrencia\Recorrencia;
use App\Domain\Recorrencia\ResolvedorRecorrencia;
use App\Domain\Recorrencia\Vigencia;

// T06 — RD-07 (recorrências) e CA-04.1..CA-04.5.

function internet(?string $fim = null): Recorrencia
{
    return Recorrencia::nova('Internet', reais('50,00'), mes('2026-01'), $fim ? mes($fim) : null, id: 7);
}

/**
 * @return array<string, string|null> "AAAA-MM" => valor formatado (null = não aparece no mês)
 */
function valoresPorMes(Recorrencia $recorrencia, string $de, string $ate): array
{
    $valores = [];
    for ($m = mes($de); ! $m->depoisDe(mes($ate)); $m = $m->proxima()) {
        $valores[(string) $m] = $recorrencia->ocorrenciaEm($m)?->valor->formatar();
    }

    return $valores;
}

it('CA-04.1 aparece em todos os meses a partir do início, inclusive anos depois', function () {
    $recorrencia = internet();

    expect($recorrencia->ocorrenciaEm(mes('2026-01'))?->valor->formatar())->toBe('R$ 50,00')
        ->and($recorrencia->ocorrenciaEm(mes('2026-07'))?->descricao)->toBe('Internet')
        ->and($recorrencia->ocorrenciaEm(mes('2031-12'))?->valor->formatar())->toBe('R$ 50,00')
        ->and($recorrencia->ocorrenciaEm(mes('2025-12')))->toBeNull();
});

it('CA-04.2 altera o valor só no mês escolhido', function () {
    $recorrencia = internet()->comValorNoMes(mes('2026-03'), reais('60,00'));

    expect(valoresPorMes($recorrencia, '2026-02', '2026-04'))->toBe([
        '2026-02' => 'R$ 50,00',
        '2026-03' => 'R$ 60,00',
        '2026-04' => 'R$ 50,00',
    ])
        ->and($recorrencia->ocorrenciaEm(mes('2026-03'))?->valorEditadoNoMes)->toBeTrue()
        ->and($recorrencia->ocorrenciaEm(mes('2026-04'))?->valorEditadoNoMes)->toBeFalse();
});

it('CA-04.2 desfaz a edição do mês e volta ao valor padrão', function () {
    $recorrencia = internet()
        ->comValorNoMes(mes('2026-03'), reais('60,00'))
        ->semValorNoMes(mes('2026-03'));

    expect($recorrencia->ocorrenciaEm(mes('2026-03'))?->valor->formatar())->toBe('R$ 50,00')
        ->and($recorrencia->ocorrenciaEm(mes('2026-03'))?->valorEditadoNoMes)->toBeFalse();
});

it('CA-04.3 muda o valor padrão daqui para frente sem mexer nos meses anteriores', function () {
    $recorrencia = internet()->alterarValorAPartirDe(mes('2026-06'), reais('70,00'));

    expect(valoresPorMes($recorrencia, '2026-04', '2026-07'))->toBe([
        '2026-04' => 'R$ 50,00',
        '2026-05' => 'R$ 50,00',
        '2026-06' => 'R$ 70,00',
        '2026-07' => 'R$ 70,00',
    ])
        ->and($recorrencia->ocorrenciaEm(mes('2029-01'))?->valor->formatar())->toBe('R$ 70,00');
});

it('CA-04.3 descarta mudanças de valor já agendadas para depois do novo mês', function () {
    $recorrencia = internet()
        ->alterarValorAPartirDe(mes('2026-09'), reais('90,00'))
        ->alterarValorAPartirDe(mes('2026-06'), reais('70,00'));

    expect(valoresPorMes($recorrencia, '2026-05', '2026-10'))->toBe([
        '2026-05' => 'R$ 50,00',
        '2026-06' => 'R$ 70,00',
        '2026-07' => 'R$ 70,00',
        '2026-08' => 'R$ 70,00',
        '2026-09' => 'R$ 70,00',
        '2026-10' => 'R$ 70,00',
    ]);
});

it('CA-04.3 mantém o valor de um mês editado à mão quando o padrão muda', function () {
    $recorrencia = internet()
        ->comValorNoMes(mes('2026-08'), reais('55,00'))
        ->alterarValorAPartirDe(mes('2026-06'), reais('70,00'));

    expect($recorrencia->ocorrenciaEm(mes('2026-08'))?->valor->formatar())->toBe('R$ 55,00')
        ->and($recorrencia->ocorrenciaEm(mes('2026-09'))?->valor->formatar())->toBe('R$ 70,00');
});

it('CA-04.3 permite trocar o valor desde o primeiro mês', function () {
    $recorrencia = internet()->alterarValorAPartirDe(mes('2026-01'), reais('45,00'));

    expect($recorrencia->ocorrenciaEm(mes('2026-01'))?->valor->formatar())->toBe('R$ 45,00')
        ->and($recorrencia->vigencias)->toHaveCount(1);
});

it('CA-04.4 some nos meses depois do encerramento', function () {
    $recorrencia = internet()->encerrarEm(mes('2026-08'));

    expect(valoresPorMes($recorrencia, '2026-07', '2026-10'))->toBe([
        '2026-07' => 'R$ 50,00',
        '2026-08' => 'R$ 50,00',
        '2026-09' => null,
        '2026-10' => null,
    ]);
});

it('CA-04.4 descarta edições de meses depois do encerramento', function () {
    $recorrencia = internet()
        ->comValorNoMes(mes('2026-10'), reais('80,00'))
        ->encerrarEm(mes('2026-08'));

    expect($recorrencia->excecoes)->toBeEmpty();
});

it('CA-04.5 gera exatamente uma despesa por mês para cada recorrência', function () {
    $recorrencias = [
        internet()->comValorNoMes(mes('2026-03'), reais('60,00')),
        Recorrencia::nova('Aluguel', reais('1.200,00'), mes('2025-01'), id: 8),
        Recorrencia::nova('Academia', reais('120,00'), mes('2026-04'), id: 9),
        Recorrencia::nova('Curso', reais('300,00'), mes('2025-01'), mes('2026-02'), id: 10),
    ];

    $ocorrencias = (new ResolvedorRecorrencia)->ocorrenciasDoMes(mes('2026-03'), $recorrencias);
    $ids = array_map(fn (OcorrenciaRecorrencia $o) => $o->recorrenciaId, $ocorrencias);

    expect($ids)->toBe([7, 8])
        ->and(array_unique($ids))->toHaveCount(count($ids))
        ->and($ocorrencias[0]->valor->formatar())->toBe('R$ 60,00');
});

it('CA-04.5 recusa duas vigências começando no mesmo mês', function () {
    Recorrencia::reconstituir(1, 'Internet', mes('2026-01'), null, [
        new Vigencia(mes('2026-01'), reais('50,00')),
        new Vigencia(mes('2026-01'), reais('60,00')),
    ]);
})->throws(InvalidArgumentException::class, 'Duas vigências');

it('vira despesa para o resumo do mês', function () {
    $despesa = internet()->ocorrenciaEm(mes('2026-05'))->paraDespesa();

    expect($despesa->descricao)->toBe('Internet')
        ->and($despesa->valor->formatar())->toBe('R$ 50,00');
});

it('reconstitui a partir do que está gravado, em qualquer ordem', function () {
    $recorrencia = Recorrencia::reconstituir(3, 'Luz', mes('2026-01'), null, [
        new Vigencia(mes('2026-07'), reais('180,00')),
        new Vigencia(mes('2026-01'), reais('150,00')),
    ], ['2026-03' => reais('170,00')]);

    expect(valoresPorMes($recorrencia, '2026-02', '2026-03'))->toBe(['2026-02' => 'R$ 150,00', '2026-03' => 'R$ 170,00'])
        ->and($recorrencia->ocorrenciaEm(mes('2026-07'))?->valor->formatar())->toBe('R$ 180,00');
});

it('renomeia mantendo valores e edições', function () {
    $recorrencia = internet()->comValorNoMes(mes('2026-03'), reais('60,00'))->renomear('Internet fibra');

    expect($recorrencia->ocorrenciaEm(mes('2026-03'))?->descricao)->toBe('Internet fibra')
        ->and($recorrencia->ocorrenciaEm(mes('2026-03'))?->valor->formatar())->toBe('R$ 60,00');
});

it('recusa recorrência inválida', function (Closure $criar, string $mensagem) {
    expect($criar)->toThrow(InvalidArgumentException::class, $mensagem);
})->with([
    'sem descrição' => [fn () => Recorrencia::nova('  ', reais('50,00'), mes('2026-01')), 'descrição'],
    'valor zero' => [fn () => Recorrencia::nova('Internet', reais('0,00'), mes('2026-01')), 'maior que zero'],
    'fim antes do início' => [fn () => Recorrencia::nova('Internet', reais('50,00'), mes('2026-05'), mes('2026-04')), 'antes do início'],
    'sem vigência' => [fn () => Recorrencia::reconstituir(1, 'Internet', mes('2026-01'), null, []), 'primeira vigência'],
    'primeira vigência depois do início' => [fn () => Recorrencia::reconstituir(1, 'Internet', mes('2026-01'), null, [new Vigencia(mes('2026-02'), reais('50,00'))]), 'primeira vigência'],
    'edição fora do período' => [fn () => internet('2026-06')->comValorNoMes(mes('2026-07'), reais('60,00')), 'não está ativa em 07/2026'],
    'edição antes do início' => [fn () => internet()->comValorNoMes(mes('2025-12'), reais('60,00')), 'não está ativa'],
    'edição com valor zero' => [fn () => internet()->comValorNoMes(mes('2026-03'), reais('0,00')), 'maior que zero'],
    'mudança de valor antes do início' => [fn () => internet()->alterarValorAPartirDe(mes('2025-12'), reais('60,00')), 'antes do início'],
    'encerrar antes do início' => [fn () => internet()->encerrarEm(mes('2025-12')), 'antes do início'],
]);

it('é imutável', function () {
    $original = internet();
    $original->comValorNoMes(mes('2026-03'), reais('60,00'))->encerrarEm(mes('2026-05'));

    expect($original->ocorrenciaEm(mes('2026-03'))?->valor->formatar())->toBe('R$ 50,00')
        ->and($original->fim)->toBeNull();
});

it('não aparece antes do início nem é afetado por competências distantes', function (string $competencia, bool $ativa) {
    expect(internet('2027-12')->ativaEm(Competencia::deTexto($competencia)))->toBe($ativa);
})->with([
    ['2025-12', false],
    ['2026-01', true],
    ['2027-12', true],
    ['2028-01', false],
]);
