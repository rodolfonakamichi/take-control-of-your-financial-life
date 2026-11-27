<?php

namespace App\Http\Controllers\Navegacao;

use App\Application\Ano\MontarAno;
use App\Domain\Competencia;
use App\Http\Controllers\Controller;
use App\Infra\Tempo\Relogio;
use Illuminate\View\View;
use InvalidArgumentException;

/**
 * Visão anual (RF-07): os 12 meses com seus totais e os totais do ano.
 */
class AnoController extends Controller
{
    public function __construct(private Relogio $relogio) {}

    public function show(MontarAno $montarAno, ?int $ano = null): View
    {
        $ano ??= $this->relogio->mesAtual()->ano;

        try {
            Competencia::de($ano, 1);
        } catch (InvalidArgumentException) {
            abort(404);
        }

        return view('ano.show', [
            'ano' => $ano,
            'visao' => $montarAno->executar($ano),
            'mesAtual' => $this->relogio->mesAtual(),
        ]);
    }
}
