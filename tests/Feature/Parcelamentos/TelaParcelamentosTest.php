<?php

use App\Application\Mes\ItemDespesa;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Livewire\TelaMes;
use App\Livewire\TelaParcelamentos;
use App\Models\Lancamento;
use App\Models\Parcelamento as ParcelamentoModel;
use App\Models\User;
use Livewire\Livewire;

// T13 — RF-05 pela tela: compras parceladas.

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-01-15 12:00:00'));
    $this->actingAs(User::factory()->create());
});

function criarParcelamento(string $descricao = 'Viagem', string $valor = '250,00', string $parcelas = '6', string $primeira = '2025-10')
{
    return Livewire::test(TelaParcelamentos::class)
        ->call('novo')
        ->set('descricao', $descricao)
        ->set('valor', $valor)
        ->set('parcelas', $parcelas)
        ->set('primeira', $primeira)
        ->call('salvar');
}

function idDoParcelamento(string $descricao = 'Viagem'): int
{
    return ParcelamentoModel::query()->where('descricao', $descricao)->value('id');
}

/**
 * @return array<string, string> descrição => valor, das parcelas do mês
 */
function parcelasDoMes(string $competencia): array
{
    $mes = Livewire::test(TelaMes::class, ['competencia' => $competencia])->viewData('mes');

    return collect($mes->despesas)
        ->filter(fn (ItemDespesa $d) => $d->origem->value === 'parcelamento')
        ->mapWithKeys(fn (ItemDespesa $d) => [$d->descricao => $d->valor->formatar()])
        ->all();
}

it('CA-05.1 cadastra e distribui as parcelas atravessando a virada do ano', function () {
    criarParcelamento()->assertHasNoErrors()->assertSet('acao', null)
        ->assertSee('6× de R$ 250,00 · 10/2025 a 03/2026')
        ->assertSee('Este mês: parcela 4/6')
        ->assertSee('3 parcelas a vencer');

    expect(parcelasDoMes('2025-10'))->toBe(['Viagem (1/6)' => 'R$ 250,00'])
        ->and(parcelasDoMes('2025-12'))->toBe(['Viagem (3/6)' => 'R$ 250,00'])
        ->and(parcelasDoMes('2026-03'))->toBe(['Viagem (6/6)' => 'R$ 250,00'])
        ->and(parcelasDoMes('2026-04'))->toBe([]);
});

it('CA-05.1 sugere o mês atual para a primeira parcela', function () {
    Livewire::test(TelaParcelamentos::class)->call('novo')->assertSet('primeira', '2026-01');
});

it('CA-05.2 recusa a numeração digitada na descrição', function () {
    criarParcelamento('Viagem (1/6)')
        ->assertHasErrors(['descricao'])
        ->assertSee('ela é gerada automaticamente');

    expect(ParcelamentoModel::query()->count())->toBe(0);
});

it('CA-05.3 muda o valor só das parcelas do mês atual em diante', function () {
    criarParcelamento();

    Livewire::test(TelaParcelamentos::class)
        ->call('alterarValor', idDoParcelamento())
        ->assertSet('valor', '250,00')
        ->assertSee('Vale para as parcelas de 01/2026 em diante')
        ->set('valor', '300,00')
        ->call('salvar')
        ->assertHasNoErrors();

    expect(parcelasDoMes('2025-12'))->toBe(['Viagem (3/6)' => 'R$ 250,00'])
        ->and(parcelasDoMes('2026-01'))->toBe(['Viagem (4/6)' => 'R$ 300,00'])
        ->and(parcelasDoMes('2026-03'))->toBe(['Viagem (6/6)' => 'R$ 300,00']);
});

it('CA-05.3 não muda parcela já paga', function () {
    criarParcelamento();
    $repo = app(RepositorioParcelamentos::class);
    $repo->salvar($repo->porId(idDoParcelamento())->marcarParcela(5, true));

    Livewire::test(TelaParcelamentos::class)
        ->call('alterarValor', idDoParcelamento())->set('valor', '300,00')->call('salvar');

    expect(parcelasDoMes('2026-02'))->toBe(['Viagem (5/6)' => 'R$ 250,00'])
        ->and(parcelasDoMes('2026-03'))->toBe(['Viagem (6/6)' => 'R$ 300,00']);
});

it('CA-05.4 cancela daqui para frente e mantém o passado', function () {
    criarParcelamento();

    Livewire::test(TelaParcelamentos::class)
        ->call('cancelarParcelamento', idDoParcelamento())
        ->assertSee('cancelado')
        ->assertSee('Cancelado em 15/01/2026')
        ->assertDontSee('Cancelar parcelamento');

    expect(parcelasDoMes('2025-12'))->toBe(['Viagem (3/6)' => 'R$ 250,00'])
        ->and(parcelasDoMes('2026-01'))->toBe([])
        ->and(parcelasDoMes('2026-03'))->toBe([])
        ->and(Lancamento::query()->count())->toBe(3);
});

it('CA-05.4 não permite mudar o valor de um parcelamento cancelado', function () {
    criarParcelamento();
    $tela = Livewire::test(TelaParcelamentos::class)->call('cancelarParcelamento', idDoParcelamento());

    $tela->call('alterarValor', idDoParcelamento())->assertSet('acao', null);
});

it('CA-05.5 excluir tudo só acontece depois da confirmação', function () {
    criarParcelamento();

    $tela = Livewire::test(TelaParcelamentos::class)
        ->call('excluir', idDoParcelamento())
        ->assertSee('Excluir “Viagem” por completo?')
        ->assertSee('inclusive as de meses passados');

    expect(Lancamento::query()->count())->toBe(6);

    $tela->call('confirmarExclusao')->assertDontSee('por completo?');

    expect(Lancamento::query()->count())->toBe(0)
        ->and(ParcelamentoModel::query()->count())->toBe(0)
        ->and(parcelasDoMes('2025-10'))->toBe([]);
});

it('CA-05.5 desistir mantém tudo', function () {
    criarParcelamento();

    Livewire::test(TelaParcelamentos::class)
        ->call('excluir', idDoParcelamento())
        ->call('desistirDaExclusao')
        ->assertDontSee('por completo?')
        ->call('confirmarExclusao');

    expect(Lancamento::query()->count())->toBe(6);
});

it('valida o cadastro', function (array $campos, string $erro) {
    criarParcelamento(...$campos)->assertHasErrors([$erro]);

    expect(ParcelamentoModel::query()->count())->toBe(0);
})->with([
    'sem descrição' => [['descricao' => ''], 'descricao'],
    'valor zero' => [['valor' => '0'], 'valor'],
    'valor inválido' => [['valor' => 'abc'], 'valor'],
    'zero parcelas' => [['parcelas' => '0'], 'parcelas'],
    'parcelas vazias' => [['parcelas' => ''], 'parcelas'],
    'parcelas não numéricas' => [['parcelas' => 'seis'], 'parcelas'],
    'parcelas demais' => [['parcelas' => '1000'], 'parcelas'],
    'mês inválido' => [['primeira' => '2026-13'], 'primeira'],
]);

it('renomeia mantendo a numeração', function () {
    criarParcelamento();

    Livewire::test(TelaParcelamentos::class)
        ->call('renomear', idDoParcelamento())->assertSet('descricao', 'Viagem')
        ->set('descricao', 'Viagem de férias')->call('salvar')->assertHasNoErrors();

    expect(parcelasDoMes('2026-01'))->toBe(['Viagem de férias (4/6)' => 'R$ 250,00']);
});

it('mostra a situação e põe concluídos e cancelados no fim', function () {
    criarParcelamento('Antigo', '10,00', '2', '2025-01');
    criarParcelamento('Futuro', '20,00', '3', '2026-06');
    criarParcelamento('Atual', '30,00', '4', '2025-12');

    $html = Livewire::test(TelaParcelamentos::class)->html();

    expect(mb_strpos($html, 'Atual'))->toBeLessThan(mb_strpos($html, 'Antigo'))
        ->and(mb_strpos($html, 'Futuro'))->toBeLessThan(mb_strpos($html, 'Antigo'))
        ->and($html)->toContain('começa depois')
        ->and($html)->toContain('concluído');
});

it('a página abre pelo menu Mais e pela parcela na tela do mês', function () {
    criarParcelamento();

    $this->get('/mais')->assertSee(route('parcelamentos'), escape: false);
    $this->get('/mes/2026-01')->assertSee('Gerenciar o parcelamento Viagem (4/6)', escape: false);
    $this->get('/parcelamentos')->assertOk()->assertSeeLivewire(TelaParcelamentos::class);
});
