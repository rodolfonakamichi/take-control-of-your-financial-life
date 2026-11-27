<?php

namespace App\Http\Controllers\Navegacao;

use App\Domain\Competencia;
use App\Http\Controllers\Controller;
use App\Infra\Tempo\Relogio;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Navegação por mês (T10). O conteúdo do mês chega na T11.
 */
class MesController extends Controller
{
    public function __construct(private Relogio $relogio) {}

    public function show(?string $competencia = null): View
    {
        try {
            $mes = $competencia === null ? $this->relogio->mesAtual() : Competencia::deTexto($competencia);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return view('mes.show', [
            'mes' => $mes,
            'mesAtual' => $this->relogio->mesAtual(),
        ]);
    }
}
