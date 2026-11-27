<?php

namespace App\Application\Parcelamentos;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Parcelamento\Parcelamento;
use App\Infra\Persistencia\RepositorioParcelamentos;
use App\Infra\Tempo\Relogio;
use InvalidArgumentException;

/**
 * RF-05: casos de uso das compras parceladas. As regras (RD-06, RD-16) ficam no domínio;
 * aqui entra o "mês atual" do relógio. Erros de regra chegam como InvalidArgumentException
 * ou DomainException.
 */
final class GerenciarParcelamentos
{
    public function __construct(
        private RepositorioParcelamentos $repositorio,
        private Relogio $relogio,
    ) {}

    /**
     * CA-05.1
     */
    public function criar(string $descricao, Dinheiro $valorParcela, int $totalParcelas, Competencia $primeira): Parcelamento
    {
        return $this->repositorio->salvar(Parcelamento::novo($descricao, $valorParcela, $totalParcelas, $primeira));
    }

    /**
     * CA-05.3: só parcelas do mês atual em diante e ainda não pagas mudam.
     */
    public function alterarValorParcela(int $id, Dinheiro $valor): Parcelamento
    {
        return $this->repositorio->salvar(
            $this->carregar($id)->alterarValorParcela($valor, $this->relogio->mesAtual()),
        );
    }

    /**
     * CA-05.4: somem as parcelas do mês atual em diante ainda não pagas; o resto fica.
     */
    public function cancelar(int $id): Parcelamento
    {
        return $this->repositorio->salvar(
            $this->carregar($id)->cancelar($this->relogio->mesAtual(), $this->relogio->agora()),
        );
    }

    public function renomear(int $id, string $descricao): Parcelamento
    {
        return $this->repositorio->salvar($this->carregar($id)->renomear($descricao));
    }

    /**
     * CA-05.5: exclusão total, inclusive parcelas passadas. A tela só chama depois da confirmação.
     */
    public function excluirTudo(int $id): void
    {
        $this->carregar($id);
        $this->repositorio->excluir($id);
    }

    private function carregar(int $id): Parcelamento
    {
        return $this->repositorio->porId($id) ?? throw new InvalidArgumentException('Parcelamento não encontrado.');
    }
}
