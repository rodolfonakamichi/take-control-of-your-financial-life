<?php

use App\Infra\Tempo\Relogio;
use App\Infra\Tempo\RelogioFixo;
use App\Models\User;

// T10 — layout mobile-first (RNF-07) e navegação por mês e ano.

beforeEach(function () {
    $this->app->instance(Relogio::class, RelogioFixo::em('2026-03-10 12:00:00'));
    $this->actingAs(User::factory()->create());
});

describe('layout', function () {
    it('é responsivo, com viewport para celular', function () {
        $this->get('/')->assertSee('<meta name="viewport" content="width=device-width, initial-scale=1">', escape: false);
    });

    it('tem a navegação principal com Início, Mês, Ano e Mais', function () {
        $this->get('/')
            ->assertSee('aria-label="Navegação principal"', escape: false)
            ->assertSeeInOrder(['Início', 'Mês', 'Ano', 'Mais']);
    });

    it('marca a página atual na navegação', function (string $url, string $rotulo) {
        $html = $this->get($url)->getContent();

        expect($html)->toMatch('/aria-current="page"[^>]*>\s*(<svg.*?<\/svg>\s*)?<span>'.$rotulo.'<\/span>/s');
    })->with([
        ['/', 'Início'],
        ['/mes', 'Mês'],
        ['/ano', 'Ano'],
        ['/mais', 'Mais'],
        ['/configuracoes/senha', 'Mais'],
    ]);

    it('tem o botão + com as ações de lançar, em toda página', function (string $url) {
        $this->get($url)
            ->assertSee('aria-label="Novo lançamento"', escape: false)
            ->assertSee(route('mes', ['novo' => 'despesa']), escape: false)
            ->assertSee(route('mes', ['novo' => 'receita']), escape: false)
            ->assertSee(route('mes', ['novo' => 'guardado']), escape: false);
    })->with(['/', '/mes/2026-05', '/ano/2027', '/mais']);
});

describe('mês', function () {
    it('abre no mês atual por padrão', function () {
        $this->get('/mes')->assertOk()->assertSee('Março de 2026')->assertDontSee('Ir para o mês atual');
    });

    it('navega para o mês anterior e o próximo', function () {
        $this->get('/mes/2026-03')
            ->assertSee(route('mes', '2026-02'), escape: false)
            ->assertSee(route('mes', '2026-04'), escape: false);
    });

    it('atravessa a virada do ano', function () {
        $this->get('/mes/2026-01')
            ->assertSee('Janeiro de 2026')
            ->assertSee(route('mes', '2025-12'), escape: false);

        $this->get('/mes/2025-12')->assertSee(route('mes', '2026-01'), escape: false);
    });

    it('oferece voltar ao mês atual quando está em outro mês', function () {
        $this->get('/mes/2027-08')->assertSee('Agosto de 2027')->assertSee('Ir para o mês atual');
    });

    it('responde 404 para mês inválido', function (string $url) {
        $this->get($url)->assertNotFound();
    })->with(['/mes/2026-13', '/mes/2026-00', '/mes/26-03', '/mes/marco']);
});

describe('ano', function () {
    it('abre no ano atual com os 12 meses, cada um levando ao mês', function () {
        $resposta = $this->get('/ano')->assertOk()->assertSee('2026');

        foreach (range(1, 12) as $m) {
            $resposta->assertSee(route('mes', sprintf('2026-%02d', $m)), escape: false);
        }

        $resposta->assertSeeInOrder(['Janeiro', 'Fevereiro', 'Março', 'Dezembro']);
    });

    it('destaca o mês atual', function () {
        expect($this->get('/ano/2026')->getContent())->toMatch('/aria-current="date"[^>]*>\s*Março/');
    });

    it('navega entre anos e oferece voltar ao atual', function () {
        $this->get('/ano/2028')
            ->assertSee(route('ano', 2027), escape: false)
            ->assertSee(route('ano', 2029), escape: false)
            ->assertSee('Ir para o ano atual');
    });

    it('responde 404 para ano fora do intervalo', function () {
        $this->get('/ano/1800')->assertNotFound();
    });
});

it('lista trocar senha e sair na página Mais', function () {
    $this->get('/mais')->assertOk()->assertSee('Trocar senha')->assertSee('Sair');
});
