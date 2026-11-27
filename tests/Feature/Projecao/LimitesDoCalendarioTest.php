<?php

use App\Models\User;

// T16 — CA-08.3: qualquer mês e ano podem ser navegados, inclusive nos limites do calendário (1900 a 9999).

beforeEach(fn () => $this->actingAs(User::factory()->create()));

it('abre o primeiro e o último mês possíveis sem link para fora do calendário', function (string $url, string $semLink) {
    $this->get($url)->assertOk()->assertDontSee($semLink, escape: false);
})->with([
    'dezembro de 9999' => ['/mes/9999-12', '/mes/10000-01'],
    'janeiro de 1900' => ['/mes/1900-01', '/mes/1899-12'],
]);

it('abre o primeiro e o último ano possíveis sem link para fora do calendário', function (string $url, string $semLink) {
    $this->get($url)->assertOk()->assertDontSee($semLink, escape: false);
})->with([
    '9999' => ['/ano/9999', '/ano/10000'],
    '1900' => ['/ano/1900', '/ano/1899'],
]);
