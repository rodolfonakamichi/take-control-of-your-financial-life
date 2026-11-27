<?php

// T01 — fumaça: a aplicação sobe e responde.
it('responde no healthcheck e na tela de entrar', function () {
    $this->get('/up')->assertOk();
    $this->get('/entrar')->assertOk();
});
