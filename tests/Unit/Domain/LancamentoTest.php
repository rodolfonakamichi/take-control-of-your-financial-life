<?php

use App\Domain\Dinheiro;
use App\Domain\Guardado\MovimentoGuardado;
use App\Domain\Lancamento\CategoriaReceita;
use App\Domain\Lancamento\Despesa;
use App\Domain\Lancamento\Receita;

// T04 — RV-01 no domínio (a validação na tela é coberta pelo CA-01.2, na T11).

it('recusa despesa ou receita sem descrição (RV-01)', function (callable $criar) {
    $criar();
})->with([
    'despesa vazia' => [fn () => new Despesa('', Dinheiro::deTexto('10,00'))],
    'despesa só espaços' => [fn () => new Despesa('   ', Dinheiro::deTexto('10,00'))],
    'receita vazia' => [fn () => new Receita('', Dinheiro::deTexto('10,00'))],
])->throws(InvalidArgumentException::class, 'descrição');

it('recusa despesa ou receita com valor zero ou negativo (RV-01)', function (callable $criar) {
    $criar();
})->with([
    'despesa zero' => [fn () => new Despesa('Luz', Dinheiro::zero())],
    'despesa negativa' => [fn () => new Despesa('Luz', Dinheiro::deTexto('-10,00'))],
    'receita zero' => [fn () => new Receita('Salário', Dinheiro::zero())],
])->throws(InvalidArgumentException::class, 'maior que zero');

it('recusa movimento do guardado com valor zero', function () {
    MovimentoGuardado::guardar(Dinheiro::zero());
})->throws(InvalidArgumentException::class);

it('tira espaços da descrição', function () {
    expect((new Despesa('  Academia ', Dinheiro::deTexto('120,00')))->descricao)->toBe('Academia');
});

it('usa Salário como categoria padrão da receita', function () {
    expect((new Receita('Pagamento', Dinheiro::deTexto('10,00')))->categoria)->toBe(CategoriaReceita::Salario);
});
