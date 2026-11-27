<?php

use App\Infra\Persistencia\Configuracoes;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Livewire\TelaGuardadoInicial;
use App\Livewire\TelaMes;
use App\Models\MovimentoGuardado;
use App\Models\User;
use Livewire\Livewire;

// T14 — RF-06 pela tela: guardar, retirar e guardado inicial.

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-03-10 12:00:00'));
    $this->actingAs(User::factory()->create());
});

function movimentar(string $competencia, string $tipo, string $valor, string $descricao = '')
{
    return Livewire::test(TelaMes::class, ['competencia' => $competencia])
        ->call('abrir', 'guardado')
        ->set('tipoMovimento', $tipo)
        ->set('valor', $valor)
        ->set('descricao', $descricao)
        ->call('salvar');
}

function noMes($tela, string $id): string
{
    preg_match('/id="'.$id.'"[^>]*>([^<]+)</', $tela->html(), $m);

    return trim(html_entity_decode($m[1] ?? ''));
}

function telaMesGuardado(string $competencia)
{
    return Livewire::test(TelaMes::class, ['competencia' => $competencia]);
}

describe('CA-06.1 guardar e retirar no mês', function () {
    it('registra movimentos de guardar e de retirar, com descrição opcional', function () {
        movimentar('2026-03', 'guardar', '300,00', 'CDB')->assertHasNoErrors()->assertSet('formulario', null);
        $tela = movimentar('2026-03', 'retirar', '100,00');

        $tela->assertSee('CDB')->assertSee('Retirado');
        expect(noMes($tela, 'total-guardado-mes'))->toBe('R$ 200,00')
            ->and(MovimentoGuardado::query()->pluck('tipo')->all())->toBe(['guardar', 'retirar'])
            ->and(MovimentoGuardado::query()->where('tipo', 'retirar')->value('descricao'))->toBeNull();
    });

    it('abre pelo botão + da navegação', function () {
        Livewire::test(TelaMes::class, ['competencia' => '2026-03', 'novo' => 'guardado'])
            ->assertSet('formulario', 'guardado')
            ->assertSee('Guardar ou retirar')
            ->assertSee('Guardei')
            ->assertSee('Retirei');
    });

    it('edita um movimento', function () {
        movimentar('2026-03', 'guardar', '300,00', 'CDB');
        $id = MovimentoGuardado::query()->value('id');

        $tela = telaMesGuardado('2026-03')
            ->call('editarMovimento', $id)
            ->assertSet('tipoMovimento', 'guardar')
            ->assertSet('valor', '300,00')
            ->assertSet('descricao', 'CDB')
            ->set('valor', '350,00')
            ->call('salvar')
            ->assertHasNoErrors();

        expect(noMes($tela, 'total-guardado-mes'))->toBe('R$ 350,00');
    });

    it('exclui um movimento', function () {
        movimentar('2026-03', 'guardar', '300,00', 'CDB');

        $tela = telaMesGuardado('2026-03')->call('excluirMovimento', MovimentoGuardado::query()->value('id'));

        $tela->assertSee('Nada guardado nem retirado neste mês.');
        expect(MovimentoGuardado::query()->count())->toBe(0);
    });

    it('valida o valor', function (string $valor) {
        movimentar('2026-03', 'guardar', $valor)->assertHasErrors(['valor']);

        expect(MovimentoGuardado::query()->count())->toBe(0);
    })->with(['', '0', '-10,00', 'abc']);

    it('recusa tipo de movimento desconhecido', function () {
        movimentar('2026-03', 'gastar', '10,00')->assertHasErrors(['tipoMovimento']);
    });
});

it('CA-06.2 o total guardado acumula mês a mês', function () {
    movimentar('2026-01', 'guardar', '100,00');
    movimentar('2026-03', 'guardar', '250,00');

    expect(noMes(telaMesGuardado('2026-01'), 'total-guardado'))->toBe('R$ 100,00')
        ->and(noMes(telaMesGuardado('2026-02'), 'total-guardado'))->toBe('R$ 100,00')
        ->and(noMes(telaMesGuardado('2026-03'), 'total-guardado'))->toBe('R$ 350,00');
});

it('CA-06.3 o total guardado continua na virada do ano', function () {
    movimentar('2025-12', 'guardar', '1.000,00');
    movimentar('2026-01', 'guardar', '100,00');

    expect(noMes(telaMesGuardado('2026-01'), 'total-guardado'))->toBe('R$ 1.100,00');
});

it('CA-06.4 retirar aumenta o Restante do mês', function () {
    movimentar('2026-01', 'guardar', '1.000,00');
    $tela = Livewire::test(TelaMes::class, ['competencia' => '2026-03'])
        ->call('abrir', 'receita')->set('descricao', 'Salário')->set('valor', '4.000,00')->call('salvar')
        ->call('abrir', 'despesa')->set('descricao', 'Contas')->set('valor', '3.000,00')->call('salvar')
        ->call('abrir', 'guardado')->set('tipoMovimento', 'retirar')->set('valor', '500,00')->call('salvar');

    expect(noMes($tela, 'total-guardado-mes'))->toBe('-R$ 500,00')
        ->and(noMes($tela, 'total-restante'))->toBe('R$ 1.500,00');
});

describe('CA-06.5 o total guardado nunca fica negativo', function () {
    it('recusa retirada maior que o total do mês', function () {
        movimentar('2026-01', 'guardar', '300,00');

        movimentar('2026-02', 'retirar', '300,01')
            ->assertHasErrors(['valor'])
            ->assertSee('O total guardado ficaria negativo em 02/2026 (-R$ 0,01).')
            ->assertSet('formulario', 'guardado');

        expect(MovimentoGuardado::query()->count())->toBe(1);
    });

    it('recusa retirada que deixaria um mês seguinte negativo', function () {
        movimentar('2026-01', 'guardar', '500,00');
        movimentar('2026-03', 'retirar', '400,00');

        movimentar('2026-02', 'retirar', '200,00')
            ->assertHasErrors(['valor'])
            ->assertSee('negativo em 03/2026');

        expect(MovimentoGuardado::query()->count())->toBe(2);
    });

    it('recusa editar um guardar para menos do que já foi retirado depois', function () {
        movimentar('2026-01', 'guardar', '500,00');
        movimentar('2026-03', 'retirar', '400,00');
        $id = MovimentoGuardado::query()->where('tipo', 'guardar')->value('id');

        telaMesGuardado('2026-01')
            ->call('editarMovimento', $id)->set('valor', '100,00')->call('salvar')
            ->assertHasErrors(['valor']);

        expect(MovimentoGuardado::query()->find($id)->valor_centavos)->toBe(50000);
    });

    it('recusa excluir um guardar antigo que deixaria meses seguintes negativos', function () {
        movimentar('2026-01', 'guardar', '500,00');
        movimentar('2026-03', 'retirar', '400,00');
        $id = MovimentoGuardado::query()->where('tipo', 'guardar')->value('id');

        telaMesGuardado('2026-01')->call('excluirMovimento', $id)
            ->assertHasErrors(['movimentos'])
            ->assertSee('Não dá para excluir: o total guardado ficaria negativo em 03/2026');

        expect(MovimentoGuardado::query()->count())->toBe(2);
    });

    it('aceita retirar tudo até zerar', function () {
        movimentar('2026-01', 'guardar', '300,00');

        movimentar('2026-02', 'retirar', '300,00')->assertHasNoErrors();

        expect(noMes(telaMesGuardado('2026-02'), 'total-guardado'))->toBe('R$ 0,00');
    });
});

describe('CA-06.6 guardado inicial', function () {
    it('define o guardado inicial, que entra no total de todos os meses', function () {
        Livewire::test(TelaGuardadoInicial::class)
            ->assertSet('valor', '0,00')
            ->set('valor', '2.000')
            ->call('salvar')
            ->assertHasNoErrors()
            ->assertSet('valor', '2.000,00')
            ->assertSee('Guardado inicial atualizado.');

        expect(app(Configuracoes::class)->guardadoInicial()->formatar())->toBe('R$ 2.000,00')
            ->and(noMes(telaMesGuardado('2020-01'), 'total-guardado'))->toBe('R$ 2.000,00');
    });

    it('permite retirar do guardado inicial', function () {
        app(Configuracoes::class)->definirGuardadoInicial(reais('1.000,00'));

        movimentar('2026-03', 'retirar', '400,00')->assertHasNoErrors();

        expect(noMes(telaMesGuardado('2026-03'), 'total-guardado'))->toBe('R$ 600,00');
    });

    it('mostra o total guardado hoje', function () {
        app(Configuracoes::class)->definirGuardadoInicial(reais('1.000,00'));
        movimentar('2026-02', 'guardar', '200,00');
        movimentar('2026-05', 'guardar', '999,00');

        $html = Livewire::test(TelaGuardadoInicial::class)->html();

        expect($html)->toMatch('/id="total-guardado-atual"[^>]*>R\$ 1\.200,00</');
    });

    it('recusa diminuir o inicial abaixo do que já foi retirado', function () {
        app(Configuracoes::class)->definirGuardadoInicial(reais('1.000,00'));
        movimentar('2026-03', 'retirar', '800,00');

        Livewire::test(TelaGuardadoInicial::class)
            ->set('valor', '700,00')->call('salvar')
            ->assertHasErrors(['valor'])
            ->assertSee('negativo em 03/2026');

        expect(app(Configuracoes::class)->guardadoInicial()->formatar())->toBe('R$ 1.000,00');
    });

    it('aceita zero e vazio, e recusa valor inválido ou negativo', function () {
        Livewire::test(TelaGuardadoInicial::class)->set('valor', '')->call('salvar')->assertHasNoErrors();
        Livewire::test(TelaGuardadoInicial::class)->set('valor', '0')->call('salvar')->assertHasNoErrors();
        Livewire::test(TelaGuardadoInicial::class)->set('valor', 'abc')->call('salvar')->assertHasErrors(['valor']);
        Livewire::test(TelaGuardadoInicial::class)->set('valor', '-5,00')->call('salvar')->assertHasErrors(['valor']);
    });

    it('a página abre pelo menu Mais', function () {
        $this->get('/mais')->assertSee(route('configuracoes.guardado'), escape: false);
        $this->get('/configuracoes/guardado')->assertOk()->assertSeeLivewire(TelaGuardadoInicial::class);
    });
});
