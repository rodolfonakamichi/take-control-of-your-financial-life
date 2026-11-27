<?php

namespace App\Livewire;

use App\Application\Recorrencias\GerenciarRecorrencias;
use App\Domain\Competencia;
use App\Domain\Recorrencia\Recorrencia;
use App\Domain\Recorrencia\Vigencia;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Infra\Tempo\Relogio;
use App\Livewire\Concerns\LeCampos;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * T12 / RF-04: cadastro das despesas fixas.
 * A edição do valor só de um mês (CA-04.2) fica na tela do mês.
 */
class TelaRecorrencias extends Component
{
    use LeCampos;

    private const ACOES = ['nova', 'valor', 'encerrar', 'renomear'];

    /** 'nova' | 'valor' | 'encerrar' | 'renomear' | null */
    public ?string $acao = null;

    #[Locked]
    public ?int $selecionadaId = null;

    public string $descricao = '';

    public string $valor = '';

    public string $inicio = '';

    public string $fim = '';

    public string $aPartirDe = '';

    public string $ultimoMes = '';

    public function nova(Relogio $relogio): void
    {
        $this->abrir('nova');
        $this->inicio = (string) $relogio->mesAtual();
    }

    public function alterarValor(int $id, RepositorioRecorrencias $repositorio, Relogio $relogio): void
    {
        $recorrencia = $repositorio->porId($id);
        if ($recorrencia === null) {
            return;
        }

        $this->abrir('valor', $recorrencia);
        $desde = self::maisRecente($relogio->mesAtual(), $recorrencia->inicio);
        $this->aPartirDe = (string) $desde;
        $this->valor = self::valorParaCampo($recorrencia->valorPadraoEm($desde));
    }

    public function encerrar(int $id, RepositorioRecorrencias $repositorio, Relogio $relogio): void
    {
        $recorrencia = $repositorio->porId($id);
        if ($recorrencia === null) {
            return;
        }

        $this->abrir('encerrar', $recorrencia);
        $this->ultimoMes = (string) ($recorrencia->fim ?? self::maisRecente($relogio->mesAtual(), $recorrencia->inicio));
    }

    public function renomear(int $id, RepositorioRecorrencias $repositorio): void
    {
        $recorrencia = $repositorio->porId($id);
        if ($recorrencia === null) {
            return;
        }

        $this->abrir('renomear', $recorrencia);
        $this->descricao = $recorrencia->descricao;
    }

    public function cancelar(): void
    {
        $this->resetErrorBag();
        $this->reset();
    }

    public function salvar(GerenciarRecorrencias $recorrencias): void
    {
        $this->resetErrorBag();

        try {
            $ok = match ($this->acao) {
                'nova' => $this->salvarNova($recorrencias),
                'valor' => $this->salvarValor($recorrencias),
                'encerrar' => $this->salvarEncerramento($recorrencias),
                'renomear' => $this->salvarNome($recorrencias),
                default => false,
            };
        } catch (InvalidArgumentException $e) {
            $this->addError($this->campoDoErro(), $e->getMessage());

            return;
        }

        if ($ok) {
            $this->cancelar();
        }
    }

    public function excluir(int $id, GerenciarRecorrencias $recorrencias): void
    {
        try {
            $recorrencias->excluir($id);
        } catch (InvalidArgumentException) {
            return;
        }

        if ($this->selecionadaId === $id) {
            $this->cancelar();
        }
    }

    public function render(RepositorioRecorrencias $repositorio, Relogio $relogio): View
    {
        $mesAtual = $relogio->mesAtual();
        $itens = array_map(fn (Recorrencia $r) => self::paraLista($r, $mesAtual), $repositorio->todas());

        usort($itens, fn (array $a, array $b) => [$a['ordem'], $a['descricao']] <=> [$b['ordem'], $b['descricao']]);

        return view('livewire.tela-recorrencias', [
            'itens' => $itens,
            'selecionada' => $this->selecionadaId ? $repositorio->porId($this->selecionadaId) : null,
        ]);
    }

    private function abrir(string $acao, ?Recorrencia $recorrencia = null): void
    {
        $this->cancelar();
        $this->acao = in_array($acao, self::ACOES, true) ? $acao : null;
        $this->selecionadaId = $recorrencia?->id;
    }

    private function salvarNova(GerenciarRecorrencias $recorrencias): bool
    {
        if (trim($this->descricao) === '') {
            $this->addError('descricao', 'Informe a descrição.');
        }
        $valor = $this->lerValor('valor');
        $inicio = $this->lerCompetencia('inicio');
        $fim = $this->lerCompetencia('fim', obrigatorio: false);

        if ($inicio !== null && $fim !== null && $fim->antesDe($inicio)) {
            $this->addError('fim', 'O último mês não pode ser antes do primeiro.');
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return false;
        }

        $recorrencias->criar($this->descricao, $valor, $inicio, $fim);

        return true;
    }

    private function salvarValor(GerenciarRecorrencias $recorrencias): bool
    {
        $valor = $this->lerValor('valor');
        $desde = $this->lerCompetencia('aPartirDe');

        if ($valor === null || $desde === null) {
            return false;
        }

        $recorrencias->alterarValorAPartirDe($this->selecionadaId, $desde, $valor);

        return true;
    }

    private function salvarEncerramento(GerenciarRecorrencias $recorrencias): bool
    {
        $ultimoMes = $this->lerCompetencia('ultimoMes');

        if ($ultimoMes === null) {
            return false;
        }

        $recorrencias->encerrar($this->selecionadaId, $ultimoMes);

        return true;
    }

    private function salvarNome(GerenciarRecorrencias $recorrencias): bool
    {
        if (trim($this->descricao) === '') {
            $this->addError('descricao', 'Informe a descrição.');

            return false;
        }

        $recorrencias->renomear($this->selecionadaId, $this->descricao);

        return true;
    }

    private function campoDoErro(): string
    {
        return match ($this->acao) {
            'valor' => 'aPartirDe',
            'encerrar' => 'ultimoMes',
            default => 'descricao',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private static function paraLista(Recorrencia $r, Competencia $mesAtual): array
    {
        $referencia = self::maisRecente($mesAtual, $r->inicio);
        $situacao = match (true) {
            $r->fim !== null && $r->fim->antesDe($mesAtual) => 'encerrada',
            $r->inicio->depoisDe($mesAtual) => 'futura',
            default => 'ativa',
        };

        $proximas = array_values(array_filter($r->vigencias, fn (Vigencia $v) => $v->desde->depoisDe($referencia)));

        return [
            'id' => $r->id,
            'descricao' => $r->descricao,
            'valor' => $r->valorPadraoEm($r->fim !== null && $r->fim->antesDe($referencia) ? $r->fim : $referencia),
            'inicio' => $r->inicio,
            'fim' => $r->fim,
            'situacao' => $situacao,
            'ordem' => $situacao === 'encerrada' ? 1 : 0,
            'proximas' => $proximas,
        ];
    }

    private static function maisRecente(Competencia $a, Competencia $b): Competencia
    {
        return $a->depoisDe($b) ? $a : $b;
    }
}
