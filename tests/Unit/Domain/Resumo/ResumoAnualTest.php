<?php

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Guardado\MovimentoGuardado;
use App\Domain\Lancamento\Despesa;
use App\Domain\Lancamento\Receita;
use App\Domain\Resumo\CalculadoraResumo;
use App\Domain\Resumo\ResumoAnual;

// T04 — RD-08 (totais anuais).

function mesDe(string $competencia, string $despesa, string $receita, ?string $guardar = null)
{
    return (new CalculadoraResumo)->calcular(
        Competencia::deTexto($competencia),
        [new Despesa('Contas', Dinheiro::deTexto($despesa))],
        [new Receita('Salário', Dinheiro::deTexto($receita))],
        $guardar ? [MovimentoGuardado::guardar(Dinheiro::deTexto($guardar))] : [],
    );
}

it('soma os meses informados nos totais do ano (RD-08)', function () {
    $ano = ResumoAnual::de(2026, [
        mesDe('2026-01', '1.000,00', '3.000,00', '100,00'),
        mesDe('2026-02', '1.500,00', '3.000,00'),
        mesDe('2026-12', '2.000,00', '3.500,00', '50,00'),
    ]);

    expect($ano->contasAPagar->formatar())->toBe('R$ 4.500,00')
        ->and($ano->recebidos->formatar())->toBe('R$ 9.500,00')
        ->and($ano->guardadoNoAno->formatar())->toBe('R$ 150,00')
        ->and($ano->restante->formatar())->toBe('R$ 4.850,00');
});

it('tem os 12 meses, com vazio onde não houve resumo', function () {
    $ano = ResumoAnual::de(2026, [mesDe('2026-03', '10,00', '20,00')]);

    expect($ano->meses)->toHaveCount(12)
        ->and(array_keys($ano->meses))->toBe(range(1, 12))
        ->and($ano->mes(3)?->restante->formatar())->toBe('R$ 10,00')
        ->and($ano->mes(4))->toBeNull();
});

it('dá zero num ano sem meses', function () {
    $ano = ResumoAnual::de(2027, []);

    expect($ano->contasAPagar->ehZero())->toBeTrue()
        ->and($ano->restante->ehZero())->toBeTrue();
});

it('recusa mês de outro ano', function () {
    ResumoAnual::de(2026, [mesDe('2025-12', '10,00', '20,00')]);
})->throws(InvalidArgumentException::class, 'não pertence ao ano 2026');

it('recusa o mesmo mês duas vezes', function () {
    ResumoAnual::de(2026, [mesDe('2026-05', '10,00', '20,00'), mesDe('2026-05', '30,00', '40,00')]);
})->throws(InvalidArgumentException::class, 'informado duas vezes');
