<?php

namespace App\Livewire;

use App\Application\Guardado\GerenciarGuardado;
use App\Domain\Dinheiro;
use App\Domain\Guardado\GuardadoFicariaNegativo;
use App\Infra\Persistencia\Configuracoes;
use App\Infra\Tempo\Relogio;
use App\Livewire\Concerns\LeCampos;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Component;

/**
 * CA-06.6: o total que já estava guardado antes do primeiro mês controlado.
 */
class TelaGuardadoInicial extends Component
{
    use LeCampos;

    public string $valor = '';

    public bool $salvo = false;

    public function mount(Configuracoes $configuracoes): void
    {
        $this->valor = self::valorParaCampo($configuracoes->guardadoInicial());
    }

    public function salvar(GerenciarGuardado $guardado): void
    {
        $this->resetErrorBag();
        $this->salvo = false;

        try {
            $valor = Dinheiro::deTexto(trim($this->valor) === '' ? '0' : $this->valor);
        } catch (InvalidArgumentException) {
            $this->addError('valor', 'Valor inválido. Use o formato 1.234,56.');

            return;
        }

        if ($valor->ehNegativo()) {
            $this->addError('valor', 'O guardado inicial não pode ser negativo.');

            return;
        }

        try {
            $guardado->definirGuardadoInicial($valor);
        } catch (GuardadoFicariaNegativo $e) {
            $this->addError('valor', 'Com esse valor, '.lcfirst($e->getMessage()).' Confira as retiradas lançadas.');

            return;
        }

        $this->valor = self::valorParaCampo($valor);
        $this->salvo = true;
    }

    public function render(GerenciarGuardado $guardado, Relogio $relogio): View
    {
        return view('livewire.tela-guardado-inicial', [
            'totalAtual' => $guardado->livro()->totalAte($relogio->mesAtual()),
        ]);
    }
}
