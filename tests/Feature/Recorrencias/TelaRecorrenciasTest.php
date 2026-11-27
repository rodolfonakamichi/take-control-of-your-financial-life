<?php

use App\Application\Mes\ItemDespesa;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Livewire\TelaMes;
use App\Livewire\TelaRecorrencias;
use App\Models\Recorrencia as RecorrenciaModel;
use App\Models\User;
use Livewire\Livewire;

// T12 — RF-04 pela tela: cadastro de despesas fixas e valor só de um mês.

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-03-10 12:00:00'));
    $this->actingAs(User::factory()->create());
});

function criarFixa(string $descricao = 'Internet', string $valor = '50,00', string $inicio = '2026-01', string $fim = '')
{
    return Livewire::test(TelaRecorrencias::class)
        ->call('nova')
        ->set('descricao', $descricao)
        ->set('valor', $valor)
        ->set('inicio', $inicio)
        ->set('fim', $fim)
        ->call('salvar');
}

function idDaFixa(string $descricao = 'Internet'): int
{
    return RecorrenciaModel::query()->where('descricao', $descricao)->value('id');
}

/**
 * @return array<string, string> descrição => valor, das despesas fixas do mês
 */
function fixasDoMes(string $competencia): array
{
    $mes = Livewire::test(TelaMes::class, ['competencia' => $competencia])->viewData('mes');

    return collect($mes->despesas)
        ->filter(fn (ItemDespesa $d) => $d->origem->value === 'recorrencia')
        ->mapWithKeys(fn (ItemDespesa $d) => [$d->descricao => $d->valor->formatar()])
        ->all();
}

it('CA-04.1 cadastra uma despesa fixa que aparece em todos os meses a partir do início', function () {
    criarFixa()->assertHasNoErrors()->assertSet('acao', null)->assertSee('Internet')->assertSee('desde 01/2026, sem fim');

    expect(fixasDoMes('2026-01'))->toBe(['Internet' => 'R$ 50,00'])
        ->and(fixasDoMes('2026-07'))->toBe(['Internet' => 'R$ 50,00'])
        ->and(fixasDoMes('2028-02'))->toBe(['Internet' => 'R$ 50,00'])
        ->and(fixasDoMes('2025-12'))->toBe([]);
});

it('CA-04.1 sugere o mês atual como primeiro mês', function () {
    Livewire::test(TelaRecorrencias::class)->call('nova')->assertSet('inicio', '2026-03');
});

it('CA-04.2 altera o valor só no mês escolhido, pela tela do mês', function () {
    criarFixa();

    Livewire::test(TelaMes::class, ['competencia' => '2026-03'])
        ->call('editarRecorrencia', idDaFixa())
        ->assertSet('formulario', 'recorrencia')
        ->assertSet('valor', '50,00')
        ->assertSee('valor só em Março de 2026')
        ->set('valor', '60,00')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSee('valor só deste mês');

    expect(fixasDoMes('2026-03'))->toBe(['Internet' => 'R$ 60,00'])
        ->and(fixasDoMes('2026-02'))->toBe(['Internet' => 'R$ 50,00'])
        ->and(fixasDoMes('2026-04'))->toBe(['Internet' => 'R$ 50,00']);
});

it('CA-04.2 volta o mês ao valor padrão', function () {
    criarFixa();
    $tela = Livewire::test(TelaMes::class, ['competencia' => '2026-03'])
        ->call('editarRecorrencia', idDaFixa())->set('valor', '60,00')->call('salvar');

    $tela->call('voltarAoPadrao', idDaFixa())->assertDontSee('valor só deste mês');

    expect(fixasDoMes('2026-03'))->toBe(['Internet' => 'R$ 50,00']);
});

it('CA-04.2 valida o valor do mês', function () {
    criarFixa();

    Livewire::test(TelaMes::class, ['competencia' => '2026-03'])
        ->call('editarRecorrencia', idDaFixa())->set('valor', '0')->call('salvar')
        ->assertHasErrors(['valor']);

    expect(fixasDoMes('2026-03'))->toBe(['Internet' => 'R$ 50,00']);
});

it('CA-04.3 muda o valor padrão a partir de um mês sem mexer nos anteriores', function () {
    criarFixa();

    $tela = Livewire::test(TelaRecorrencias::class)
        ->call('alterarValor', idDaFixa())
        ->assertSet('aPartirDe', '2026-03')
        ->assertSet('valor', '50,00')
        ->set('aPartirDe', '2026-06')
        ->set('valor', '70,00')
        ->call('salvar')
        ->assertHasNoErrors();

    $tela->assertSee('R$ 70,00 a partir de 06/2026');
    expect(fixasDoMes('2026-05'))->toBe(['Internet' => 'R$ 50,00'])
        ->and(fixasDoMes('2026-06'))->toBe(['Internet' => 'R$ 70,00'])
        ->and(fixasDoMes('2027-01'))->toBe(['Internet' => 'R$ 70,00']);
});

it('CA-04.3 recusa mudar o valor antes do início', function () {
    criarFixa(inicio: '2026-01');

    Livewire::test(TelaRecorrencias::class)
        ->call('alterarValor', idDaFixa())->set('aPartirDe', '2025-12')->set('valor', '70,00')->call('salvar')
        ->assertHasErrors(['aPartirDe']);

    expect(fixasDoMes('2026-01'))->toBe(['Internet' => 'R$ 50,00']);
});

it('CA-04.4 encerra a despesa fixa', function () {
    criarFixa();

    Livewire::test(TelaRecorrencias::class)
        ->call('encerrar', idDaFixa())
        ->assertSet('ultimoMes', '2026-03')
        ->set('ultimoMes', '2026-08')
        ->call('salvar')
        ->assertHasNoErrors()
        ->assertSee('desde 01/2026 até 08/2026');

    expect(fixasDoMes('2026-08'))->toBe(['Internet' => 'R$ 50,00'])
        ->and(fixasDoMes('2026-09'))->toBe([]);
});

it('CA-04.4 cadastra já com último mês e recusa fim antes do início', function () {
    criarFixa('Curso', '300,00', '2026-01', '2026-04')->assertHasNoErrors();
    expect(fixasDoMes('2026-05'))->toBe([]);

    criarFixa('Errada', '10,00', '2026-05', '2026-04')
        ->assertHasErrors(['fim'])
        ->assertSee('O último mês não pode ser antes do primeiro.');
    expect(RecorrenciaModel::query()->where('descricao', 'Errada')->exists())->toBeFalse();
});

it('CA-04.5 a tela do mês mostra cada despesa fixa uma única vez', function () {
    criarFixa();
    criarFixa('Aluguel', '1.200,00', '2025-01');

    $despesas = Livewire::test(TelaMes::class, ['competencia' => '2026-03'])->viewData('mes')->despesas;
    $descricoes = array_map(fn (ItemDespesa $d) => $d->descricao, $despesas);

    expect($descricoes)->toBe(['Aluguel', 'Internet']);
});

it('valida o cadastro', function (array $campos, string $erro) {
    criarFixa(...$campos)->assertHasErrors([$erro]);

    expect(RecorrenciaModel::query()->count())->toBe(0);
})->with([
    'sem descrição' => [['descricao' => ' '], 'descricao'],
    'valor zero' => [['valor' => '0'], 'valor'],
    'valor inválido' => [['valor' => 'abc'], 'valor'],
    'sem início' => [['inicio' => ''], 'inicio'],
    'início inválido' => [['inicio' => '2026-13'], 'inicio'],
    'fim inválido' => [['fim' => 'março'], 'fim'],
]);

it('renomeia', function () {
    criarFixa();

    Livewire::test(TelaRecorrencias::class)
        ->call('renomear', idDaFixa())->assertSet('descricao', 'Internet')
        ->set('descricao', 'Internet fibra')->call('salvar')->assertHasNoErrors();

    expect(fixasDoMes('2026-03'))->toBe(['Internet fibra' => 'R$ 50,00']);
});

it('exclui de todos os meses', function () {
    criarFixa();

    Livewire::test(TelaRecorrencias::class)->call('excluir', idDaFixa())->assertSee('Nenhuma despesa fixa cadastrada.');

    expect(fixasDoMes('2026-03'))->toBe([])
        ->and(app(RepositorioRecorrencias::class)->todas())->toBe([]);
});

it('mostra a situação e põe as encerradas no fim', function () {
    criarFixa('Antiga', '10,00', '2025-01', '2025-12');
    criarFixa('Futura', '20,00', '2026-09');
    criarFixa('Atual', '30,00', '2026-01');

    $html = Livewire::test(TelaRecorrencias::class)->html();

    expect(mb_strpos($html, 'Atual'))->toBeLessThan(mb_strpos($html, 'Antiga'))
        ->and(mb_strpos($html, 'Futura'))->toBeLessThan(mb_strpos($html, 'Antiga'))
        ->and($html)->toContain('começa depois')
        ->and($html)->toContain('encerrada');
});

it('a página abre pelo menu Mais', function () {
    $this->get('/mais')->assertSee(route('recorrencias'), escape: false);
    $this->get('/recorrencias')->assertOk()->assertSeeLivewire(TelaRecorrencias::class);
});
