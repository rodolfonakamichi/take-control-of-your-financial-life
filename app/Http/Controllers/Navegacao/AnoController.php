<?php

namespace App\Http\Controllers\Navegacao;

use App\Domain\Competencia;
use App\Http\Controllers\Controller;
use App\Infra\Tempo\Relogio;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Navegação por ano (T10). Os totais do ano chegam na T15.
 */
class AnoController extends Controller
{
    public function __construct(private Relogio $relogio) {}

    public function show(?int $ano = null): View
    {
        $ano ??= $this->relogio->mesAtual()->ano;

        try {
            $meses = Competencia::mesesDoAno($ano);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return view('ano.show', [
            'ano' => $ano,
            'meses' => $meses,
            'mesAtual' => $this->relogio->mesAtual(),
        ]);
    }
}
