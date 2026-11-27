<?php

namespace App\Livewire;

use App\Application\Lancamentos\LancamentosAvulsos;
use App\Application\Mes\MontarMes;
use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Lancamento\CategoriaReceita;
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
    #[Locked]
    public string $competencia;

    /** 'despesa' | 'receita' | null (formulário fechado) */
    public ?string $formulario = null;

    #[Locked]
    public ?int $editandoId = null;

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
        $this->reset('editandoId', 'descricao', 'valor', 'categoria');
        $this->formulario = $tipo;
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
        $this->valor = number_format($lancamento->valor_centavos / 100, 2, ',', '.');
        $this->categoria = $lancamento->categoria_receita ?? 'salario';
    }

    public function cancelar(): void
    {
        $this->resetErrorBag();
        $this->reset('formulario', 'editandoId', 'descricao', 'valor', 'categoria');
    }

    public function salvar(LancamentosAvulsos $lancamentos): void
    {
        $this->resetErrorBag();

        $valor = $this->lerValor();
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

    public function render(MontarMes $montarMes): View
    {
        return view('livewire.tela-mes', [
            'mes' => $montarMes->executar(Competencia::deTexto($this->competencia)),
            'categorias' => CategoriaReceita::cases(),
        ]);
    }

    /**
     * RV-01 / CA-01.2: valor obrigatório, no formato brasileiro e maior que zero.
     */
    private function lerValor(): ?Dinheiro
    {
        if (trim($this->valor) === '') {
            $this->addError('valor', 'Informe o valor.');

            return null;
        }

        try {
            $valor = Dinheiro::deTexto($this->valor);
        } catch (InvalidArgumentException) {
            $this->addError('valor', 'Valor inválido. Use o formato 1.234,56.');

            return null;
        }

        if (! $valor->ehPositivo()) {
            $this->addError('valor', 'O valor deve ser maior que zero.');

            return null;
        }

        return $valor;
    }
}
