<?php

use App\Domain\Parcelamento\GeradorParcelas;
use App\Domain\Parcelamento\Parcela;
use App\Domain\Parcelamento\Parcelamento;

// T07 — RD-06 (parcelas em meses consecutivos), RD-16 (alterar/cancelar não toca passado nem pagas)
// e CA-05.1..CA-05.4.

function viagem(): Parcelamento
{
    return Parcelamento::novo('Viagem', reais('250,00'), 6, mes('2025-10'), id: 1);
}

/**
 * @return array<string, string> "AAAA-MM" => "Descrição (k/N) R$ valor"
 */
function parcelasResumidas(Parcelamento $parcelamento): array
{
    $resumo = [];
    foreach ($parcelamento->parcelas as $p) {
        $resumo[(string) $p->competencia] = $p->descricaoCom($parcelamento->descricao).' '.$p->valor->formatar().($p->paga ? ' paga' : '');
    }

    return $resumo;
}

function cancelamento(): DateTimeImmutable
{
    return new DateTimeImmutable('2026-01-15 10:00:00');
}

it('CA-05.1 distribui as parcelas em meses seguidos, atravessando a virada do ano', function () {
    expect(parcelasResumidas(viagem()))->toBe([
        '2025-10' => 'Viagem (1/6) R$ 250,00',
        '2025-11' => 'Viagem (2/6) R$ 250,00',
        '2025-12' => 'Viagem (3/6) R$ 250,00',
        '2026-01' => 'Viagem (4/6) R$ 250,00',
        '2026-02' => 'Viagem (5/6) R$ 250,00',
        '2026-03' => 'Viagem (6/6) R$ 250,00',
    ])
        ->and((string) viagem()->ultimaCompetencia())->toBe('2026-03');
});

it('CA-05.1 encontra a parcela de cada mês e nenhuma fora do período', function () {
    $parcelamento = viagem();

    expect($parcelamento->parcelaEm(mes('2026-01'))?->numero)->toBe(4)
        ->and($parcelamento->parcelaEm(mes('2025-09')))->toBeNull()
        ->and($parcelamento->parcelaEm(mes('2026-04')))->toBeNull();
});

it('CA-05.2 gera a numeração e não aceita numeração digitada na descrição', function (string $descricao) {
    Parcelamento::novo($descricao, reais('100,00'), 3, mes('2026-01'));
})->with(['Viagem (1/6)', 'Viagem(2/6)', 'Curso ( 3 / 10 )'])
    ->throws(InvalidArgumentException::class, 'gerada automaticamente');

it('CA-05.2 aceita parênteses que não são numeração', function () {
    $parcelamento = Parcelamento::novo('Notebook (trabalho)', reais('500,00'), 2, mes('2026-01'));

    expect($parcelamento->parcelas[0]->descricaoCom($parcelamento->descricao))->toBe('Notebook (trabalho) (1/2)');
});

it('CA-05.3 muda o valor só das parcelas do mês atual em diante', function () {
    $alterado = viagem()->alterarValorParcela(reais('300,00'), mesAtual: mes('2026-01'));

    expect(parcelasResumidas($alterado))->toBe([
        '2025-10' => 'Viagem (1/6) R$ 250,00',
        '2025-11' => 'Viagem (2/6) R$ 250,00',
        '2025-12' => 'Viagem (3/6) R$ 250,00',
        '2026-01' => 'Viagem (4/6) R$ 300,00',
        '2026-02' => 'Viagem (5/6) R$ 300,00',
        '2026-03' => 'Viagem (6/6) R$ 300,00',
    ])
        ->and($alterado->valorParcela->formatar())->toBe('R$ 300,00');
});

it('CA-05.3 não muda parcelas já pagas, nem as futuras pagas adiantado', function () {
    $alterado = viagem()
        ->marcarParcela(4, paga: true)
        ->marcarParcela(6, paga: true)
        ->alterarValorParcela(reais('300,00'), mesAtual: mes('2026-01'));

    expect(parcelasResumidas($alterado))->toMatchArray([
        '2026-01' => 'Viagem (4/6) R$ 250,00 paga',
        '2026-02' => 'Viagem (5/6) R$ 300,00',
        '2026-03' => 'Viagem (6/6) R$ 250,00 paga',
    ]);
});

it('CA-05.3 muda todas as parcelas se o parcelamento ainda não começou', function () {
    $alterado = viagem()->alterarValorParcela(reais('200,00'), mesAtual: mes('2025-08'));

    expect(array_map(fn (Parcela $p) => $p->valor->formatar(), $alterado->parcelas))
        ->each->toBe('R$ 200,00');
});

it('CA-05.4 cancela as parcelas do mês atual em diante e mantém o passado', function () {
    $cancelado = viagem()->cancelar(mesAtual: mes('2026-01'), em: cancelamento());

    expect(parcelasResumidas($cancelado))->toBe([
        '2025-10' => 'Viagem (1/6) R$ 250,00',
        '2025-11' => 'Viagem (2/6) R$ 250,00',
        '2025-12' => 'Viagem (3/6) R$ 250,00',
    ])
        ->and($cancelado->cancelado())->toBeTrue()
        ->and($cancelado->parcelaEm(mes('2026-02')))->toBeNull();
});

it('CA-05.4 mantém parcelas pagas ao cancelar, mesmo futuras', function () {
    $cancelado = viagem()
        ->marcarParcela(5, paga: true)
        ->cancelar(mesAtual: mes('2026-01'), em: cancelamento());

    expect(array_keys(parcelasResumidas($cancelado)))->toBe(['2025-10', '2025-11', '2025-12', '2026-02']);
});

it('CA-05.4 não permite alterar nem cancelar de novo um parcelamento cancelado', function (Closure $operacao) {
    $cancelado = viagem()->cancelar(mes('2026-01'), cancelamento());

    $operacao($cancelado);
})->with([
    'alterar' => [fn (Parcelamento $p) => $p->alterarValorParcela(reais('1,00'), mes('2026-01'))],
    'cancelar' => [fn (Parcelamento $p) => $p->cancelar(mes('2026-01'), cancelamento())],
])->throws(DomainException::class, 'já foi cancelado');

it('reconstitui um parcelamento cancelado com parte das parcelas', function () {
    $original = viagem()->cancelar(mes('2026-01'), cancelamento());

    $reconstituido = Parcelamento::reconstituir(
        1, 'Viagem', reais('250,00'), 6, mes('2025-10'), $original->parcelas, cancelamento(),
    );

    expect(parcelasResumidas($reconstituido))->toBe(parcelasResumidas($original));
});

it('recusa dados gravados inconsistentes', function (Closure $criar, string $mensagem) {
    expect($criar)->toThrow(InvalidArgumentException::class, $mensagem);
})->with([
    'parcela no mês errado' => [
        fn () => Parcelamento::reconstituir(1, 'Curso', reais('10,00'), 2, mes('2026-01'), [
            new Parcela(1, 2, mes('2026-01'), reais('10,00')),
            new Parcela(2, 2, mes('2026-03'), reais('10,00')),
        ]),
        'deveria cair em 02/2026',
    ],
    'parcela repetida' => [
        fn () => Parcelamento::reconstituir(1, 'Curso', reais('10,00'), 2, mes('2026-01'), [
            new Parcela(1, 2, mes('2026-01'), reais('10,00')),
            new Parcela(1, 2, mes('2026-01'), reais('10,00')),
        ]),
        'repetida',
    ],
    'parcela além do total' => [
        fn () => Parcelamento::reconstituir(1, 'Curso', reais('10,00'), 2, mes('2026-01'), [
            new Parcela(3, 2, mes('2026-03'), reais('10,00')),
        ]),
        'não pertence',
    ],
    'ativo sem todas as parcelas' => [
        fn () => Parcelamento::reconstituir(1, 'Curso', reais('10,00'), 2, mes('2026-01'), [
            new Parcela(1, 2, mes('2026-01'), reais('10,00')),
        ]),
        'todas as parcelas',
    ],
]);

it('recusa parcelamento inválido', function (Closure $criar, string $mensagem) {
    expect($criar)->toThrow(InvalidArgumentException::class, $mensagem);
})->with([
    'sem descrição' => [fn () => Parcelamento::novo(' ', reais('10,00'), 2, mes('2026-01')), 'descrição'],
    'zero parcelas' => [fn () => Parcelamento::novo('Curso', reais('10,00'), 0, mes('2026-01')), 'pelo menos 1'],
    'valor zero' => [fn () => Parcelamento::novo('Curso', reais('0,00'), 2, mes('2026-01')), 'maior que zero'],
    'novo valor zero' => [fn () => viagem()->alterarValorParcela(reais('0,00'), mes('2026-01')), 'maior que zero'],
    'marcar parcela inexistente' => [fn () => viagem()->marcarParcela(7, true), 'não existe'],
]);

it('marca e desmarca parcela como paga', function () {
    $parcelamento = viagem()->marcarParcela(2, paga: true);

    expect($parcelamento->parcela(2)?->paga)->toBeTrue()
        ->and($parcelamento->marcarParcela(2, paga: false)->parcela(2)?->paga)->toBeFalse();
});

it('é imutável', function () {
    $original = viagem();
    $original->alterarValorParcela(reais('999,00'), mes('2025-10'));
    $original->cancelar(mes('2025-10'), cancelamento());

    expect($original->cancelado())->toBeFalse()
        ->and($original->parcelas)->toHaveCount(6)
        ->and($original->parcela(1)?->valor->formatar())->toBe('R$ 250,00');
});

it('vira despesa para o resumo do mês com a numeração na descrição', function () {
    $parcelamento = viagem();
    $despesa = $parcelamento->parcelaEm(mes('2025-12'))->paraDespesa($parcelamento->descricao);

    expect($despesa->descricao)->toBe('Viagem (3/6)')
        ->and($despesa->valor->formatar())->toBe('R$ 250,00');
});

it('lista as parcelas que caem num mês, de vários parcelamentos', function () {
    $parcelamentos = [
        viagem(),
        Parcelamento::novo('Curso', reais('320,00'), 10, mes('2026-03'), id: 2),
        Parcelamento::novo('Celular', reais('150,00'), 3, mes('2025-01'), id: 3),
    ];

    $doMes = GeradorParcelas::doMes(mes('2026-03'), $parcelamentos);

    expect(array_map(fn (array $item) => $item['parcela']->descricaoCom($item['parcelamento']->descricao), $doMes))
        ->toBe(['Viagem (6/6)', 'Curso (1/10)']);
});
