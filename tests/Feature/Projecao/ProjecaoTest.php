<?php

use App\Domain\Parcelamento\Parcelamento;
use App\Domain\Recorrencia\Recorrencia;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Models\Lancamento;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// T16 — RF-08: meses futuros montados a partir das regras, sem copiar nada.

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-03-10 12:00:00'));
    $this->actingAs(User::factory()->create());
});

/**
 * Aluguel sem fim desde jan/2026; Internet encerrada em dez/2026; Notebook em 24× desde jun/2026;
 * uma despesa avulsa em mar/2026.
 */
function cenarioDeProjecao(): void
{
    $recorrencias = app(RepositorioRecorrencias::class);
    $recorrencias->salvar(Recorrencia::nova('Aluguel', reais('1.200,00'), mes('2026-01')));
    $recorrencias->salvar(Recorrencia::nova('Internet', reais('100,00'), mes('2026-01'), mes('2026-12')));

    app(RepositorioParcelamentos::class)->salvar(Parcelamento::novo('Notebook', reais('150,00'), 24, mes('2026-06')));

    Lancamento::query()->create([
        'tipo' => 'despesa', 'origem' => 'avulso', 'competencia' => '2026-03', 'descricao' => 'Presente', 'valor_centavos' => 9000,
    ]);
}

/**
 * @return array<string, int>
 */
function contagemDasTabelas(): array
{
    $tabelas = ['lancamentos', 'recorrencias', 'recorrencia_valores', 'recorrencia_ocorrencias', 'parcelamentos', 'movimentos_guardado', 'configuracoes'];

    return collect($tabelas)->mapWithKeys(fn (string $t) => [$t => DB::table($t)->count()])->all();
}

describe('CA-08.1 mês futuro mostra recorrências e parcelas sozinho', function () {
    it('monta um mês de um ano nunca aberto', function () {
        cenarioDeProjecao();

        // 2027-05 = parcela 12 de 24 (jun/2026 + 11 meses).
        $this->get('/mes/2027-05')->assertOk()
            ->assertSee('Aluguel')
            ->assertSee('Notebook (12/24)')
            ->assertDontSee('Internet')
            ->assertDontSee('Presente');
    });

    it('para de mostrar a parcela depois da última e a fixa depois do fim', function () {
        cenarioDeProjecao();

        $this->get('/mes/2028-05')->assertSee('Notebook (24/24)');
        $this->get('/mes/2028-06')->assertSee('Aluguel')->assertDontSee('Notebook');
        $this->get('/mes/2026-12')->assertSee('Internet');
        $this->get('/mes/2027-01')->assertDontSee('Internet');
    });

    it('mostra também o que foi lançado à mão num mês futuro', function () {
        cenarioDeProjecao();
        Lancamento::query()->create([
            'tipo' => 'despesa', 'origem' => 'avulso', 'competencia' => '2027-05', 'descricao' => 'Viagem planejada', 'valor_centavos' => 90000,
        ]);

        $this->get('/mes/2027-05')->assertSee('Aluguel')->assertSee('Notebook (12/24)')->assertSee('Viagem planejada');
    });

    it('traz os totais projetados na visão anual de um ano distante', function () {
        cenarioDeProjecao();

        $html = $this->get('/ano/2031')->assertOk()->getContent();

        expect($html)->toMatch('/id="mes-7".*?data-campo="contas"[^>]*>R\$ 1\.200,00</s')
            ->and($html)->toMatch('/id="ano-contas"[^>]*>R\$ 14\.400,00</');
    });
});

describe('CA-08.2 meses futuros são marcados como projeção até chegarem', function () {
    it('marca só os meses depois do atual', function () {
        $this->get('/mes/2026-03')->assertDontSee('Projeção.');
        $this->get('/mes/2026-02')->assertDontSee('Projeção.');
        $this->get('/mes/2026-04')->assertSee('Projeção.');
        $this->get('/mes/2040-01')->assertSee('Projeção.');
    });

    it('deixa de ser projeção quando o mês chega', function () {
        $this->get('/mes/2026-04')->assertSee('Projeção.');

        $this->app->instance(Relogio::class, RelogioFixo::em('2026-04-01 00:00:01'));

        $this->get('/mes/2026-04')->assertDontSee('Projeção.');
        $this->get('/mes/2026-05')->assertSee('Projeção.');
    });

    it('marca na visão anual exatamente os meses futuros', function () {
        $html = $this->get('/ano/2026')->getContent();

        expect(substr_count($html, '>projeção<'))->toBe(9); // abril a dezembro
    });
});

describe('CA-08.3 não existe "criar ano"', function () {
    it('navega por meses e anos distantes sem gravar nada no banco', function () {
        cenarioDeProjecao();
        $antes = contagemDasTabelas();

        foreach (['/mes/2027-05', '/mes/2035-11', '/mes/2099-01', '/ano/2027', '/ano/2050', '/ano/2099'] as $url) {
            $this->get($url)->assertOk();
        }

        expect(contagemDasTabelas())->toBe($antes);
    });

    it('não oferece criar ou copiar ano', function () {
        $html = $this->get('/ano/2027')->getContent().$this->get('/mais')->getContent();

        expect(mb_strtolower($html))->not->toContain('criar ano')
            ->and(mb_strtolower($html))->not->toContain('copiar ano');
    });

    it('vai de ano em ano pela navegação', function () {
        $this->get('/ano/2026')->assertSee(route('ano', 2027), escape: false);
        $this->get('/ano/2027')->assertSee(route('ano', 2028), escape: false);
    });
});
