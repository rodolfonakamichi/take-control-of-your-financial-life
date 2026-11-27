<?php

use App\Application\Mes\MontarMes;
use App\Domain\Parcelamento\Parcelamento;
use App\Domain\Recorrencia\Recorrencia;
use App\Infra\Persistencia\Configuracoes;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Models\Lancamento;
use App\Models\MovimentoGuardado;
use App\Models\User;

// T15 — RF-07: visão anual.

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-03-10 12:00:00'));
    $this->actingAs(User::factory()->create());
});

/**
 * Aluguel fixo de R$ 1.000 desde jan/2026; parcelamento 3× R$ 200 de nov/2026 a jan/2027;
 * salário de R$ 3.000 em jan, fev e mar; guardado inicial R$ 500, +R$ 100 em fev, −R$ 50 em dez.
 */
function anoDeExemplo(): void
{
    app(RepositorioRecorrencias::class)->salvar(Recorrencia::nova('Aluguel', reais('1.000,00'), mes('2026-01')));
    app(RepositorioParcelamentos::class)->salvar(Parcelamento::novo('Geladeira', reais('200,00'), 3, mes('2026-11')));

    foreach (['2026-01', '2026-02', '2026-03'] as $m) {
        Lancamento::query()->create([
            'tipo' => 'receita', 'origem' => 'avulso', 'categoria_receita' => 'salario',
            'competencia' => $m, 'descricao' => 'Salário', 'valor_centavos' => 300000,
        ]);
    }

    app(Configuracoes::class)->definirGuardadoInicial(reais('500,00'));
    MovimentoGuardado::query()->create(['competencia' => '2026-02', 'tipo' => 'guardar', 'valor_centavos' => 10000]);
    MovimentoGuardado::query()->create(['competencia' => '2026-12', 'tipo' => 'retirar', 'valor_centavos' => 5000]);
}

function paginaDoAno(int $ano): DOMXPath
{
    $html = test()->get("/ano/{$ano}")->assertOk()->getContent();
    $dom = new DOMDocument;
    @$dom->loadHTML('<?xml encoding="utf-8">'.$html);

    return new DOMXPath($dom);
}

function campoDoMes(DOMXPath $xpath, int $mes, string $campo): string
{
    return trim($xpath->query("//a[@id='mes-{$mes}']//*[@data-campo='{$campo}']")->item(0)?->textContent ?? '');
}

function totalDoAno(DOMXPath $xpath, string $id): string
{
    return trim($xpath->query("//*[@id='{$id}']")->item(0)?->textContent ?? '');
}

it('CA-07.1 mostra os totais de cada um dos 12 meses', function () {
    anoDeExemplo();
    $ano = paginaDoAno(2026);

    $linha = fn (int $m) => [
        campoDoMes($ano, $m, 'contas'),
        campoDoMes($ano, $m, 'recebidos'),
        campoDoMes($ano, $m, 'guardado'),
        campoDoMes($ano, $m, 'restante'),
        campoDoMes($ano, $m, 'total-guardado'),
    ];

    expect($linha(1))->toBe(['R$ 1.000,00', 'R$ 3.000,00', 'R$ 0,00', 'R$ 2.000,00', 'R$ 500,00'])
        ->and($linha(2))->toBe(['R$ 1.000,00', 'R$ 3.000,00', 'R$ 100,00', 'R$ 1.900,00', 'R$ 600,00'])
        ->and($linha(3))->toBe(['R$ 1.000,00', 'R$ 3.000,00', 'R$ 0,00', 'R$ 2.000,00', 'R$ 600,00'])
        ->and($linha(7))->toBe(['R$ 1.000,00', 'R$ 0,00', 'R$ 0,00', '-R$ 1.000,00', 'R$ 600,00'])
        ->and($linha(11))->toBe(['R$ 1.200,00', 'R$ 0,00', 'R$ 0,00', '-R$ 1.200,00', 'R$ 600,00'])
        ->and($linha(12))->toBe(['R$ 1.200,00', 'R$ 0,00', '-R$ 50,00', '-R$ 1.150,00', 'R$ 550,00']);

    foreach (range(1, 12) as $m) {
        expect(campoDoMes($ano, $m, 'restante'))->not->toBe('');
    }
});

it('CA-07.1 usa os mesmos números da tela de cada mês', function () {
    anoDeExemplo();
    $ano = paginaDoAno(2026);

    foreach (range(1, 12) as $m) {
        $mes = app(MontarMes::class)->executar(mes(sprintf('2026-%02d', $m)));

        expect(campoDoMes($ano, $m, 'contas'))->toBe($mes->resumo->contasAPagar->formatar())
            ->and(campoDoMes($ano, $m, 'restante'))->toBe($mes->resumo->restante->formatar())
            ->and(campoDoMes($ano, $m, 'total-guardado'))->toBe($mes->totalGuardado->formatar());
    }
});

it('CA-07.2 mostra os totais do ano', function () {
    anoDeExemplo();
    $ano = paginaDoAno(2026);

    // Contas: 10 × 1.000 + 2 × 1.200 = 12.400; Recebidos: 9.000; Guardado: 100 − 50 = 50;
    // Restante: 9.000 − 12.400 − 50 = −3.450 (é a soma dos restantes dos meses).
    expect(totalDoAno($ano, 'ano-contas'))->toBe('R$ 12.400,00')
        ->and(totalDoAno($ano, 'ano-recebidos'))->toBe('R$ 9.000,00')
        ->and(totalDoAno($ano, 'ano-guardado'))->toBe('R$ 50,00')
        ->and(totalDoAno($ano, 'ano-restante'))->toBe('-R$ 3.450,00')
        ->and(totalDoAno($ano, 'ano-total-guardado'))->toBe('R$ 550,00');
});

it('CA-07.2 leva o parcelamento e o total guardado para o ano seguinte', function () {
    anoDeExemplo();
    $ano = paginaDoAno(2027);

    expect(campoDoMes($ano, 1, 'contas'))->toBe('R$ 1.200,00')
        ->and(campoDoMes($ano, 2, 'contas'))->toBe('R$ 1.000,00')
        ->and(campoDoMes($ano, 1, 'total-guardado'))->toBe('R$ 550,00');
});

it('CA-07.3 cada mês abre o detalhe do mês', function () {
    $ano = paginaDoAno(2026);

    foreach (range(1, 12) as $m) {
        $href = $ano->query("//a[@id='mes-{$m}']")->item(0)?->getAttribute('href');

        expect($href)->toBe(route('mes', sprintf('2026-%02d', $m)));
    }
});

it('marca os meses de projeção e o mês atual', function () {
    $ano = paginaDoAno(2026);

    $projecao = fn (int $m) => str_contains($ano->query("//a[@id='mes-{$m}']")->item(0)->textContent, 'projeção');

    expect($projecao(3))->toBeFalse()
        ->and($projecao(4))->toBeTrue()
        ->and($projecao(12))->toBeTrue()
        ->and($ano->query("//a[@id='mes-3']")->item(0)->getAttribute('aria-current'))->toBe('date')
        ->and($ano->query("//a[@aria-current='date']")->length)->toBe(1);
});

it('destaca o Restante negativo do mês e do ano', function () {
    anoDeExemplo();
    $ano = paginaDoAno(2026);

    $classe = fn (string $xp) => $ano->query($xp)->item(0)->getAttribute('class');

    expect($classe("//a[@id='mes-7']//*[@data-campo='restante']"))->toContain('text-red-700')
        ->and($classe("//a[@id='mes-1']//*[@data-campo='restante']"))->not->toContain('text-red-700')
        ->and($classe("//*[@id='ano-restante']"))->toContain('text-red-700');
});

it('mostra um ano vazio zerado', function () {
    $ano = paginaDoAno(2030);

    expect(totalDoAno($ano, 'ano-contas'))->toBe('R$ 0,00')
        ->and(totalDoAno($ano, 'ano-restante'))->toBe('R$ 0,00')
        ->and(campoDoMes($ano, 6, 'restante'))->toBe('R$ 0,00');
});
