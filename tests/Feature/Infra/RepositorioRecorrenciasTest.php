<?php

use App\Domain\Recorrencia\Recorrencia;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Models\RecorrenciaOcorrencia;
use App\Models\RecorrenciaValor;

// T08 — Recorrencia (domínio) <-> banco.

beforeEach(fn () => $this->repo = app(RepositorioRecorrencias::class));

it('grava e lê uma recorrência nova com id', function () {
    $salva = $this->repo->salvar(Recorrencia::nova('Internet', reais('50,00'), mes('2026-01')));

    $lida = $this->repo->porId($salva->id);

    expect($salva->id)->toBeInt()
        ->and($lida->descricao)->toBe('Internet')
        ->and((string) $lida->inicio)->toBe('2026-01')
        ->and($lida->fim)->toBeNull()
        ->and($lida->ocorrenciaEm(mes('2026-05'))?->valor->formatar())->toBe('R$ 50,00');
});

it('grava vigências, edição do mês e encerramento', function () {
    $salva = $this->repo->salvar(Recorrencia::nova('Internet', reais('50,00'), mes('2026-01')));

    $this->repo->salvar(
        $salva->comValorNoMes(mes('2026-03'), reais('60,00'))
            ->alterarValorAPartirDe(mes('2026-06'), reais('70,00'))
            ->encerrarEm(mes('2026-10')),
    );
    $lida = $this->repo->porId($salva->id);

    expect($lida->ocorrenciaEm(mes('2026-03'))?->valor->formatar())->toBe('R$ 60,00')
        ->and($lida->ocorrenciaEm(mes('2026-04'))?->valor->formatar())->toBe('R$ 50,00')
        ->and($lida->ocorrenciaEm(mes('2026-07'))?->valor->formatar())->toBe('R$ 70,00')
        ->and($lida->ocorrenciaEm(mes('2026-11')))->toBeNull()
        ->and(RecorrenciaValor::query()->count())->toBe(2);
});

it('apaga do banco a edição do mês desfeita', function () {
    $salva = $this->repo->salvar(
        Recorrencia::nova('Luz', reais('150,00'), mes('2026-01'))->comValorNoMes(mes('2026-02'), reais('170,00')),
    );
    expect(RecorrenciaOcorrencia::query()->count())->toBe(1);

    $this->repo->salvar($salva->semValorNoMes(mes('2026-02')));

    expect(RecorrenciaOcorrencia::query()->count())->toBe(0);
});

it('lista só as recorrências ativas no mês', function () {
    $this->repo->salvar(Recorrencia::nova('Internet', reais('50,00'), mes('2026-01')));
    $this->repo->salvar(Recorrencia::nova('Curso', reais('300,00'), mes('2025-01'), mes('2026-02')));
    $this->repo->salvar(Recorrencia::nova('Academia', reais('120,00'), mes('2026-06')));

    $descricoes = fn (string $m) => array_map(fn (Recorrencia $r) => $r->descricao, $this->repo->ativasEm(mes($m)));

    expect($descricoes('2026-02'))->toBe(['Curso', 'Internet'])
        ->and($descricoes('2026-03'))->toBe(['Internet'])
        ->and($descricoes('2026-06'))->toBe(['Academia', 'Internet'])
        ->and($descricoes('2025-12'))->toBe(['Curso']);
});

it('exclui a recorrência com vigências e edições', function () {
    $salva = $this->repo->salvar(
        Recorrencia::nova('Luz', reais('150,00'), mes('2026-01'))->comValorNoMes(mes('2026-02'), reais('170,00')),
    );

    $this->repo->excluir($salva->id);

    expect($this->repo->todas())->toBeEmpty()
        ->and(RecorrenciaValor::query()->count())->toBe(0)
        ->and(RecorrenciaOcorrencia::query()->count())->toBe(0);
});
