<?php

// T01 — fumaça: a aplicação sobe e responde.
it('responde na página inicial', function () {
    $this->get('/')->assertOk();
});
