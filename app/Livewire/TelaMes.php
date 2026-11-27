<?php

namespace App\Livewire;

use App\Application\Lancamentos\LancamentosAvulsos;
use App\Application\Mes\MontarMes;
use App\Application\Recorrencias\GerenciarRecorrencias;
use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Lancamento\CategoriaReceita;
use App\Infra\Persistencia\RepositorioRecorrencias;
use App\Livewire\Concerns\LeCampos;
use App\Models\Lancamento;
use Illuminate\Contracts\View\View;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Tela do mês (T11): resumo + despesas, receitas e guardado, com lançar, editar e excluir.
 * Os totais são só exibidos, nunca editáveis (RV-02, CA-03.2).
 */
class TelaMes extends Component
{
    use LeCampos;

    #[Locked]
    public string $competencia;

    /** 'despesa' | 'receita' | 'recorrencia' (valor da fixa só neste mês) | null (fechado) */
    public ?string $formulario = null;

    #[Locked]
    public ?int $editandoId = null;

    #[Locked]
    public ?int $recorrenciaId = null;

    public string $descricao = '';

    public string $valor = '';

    public string $categoria = 'salario';

    public function mount(string $competencia, ?string $novo = null): void
    {
        $this->competencia = (string) Competencia::deTexto($competencia);

        if (in_array($novo, ['despesa', 'receita'], true)) {
            $this->abrir($novo);
        }
    }

    public function abrir(string $tipo): void
    {
        if (! in_array($tipo, ['despesa', 'receita'], true)) {
            return;
        }

        $this->resetErrorBag();
        $this->reset('editandoId', 'recorrenciaId', 'descricao', 'valor', 'categoria');
        $this->formulario = $tipo;
    }

    /**
     * CA-04.2: abre a edição do valor de uma despesa fixa só neste mês.
     */
    public function editarRecorrencia(int $id, RepositorioRecorrencias $repositorio): void
    {
        $ocorrencia = $repositorio->porId($id)?->ocorrenciaEm(Competencia::deTexto($this->competencia));

        if ($ocorrencia === null) {
            return;
        }

        $this->resetErrorBag();
        $this->reset('editandoId', 'categoria');
        $this->formulario = 'recorrencia';
        $this->recorrenciaId = $id;
        $this->descricao = $ocorrencia->descricao;
        $this->valor = self::valorParaCampo($ocorrencia->valor);
    }

    /**
     * CA-04.2: o mês volta a usar o valor padrão da despesa fixa.
     */
    public function voltarAoPadrao(int $id, GerenciarRecorrencias $recorrencias): void
    {
        try {
            $recorrencias->desfazerValorDoMes($id, Competencia::deTexto($this->competencia));
        } catch (InvalidArgumentException) {
            return;
        }

        if ($this->recorrenciaId === $id) {
            $this->cancelar();
        }
    }

    public function editar(int $id): void
    {
        $lancamento = Lancamento::query()->find($id);

        if ($lancamento === null || $lancamento->origem !== Lancamento::AVULSO || (string) $lancamento->competencia !== $this->competencia) {
            return;
        }

        $this->resetErrorBag();
        $this->formulario = $lancamento->tipo;
        $this->editandoId = $lancamento->id;
        $this->descricao = $lancamento->descricao;
        $this->valor = self::valorParaCampo(Dinheiro::centavos($lancamento->valor_centavos));
        $this->categoria = $lancamento->categoria_receita ?? 'salario';
    }

    public function cancelar(): void
    {
        $this->resetErrorBag();
        $this->reset('formulario', 'editandoId', 'recorrenciaId', 'descricao', 'valor', 'categoria');
    }

    public function salvar(LancamentosAvulsos $lancamentos, GerenciarRecorrencias $recorrencias): void
    {
        $this->resetErrorBag();

        if ($this->formulario === 'recorrencia') {
            $this->salvarValorDaRecorrencia($recorrencias);

            return;
        }

        $valor = $this->lerValor('valor');
        $categoria = CategoriaReceita::tryFrom($this->categoria);

        if ($this->formulario === 'receita' && $categoria === null) {
            $this->addError('categoria', 'Escolha a categoria da receita.');
        }

        if (trim($this->descricao) === '') {
            $this->addError('descricao', 'Informe a descrição.');
        }

        if ($this->getErrorBag()->isNotEmpty() || $valor === null) {
            return;
        }

        try {
            $competencia = Competencia::deTexto($this->competencia);

            match (true) {
                $this->editandoId !== null => $lancamentos->editar($this->editandoId, $this->descricao, $valor, $categoria),
                $this->formulario === 'receita' => $lancamentos->lancarReceita($competencia, $this->descricao, $valor, $categoria),
                default => $lancamentos->lancarDespesa($competencia, $this->descricao, $valor),
            };
        } catch (InvalidArgumentException $e) {
            $this->addError('descricao', $e->getMessage());

            return;
        }

        $this->cancelar();
    }

    public function excluir(int $id, LancamentosAvulsos $lancamentos): void
    {
        $lancamento = Lancamento::query()->find($id);

        if ($lancamento === null || (string) $lancamento->competencia !== $this->competencia) {
            return;
        }

        try {
            $lancamentos->excluir($id);
        } catch (InvalidArgumentException) {
            return;
        }

        if ($this->editandoId === $id) {
            $this->cancelar();
        }
    }

    private function salvarValorDaRecorrencia(GerenciarRecorrencias $recorrencias): void
    {
        $valor = $this->lerValor('valor');

        if ($valor === null || $this->recorrenciaId === null) {
            return;
        }

        try {
            $recorrencias->valorSoNoMes($this->recorrenciaId, Competencia::deTexto($this->competencia), $valor);
        } catch (InvalidArgumentException $e) {
            $this->addError('valor', $e->getMessage());

            return;
        }

        $this->cancelar();
    }

    public function render(MontarMes $montarMes): View
    {
        return view('livewire.tela-mes', [
            'mes' => $montarMes->executar(Competencia::deTexto($this->competencia)),
            'categorias' => CategoriaReceita::cases(),
        ]);
    }
}
