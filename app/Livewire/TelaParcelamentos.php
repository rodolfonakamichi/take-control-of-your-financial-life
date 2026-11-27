<?php

namespace App\Livewire;

use App\Application\Parcelamentos\GerenciarParcelamentos;
use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Parcelamento\Parcela;
use App\Domain\Parcelamento\Parcelamento;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Tempo\Relogio;
use App\Livewire\Concerns\LeCampos;
use DomainException;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * T13 / RF-05: compras parceladas.
 * Excluir tudo (inclusive o passado) exige confirmação em duas etapas, verificada no servidor (CA-05.5).
 */
class TelaParcelamentos extends Component
{
    use LeCampos;

    private const ACOES = ['novo', 'valor', 'renomear'];

    /** 'novo' | 'valor' | 'renomear' | null */
    public ?string $acao = null;

    #[Locked]
    public ?int $selecionadoId = null;

    #[Locked]
    public ?int $confirmandoExclusaoId = null;

    public string $descricao = '';

    public string $valor = '';

    public string $parcelas = '';

    public string $primeira = '';

    public function novo(Relogio $relogio): void
    {
        $this->abrir('novo');
        $this->primeira = (string) $relogio->mesAtual();
    }

    public function alterarValor(int $id, RepositorioParcelamentos $repositorio): void
    {
        $parcelamento = $repositorio->porId($id);
        if ($parcelamento === null || $parcelamento->cancelado()) {
            return;
        }

        $this->abrir('valor', $parcelamento);
        $this->valor = self::valorParaCampo($parcelamento->valorParcela);
    }

    public function renomear(int $id, RepositorioParcelamentos $repositorio): void
    {
        $parcelamento = $repositorio->porId($id);
        if ($parcelamento === null) {
            return;
        }

        $this->abrir('renomear', $parcelamento);
        $this->descricao = $parcelamento->descricao;
    }

    public function cancelarFormulario(): void
    {
        $this->resetErrorBag();
        $this->reset();
    }

    public function salvar(GerenciarParcelamentos $parcelamentos): void
    {
        $this->resetErrorBag();

        try {
            $ok = match ($this->acao) {
                'novo' => $this->salvarNovo($parcelamentos),
                'valor' => $this->salvarValor($parcelamentos),
                'renomear' => $this->salvarNome($parcelamentos),
                default => false,
            };
        } catch (InvalidArgumentException|DomainException $e) {
            $this->addError($this->acao === 'valor' ? 'valor' : 'descricao', $e->getMessage());

            return;
        }

        if ($ok) {
            $this->cancelarFormulario();
        }
    }

    /**
     * CA-05.4: cancela daqui para frente; passado e parcelas pagas ficam.
     */
    public function cancelarParcelamento(int $id, GerenciarParcelamentos $parcelamentos): void
    {
        try {
            $parcelamentos->cancelar($id);
        } catch (InvalidArgumentException|DomainException) {
            return;
        }

        if ($this->selecionadoId === $id) {
            $this->cancelarFormulario();
        }
    }

    /**
     * CA-05.5, etapa 1: só pede a confirmação.
     */
    public function excluir(int $id, RepositorioParcelamentos $repositorio): void
    {
        if ($repositorio->porId($id) === null) {
            return;
        }

        $this->cancelarFormulario();
        $this->confirmandoExclusaoId = $id;
    }

    /**
     * CA-05.5, etapa 2: exclui tudo, inclusive o passado.
     */
    public function confirmarExclusao(GerenciarParcelamentos $parcelamentos): void
    {
        if ($this->confirmandoExclusaoId === null) {
            return;
        }

        try {
            $parcelamentos->excluirTudo($this->confirmandoExclusaoId);
        } catch (InvalidArgumentException) {
            // já não existia
        }

        $this->confirmandoExclusaoId = null;
    }

    public function desistirDaExclusao(): void
    {
        $this->confirmandoExclusaoId = null;
    }

    public function render(RepositorioParcelamentos $repositorio, Relogio $relogio): View
    {
        $mesAtual = $relogio->mesAtual();
        $itens = array_map(fn (Parcelamento $p) => self::paraLista($p, $mesAtual), $repositorio->todos());

        usort($itens, fn (array $a, array $b) => [$a['ordem'], $a['descricao']] <=> [$b['ordem'], $b['descricao']]);

        return view('livewire.tela-parcelamentos', [
            'itens' => $itens,
            'selecionado' => $this->selecionadoId ? $repositorio->porId($this->selecionadoId) : null,
            'emConfirmacao' => $this->confirmandoExclusaoId ? $repositorio->porId($this->confirmandoExclusaoId) : null,
            'mesAtual' => $mesAtual,
        ]);
    }

    private function abrir(string $acao, ?Parcelamento $parcelamento = null): void
    {
        $this->cancelarFormulario();
        $this->acao = in_array($acao, self::ACOES, true) ? $acao : null;
        $this->selecionadoId = $parcelamento?->id;
    }

    private function salvarNovo(GerenciarParcelamentos $parcelamentos): bool
    {
        if (trim($this->descricao) === '') {
            $this->addError('descricao', 'Informe a descrição.');
        }
        $valor = $this->lerValor('valor');
        $quantidade = $this->lerQuantidade();
        $primeira = $this->lerCompetencia('primeira');

        if ($this->getErrorBag()->isNotEmpty()) {
            return false;
        }

        $parcelamentos->criar($this->descricao, $valor, $quantidade, $primeira);

        return true;
    }

    private function salvarValor(GerenciarParcelamentos $parcelamentos): bool
    {
        $valor = $this->lerValor('valor');

        if ($valor === null) {
            return false;
        }

        $parcelamentos->alterarValorParcela($this->selecionadoId, $valor);

        return true;
    }

    private function salvarNome(GerenciarParcelamentos $parcelamentos): bool
    {
        if (trim($this->descricao) === '') {
            $this->addError('descricao', 'Informe a descrição.');

            return false;
        }

        $parcelamentos->renomear($this->selecionadoId, $this->descricao);

        return true;
    }

    private function lerQuantidade(): ?int
    {
        $texto = trim($this->parcelas);

        if (! preg_match('/^\d{1,3}$/', $texto) || (int) $texto < 1) {
            $this->addError('parcelas', 'Informe quantas parcelas (de 1 a 999).');

            return null;
        }

        return (int) $texto;
    }

    /**
     * @return array<string, mixed>
     */
    private static function paraLista(Parcelamento $p, Competencia $mesAtual): array
    {
        $ultima = $p->ultimaCompetencia();
        $atual = $p->parcelaEm($mesAtual);
        $aVencer = array_filter($p->parcelas, fn (Parcela $x) => ! $x->paga && ! $x->competencia->antesDe($mesAtual));

        $situacao = match (true) {
            $p->cancelado() => 'cancelado',
            $ultima->antesDe($mesAtual) => 'concluido',
            $p->primeiraCompetencia->depoisDe($mesAtual) => 'futuro',
            default => 'andamento',
        };

        return [
            'id' => $p->id,
            'descricao' => $p->descricao,
            'valorParcela' => $p->valorParcela,
            'totalParcelas' => $p->totalParcelas,
            'primeira' => $p->primeiraCompetencia,
            'ultima' => $ultima,
            'parcelaAtual' => $atual?->numero,
            'aVencer' => Dinheiro::somarTodos(array_map(fn (Parcela $x) => $x->valor, $aVencer)),
            'quantidadeAVencer' => count($aVencer),
            'situacao' => $situacao,
            'canceladoEm' => $p->canceladoEm,
            'ordem' => in_array($situacao, ['concluido', 'cancelado'], true) ? 1 : 0,
        ];
    }
}
