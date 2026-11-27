<?php

namespace App\Http\Controllers\Navegacao;

use App\Domain\Competencia;
use App\Http\Controllers\Controller;
use App\Infra\Tempo\Relogio;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Página do mês: navegação (T10) + componente TelaMes (T11).
 */
class MesController extends Controller
{
    public function __construct(private Relogio $relogio) {}

    public function show(Request $request, ?string $competencia = null): View
    {
        try {
            $mes = $competencia === null ? $this->relogio->mesAtual() : Competencia::deTexto($competencia);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return view('mes.show', [
            'mes' => $mes,
            'mesAtual' => $this->relogio->mesAtual(),
            'novo' => $request->query('novo'),
        ]);
    }
}
