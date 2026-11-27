<?php

use Symfony\Component\Finder\Finder;
use Tests\Support\Rastreabilidade;

/*
 * T02 / RNF-04 — o código fica preso à spec.
 * Os testes do projeto (fora de tests/Spec e tests/Support) são varridos atrás de IDs CA-xx.y.
 */

function conteudosDosTestesDoProjeto(): Generator
{
    $arquivos = Finder::create()
        ->files()
        ->name('*.php')
        ->in(base_path('tests'))
        ->exclude(['Spec', 'Support']);

    foreach ($arquivos as $arquivo) {
        yield $arquivo->getContents();
    }
}

function criteriosDasSpecs(): array
{
    return Rastreabilidade::criteriosPorEntrega(file_get_contents(base_path('specs/01-requirements.md')));
}

it('encontra os critérios de aceite das três entregas nas specs', function () {
    $porEntrega = criteriosDasSpecs();

    expect($porEntrega)->toHaveKeys(['v1.0', 'v1.1', 'v1.2'])
        ->and($porEntrega['v1.0'])->toContain('CA-01.1', 'CA-18.6')
        ->and($porEntrega['v1.1'])->toContain('CA-10.1', 'CA-14.5')
        ->and($porEntrega['v1.2'])->toContain('CA-15.1', 'CA-17.3');
});

it('não tem critério de aceite duplicado nas specs', function () {
    $todos = array_merge(...array_values(criteriosDasSpecs()));

    expect(array_diff_assoc($todos, array_unique($todos)))->toBeEmpty();
});

it('todo critério de aceite de entrega concluída tem teste', function () {
    $concluidas = Rastreabilidade::entregasConcluidas(file_get_contents(base_path('specs/04-tasks.md')));
    $citados = Rastreabilidade::criteriosCitados(conteudosDosTestesDoProjeto());

    $faltando = Rastreabilidade::semTeste(criteriosDasSpecs(), $concluidas, $citados);

    expect($faltando)->toBeEmpty('Critérios sem teste: '.implode(', ', $faltando));
});

it('todo critério citado em teste existe nas specs', function () {
    $citados = Rastreabilidade::criteriosCitados(conteudosDosTestesDoProjeto());

    $inexistentes = Rastreabilidade::inexistentes(criteriosDasSpecs(), $citados);

    expect($inexistentes)->toBeEmpty('Testes citam critérios que não existem: '.implode(', ', $inexistentes));
});

describe('o verificador', function () {
    $requisitos = <<<'MD'
        ## v1.0 — Base
        - **CA-01.1** primeiro
        - **CA-01.2** segundo, que cita CA-09.9 no texto
        ## v1.1 — Mais
        - **CA-02.1** terceiro
        ## Requisitos não funcionais
        - **CA-99.1** fora de entrega
        MD;

    it('só conta definições de critério dentro das seções de entrega', function () use ($requisitos) {
        expect(Rastreabilidade::criteriosPorEntrega($requisitos))
            ->toBe(['v1.0' => ['CA-01.1', 'CA-01.2'], 'v1.1' => ['CA-02.1']]);
    });

    it('lê os portões marcados como concluídos', function () {
        $tarefas = "| ☑ G1 | Portão v1.0 |\n| ☐ G2 | Portão v1.1 |\n| ☑ T05 | outra |";

        expect(Rastreabilidade::entregasConcluidas($tarefas))->toBe(['v1.0']);
    });

    it('acusa critério sem teste só em entrega concluída', function () use ($requisitos) {
        $porEntrega = Rastreabilidade::criteriosPorEntrega($requisitos);
        $citados = Rastreabilidade::criteriosCitados(["it('CA-01.1 faz algo')"]);

        expect(Rastreabilidade::semTeste($porEntrega, ['v1.0'], $citados))->toBe(['CA-01.2'])
            ->and(Rastreabilidade::semTeste($porEntrega, [], $citados))->toBe([]);
    });

    it('acusa teste que cita critério inexistente', function () use ($requisitos) {
        $porEntrega = Rastreabilidade::criteriosPorEntrega($requisitos);

        expect(Rastreabilidade::inexistentes($porEntrega, ['CA-01.1', 'CA-07.7']))->toBe(['CA-07.7']);
    });
});
