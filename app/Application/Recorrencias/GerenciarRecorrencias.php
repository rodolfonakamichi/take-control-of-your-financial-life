<?php

namespace App\Application\Recorrencias;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use App\Domain\Recorrencia\Recorrencia;
use App\Infra\Persistencia\RepositorioRecorrencias;
use InvalidArgumentException;

/**
 * RF-04: casos de uso das despesas fixas. As regras ficam no domínio (Recorrencia);
 * aqui só se carrega, aplica e grava. Erros de regra chegam como InvalidArgumentException.
 */
final class GerenciarRecorrencias
{
    public function __construct(private RepositorioRecorrencias $repositorio) {}

    public function criar(string $descricao, Dinheiro $valor, Competencia $inicio, ?Competencia $fim = null): Recorrencia
    {
        $recorrencia = Recorrencia::nova($descricao, $valor, $inicio);

        if ($fim !== null) {
            $recorrencia = $recorrencia->encerrarEm($fim);
        }

        return $this->repositorio->salvar($recorrencia);
    }

    /**
     * CA-04.2
     */
    public function valorSoNoMes(int $id, Competencia $competencia, Dinheiro $valor): Recorrencia
    {
        return $this->repositorio->salvar($this->carregar($id)->comValorNoMes($competencia, $valor));
    }

    /**
     * CA-04.2: volta o mês ao valor padrão.
     */
    public function desfazerValorDoMes(int $id, Competencia $competencia): Recorrencia
    {
        return $this->repositorio->salvar($this->carregar($id)->semValorNoMes($competencia));
    }

    /**
     * CA-04.3
     */
    public function alterarValorAPartirDe(int $id, Competencia $desde, Dinheiro $valor): Recorrencia
    {
        return $this->repositorio->salvar($this->carregar($id)->alterarValorAPartirDe($desde, $valor));
    }

    /**
     * CA-04.4
     */
    public function encerrar(int $id, Competencia $ultimoMes): Recorrencia
    {
        return $this->repositorio->salvar($this->carregar($id)->encerrarEm($ultimoMes));
    }

    public function renomear(int $id, string $descricao): Recorrencia
    {
        return $this->repositorio->salvar($this->carregar($id)->renomear($descricao));
    }

    public function excluir(int $id): void
    {
        $this->carregar($id);
        $this->repositorio->excluir($id);
    }

    private function carregar(int $id): Recorrencia
    {
        return $this->repositorio->porId($id) ?? throw new InvalidArgumentException('Despesa fixa não encontrada.');
    }
}
