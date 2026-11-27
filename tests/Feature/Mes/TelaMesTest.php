<?php

use App\Domain\Parcelamento\Parcelamento;
use App\Domain\Recorrencia\Recorrencia;
use App\Infra\Persistencia\Configuracoes;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Livewire\TelaMes;
use App\Models\Lancamento;
use App\Models\MovimentoGuardado;
use App\Models\User;
use Livewire\Livewire;

// T11 — tela do mês: RF-01 (despesas), RF-02 (receitas), RF-03 (resumo).

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-03-10 12:00:00'));
    $this->actingAs(User::factory()->create());
});

function telaDoMes(string $competencia = '2026-03', ?string $novo = null)
{
    return Livewire::test(TelaMes::class, ['competencia' => $competencia, 'novo' => $novo]);
}

function lancar($tela, string $tipo, string $descricao, string $valor, string $categoria = 'salario')
{
    return $tela->call('abrir', $tipo)
        ->set('descricao', $descricao)
        ->set('valor', $valor)
        ->set('categoria', $categoria)
        ->call('salvar');
}

function totalNaTela($tela, string $id): string
{
    preg_match('/id="'.$id.'"[^>]*>([^<]+)</', $tela->html(), $m);

    return trim(html_entity_decode($m[1] ?? ''));
}

describe('RF-01 despesas', function () {
    it('CA-01.1 lança uma despesa e soma em Contas a pagar', function () {
        $tela = lancar(telaDoMes('2025-10'), 'despesa', 'Aluguel', '1.200,00')->assertHasNoErrors();

        $tela->assertSee('Aluguel');
        expect(totalNaTela($tela, 'total-contas'))->toBe('R$ 1.200,00');

        $tela = lancar($tela, 'despesa', 'Internet', '99,90');
        expect(totalNaTela($tela, 'total-contas'))->toBe('R$ 1.299,90')
            ->and(Lancamento::query()->where('competencia', '2025-10')->count())->toBe(2);
    });

    it('CA-01.1 grava a despesa no mês da tela, como avulsa', function () {
        lancar(telaDoMes('2025-10'), 'despesa', 'Aluguel', '1.200,00');

        expect(Lancamento::query()->first()->only(['tipo', 'origem', 'descricao', 'valor_centavos']))
            ->toBe(['tipo' => 'despesa', 'origem' => 'avulso', 'descricao' => 'Aluguel', 'valor_centavos' => 120000])
            ->and((string) Lancamento::query()->first()->competencia)->toBe('2025-10');
    });

    it('CA-01.2 recusa lançamento sem descrição ou com valor inválido', function (string $descricao, string $valor, string $campo, string $mensagem) {
        lancar(telaDoMes(), 'despesa', $descricao, $valor)
            ->assertHasErrors([$campo])
            ->assertSee($mensagem);

        expect(Lancamento::query()->count())->toBe(0);
    })->with([
        'sem descrição' => ['', '50,00', 'descricao', 'Informe a descrição.'],
        'só espaços' => ['   ', '50,00', 'descricao', 'Informe a descrição.'],
        'sem valor' => ['Luz', '', 'valor', 'Informe o valor.'],
        'valor zero' => ['Luz', '0,00', 'valor', 'O valor deve ser maior que zero.'],
        'valor negativo' => ['Luz', '-10,00', 'valor', 'O valor deve ser maior que zero.'],
        'valor que não é número' => ['Luz', 'abc', 'valor', 'Valor inválido. Use o formato 1.234,56.'],
    ]);

    it('CA-01.2 mantém o formulário aberto com o que foi digitado', function () {
        lancar(telaDoMes(), 'despesa', 'Luz', '0')
            ->assertSet('formulario', 'despesa')
            ->assertSet('descricao', 'Luz');
    });

    it('CA-01.3 edita o valor e recalcula Contas a pagar e Restante', function () {
        $tela = lancar(telaDoMes(), 'receita', 'Salário', '3.000,00');
        $tela = lancar($tela, 'despesa', 'Mercado', '500,00');
        $id = Lancamento::query()->where('descricao', 'Mercado')->value('id');

        $tela->call('editar', $id)
            ->assertSet('valor', '500,00')
            ->set('valor', '650,00')
            ->call('salvar')
            ->assertHasNoErrors();

        expect(totalNaTela($tela, 'total-contas'))->toBe('R$ 650,00')
            ->and(totalNaTela($tela, 'total-restante'))->toBe('R$ 2.350,00')
            ->and(Lancamento::query()->count())->toBe(2);
    });

    it('CA-01.3 exclui e recalcula Contas a pagar e Restante', function () {
        $tela = lancar(telaDoMes(), 'receita', 'Salário', '3.000,00');
        $tela = lancar($tela, 'despesa', 'Mercado', '500,00');
        $tela = lancar($tela, 'despesa', 'Luz', '150,00');

        $tela->call('excluir', Lancamento::query()->where('descricao', 'Mercado')->value('id'));

        expect(totalNaTela($tela, 'total-contas'))->toBe('R$ 150,00')
            ->and(totalNaTela($tela, 'total-restante'))->toBe('R$ 2.850,00');
        $tela->assertDontSee('Mercado');
    });

    it('não edita nem exclui parcelas ou lançamentos de outro mês por esta tela', function () {
        app(RepositorioParcelamentos::class)->salvar(Parcelamento::novo('Viagem', reais('250,00'), 2, mes('2026-03')));
        $parcela = Lancamento::query()->where('competencia', '2026-03')->first();
        $outroMes = Lancamento::query()->create([
            'tipo' => 'despesa', 'origem' => 'avulso', 'competencia' => '2026-04', 'descricao' => 'Abril', 'valor_centavos' => 100,
        ]);

        telaDoMes()
            ->call('editar', $parcela->id)->assertSet('editandoId', null)
            ->call('excluir', $parcela->id)
            ->call('excluir', $outroMes->id);

        expect(Lancamento::query()->count())->toBe(3);
    });
});

describe('RF-02 receitas', function () {
    it('CA-02.1 soma Salário e Comissão em Recebidos', function () {
        $tela = lancar(telaDoMes('2025-10'), 'receita', 'Salário', '4.000,00', 'salario');
        $tela = lancar($tela, 'receita', 'Comissão', '200,00', 'comissao')->assertHasNoErrors();

        expect(totalNaTela($tela, 'total-recebidos'))->toBe('R$ 4.200,00');
        expect(mb_strpos($tela->html(), 'Salário'))->toBeLessThan(mb_strpos($tela->html(), 'Comissão'));
        expect(Lancamento::query()->pluck('categoria_receita')->all())->toBe(['salario', 'comissao']);
    });

    it('CA-02.2 soma várias receitas da mesma categoria', function () {
        $tela = lancar(telaDoMes(), 'receita', 'Adiantamento', '1.500,00', 'outros');
        $tela = lancar($tela, 'receita', 'Restante das férias', '800,00', 'outros');

        expect(totalNaTela($tela, 'total-recebidos'))->toBe('R$ 2.300,00');
    });

    it('edita a categoria de uma receita', function () {
        $tela = lancar(telaDoMes(), 'receita', 'Bônus', '300,00', 'salario');
        $id = Lancamento::query()->value('id');

        $tela->call('editar', $id)->assertSet('categoria', 'salario')
            ->set('categoria', 'comissao')->call('salvar')->assertHasNoErrors();

        expect(Lancamento::query()->value('categoria_receita'))->toBe('comissao');
    });

    it('recusa categoria de receita inválida', function () {
        lancar(telaDoMes(), 'receita', 'Bônus', '300,00', 'loteria')->assertHasErrors('categoria');

        expect(Lancamento::query()->count())->toBe(0);
    });
});

describe('RF-03 resumo do mês', function () {
    it('CA-03.1 mostra Contas a pagar, Recebidos, Guardado no mês e Restante', function () {
        MovimentoGuardado::query()->create(['competencia' => '2026-03', 'tipo' => 'guardar', 'valor_centavos' => 20000]);
        $tela = lancar(telaDoMes(), 'despesa', 'Aluguel', '2.000,00');
        $tela = lancar($tela, 'despesa', 'Mercado', '800,00');
        $tela = lancar($tela, 'receita', 'Salário', '4.000,00');

        expect(totalNaTela($tela, 'total-contas'))->toBe('R$ 2.800,00')
            ->and(totalNaTela($tela, 'total-recebidos'))->toBe('R$ 4.000,00')
            ->and(totalNaTela($tela, 'total-guardado-mes'))->toBe('R$ 200,00')
            ->and(totalNaTela($tela, 'total-restante'))->toBe('R$ 1.000,00');
    });

    it('CA-03.1 inclui despesas fixas e parcelas do mês no resumo', function () {
        app(RepositorioRecorrencias::class)->salvar(Recorrencia::nova('Internet', reais('100,00'), mes('2026-01')));
        app(RepositorioParcelamentos::class)->salvar(Parcelamento::novo('Viagem', reais('250,00'), 6, mes('2026-02')));

        $tela = telaDoMes()->assertSee('Internet')->assertSee('Viagem (2/6)')->assertSee('fixa')->assertSee('parcela');

        expect(totalNaTela($tela, 'total-contas'))->toBe('R$ 350,00');
    });

    it('CA-03.2 não oferece campo para digitar totais', function () {
        $tela = lancar(telaDoMes(), 'despesa', 'Luz', '100,00')->call('abrir', 'despesa');

        expect($tela->html())->not->toMatch('/<input[^>]+(contas|recebidos|restante|guardado)/i');

        $publicas = array_keys(get_class_vars(TelaMes::class));
        expect($publicas)->not->toContain('contasAPagar', 'recebidos', 'restante', 'totalGuardado');
    });

    it('CA-03.3 destaca o Restante negativo', function () {
        $tela = lancar(telaDoMes(), 'despesa', 'Aluguel', '1.200,00');

        expect(totalNaTela($tela, 'total-restante'))->toBe('-R$ 1.200,00')
            ->and($tela->html())->toContain('data-negativo')
            ->and($tela->html())->toMatch('/id="total-restante"[^>]*class="[^"]*text-red-700/');

        $tela = lancar($tela, 'receita', 'Salário', '3.000,00');
        expect($tela->html())->not->toContain('data-negativo');
    });

    it('CA-03.4 não leva o Restante de um mês para o seguinte', function () {
        lancar(telaDoMes('2026-02'), 'receita', 'Salário', '3.000,00');

        $marco = lancar(telaDoMes('2026-03'), 'despesa', 'Aluguel', '1.000,00');

        expect(totalNaTela($marco, 'total-restante'))->toBe('-R$ 1.000,00');
    });

    it('CA-03.5 mostra o total guardado até o mês', function () {
        app(Configuracoes::class)->definirGuardadoInicial(reais('1.000,00'));
        MovimentoGuardado::query()->create(['competencia' => '2026-01', 'tipo' => 'guardar', 'valor_centavos' => 30000]);
        MovimentoGuardado::query()->create(['competencia' => '2026-04', 'tipo' => 'guardar', 'valor_centavos' => 50000]);

        expect(totalNaTela(telaDoMes('2026-03'), 'total-guardado'))->toBe('R$ 1.300,00')
            ->and(totalNaTela(telaDoMes('2026-04'), 'total-guardado'))->toBe('R$ 1.800,00');
    });
});

describe('formulário', function () {
    it('abre o formulário pedido pelo botão +', function (string $novo) {
        telaDoMes(novo: $novo)->assertSet('formulario', $novo)->assertSee($novo === 'receita' ? 'Nova receita' : 'Nova despesa');
    })->with(['despesa', 'receita']);

    it('ignora pedido de formulário desconhecido', function () {
        telaDoMes(novo: 'qualquer')->assertSet('formulario', null);
    });

    it('fecha e limpa o formulário depois de salvar e ao cancelar', function () {
        lancar(telaDoMes(), 'despesa', 'Luz', '100,00')
            ->assertSet('formulario', null)
            ->assertSet('descricao', '')
            ->call('abrir', 'despesa')->set('descricao', 'Rascunho')
            ->call('cancelar')
            ->assertSet('formulario', null)
            ->assertSet('descricao', '');
    });

    it('mostra os estados vazios', function () {
        telaDoMes()->assertSee('Nenhuma despesa neste mês.')->assertSee('Nenhuma receita neste mês.');
    });

    it('avisa quando o mês é projeção e permite lançar nele', function () {
        lancar(telaDoMes('2026-08'), 'despesa', 'Viagem planejada', '900,00')
            ->assertSee('Projeção.')
            ->assertSee('Viagem planejada');

        telaDoMes('2026-03')->assertDontSee('Projeção.');
    });
});

it('a página do mês carrega o componente e abre o formulário pelo ?novo=', function () {
    $this->get('/mes/2026-03?novo=receita')
        ->assertOk()
        ->assertSeeLivewire(TelaMes::class)
        ->assertSee('Nova receita');
});
