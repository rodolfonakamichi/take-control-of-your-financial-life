<?php

namespace App\Domain\Guardado;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * O histórico do guardado (poupança e investimentos): um total único, sem separar por aplicação.
 *
 * - RD-03: Guardado no mês = Σ guardar − Σ retirar do mês.
 * - RD-05: Total guardado(m) = Total guardado(m−1) + Guardado no mês(m), encadeando entre anos;
 *          antes do primeiro movimento, vale o guardado inicial.
 * - RV-07: o total nunca pode ficar negativo em nenhum mês.
 *
 * Imutável: adicionar ou remover devolve um novo livro, já validado. Para editar um movimento,
 * remova o antigo e adicione o novo.
 */
final readonly class LivroGuardado
{
    /**
     * @param  array<string, list<MovimentoGuardado>>  $movimentosPorMes  chave "AAAA-MM", ordenada
     */
    private function __construct(
        public Dinheiro $guardadoInicial,
        private array $movimentosPorMes,
    ) {}

    /**
     * @param  iterable<array{0: Competencia, 1: MovimentoGuardado}>  $movimentos  pares [competência, movimento]
     *
     * @throws GuardadoFicariaNegativo se o histórico informado já deixa algum mês negativo
     */
    public static function de(Dinheiro $guardadoInicial, iterable $movimentos = []): self
    {
        if ($guardadoInicial->ehNegativo()) {
            throw new InvalidArgumentException('O guardado inicial não pode ser negativo.');
        }

        $porMes = [];
        foreach ($movimentos as [$competencia, $movimento]) {
            $porMes[(string) $competencia][] = $movimento;
        }

        return self::validado($guardadoInicial, $porMes);
    }

    /**
     * @throws GuardadoFicariaNegativo
     */
    public function adicionar(Competencia $competencia, MovimentoGuardado $movimento): self
    {
        $porMes = $this->movimentosPorMes;
        $porMes[(string) $competencia][] = $movimento;

        return self::validado($this->guardadoInicial, $porMes);
    }

    /**
     * Remove um movimento igual ao informado (mesmo tipo, valor e descrição) daquele mês.
     *
     * @throws GuardadoFicariaNegativo se a remoção (ex.: de um "guardar" antigo) deixar algum mês negativo
     */
    public function remover(Competencia $competencia, MovimentoGuardado $movimento): self
    {
        $chave = (string) $competencia;
        $doMes = $this->movimentosPorMes[$chave] ?? [];

        foreach ($doMes as $i => $existente) {
            if ($existente == $movimento) {
                unset($doMes[$i]);
                $porMes = $this->movimentosPorMes;
                $porMes[$chave] = array_values($doMes);

                if ($porMes[$chave] === []) {
                    unset($porMes[$chave]);
                }

                return self::validado($this->guardadoInicial, $porMes);
            }
        }

        throw new InvalidArgumentException("Movimento não encontrado em {$competencia->formatar()}.");
    }

    /**
     * Troca o guardado inicial (CA-06.6), revalidando todo o histórico.
     *
     * @throws GuardadoFicariaNegativo
     */
    public function comGuardadoInicial(Dinheiro $guardadoInicial): self
    {
        if ($guardadoInicial->ehNegativo()) {
            throw new InvalidArgumentException('O guardado inicial não pode ser negativo.');
        }

        return self::validado($guardadoInicial, $this->movimentosPorMes);
    }

    /**
     * RD-03.
     */
    public function guardadoNoMes(Competencia $competencia): Dinheiro
    {
        return self::somarEfeitos($this->movimentosPorMes[(string) $competencia] ?? []);
    }

    /**
     * RD-05: total guardado ao fim do mês informado.
     */
    public function totalAte(Competencia $competencia): Dinheiro
    {
        $total = $this->guardadoInicial;
        $limite = (string) $competencia;

        foreach ($this->movimentosPorMes as $chave => $movimentos) {
            if (strcmp($chave, $limite) > 0) {
                break;
            }

            $total = $total->somar(self::somarEfeitos($movimentos));
        }

        return $total;
    }

    /**
     * Total guardado ao fim de cada mês do intervalo (para a visão anual e os gráficos).
     *
     * @return array<string, Dinheiro> chave "AAAA-MM"
     */
    public function serie(Competencia $de, Competencia $ate): array
    {
        if ($ate->antesDe($de)) {
            throw new InvalidArgumentException('O fim do intervalo é anterior ao início.');
        }

        $serie = [];
        $total = $this->totalAte($de->anterior());

        for ($mes = $de; ! $mes->depoisDe($ate); $mes = $mes->proxima()) {
            $total = $total->somar($this->guardadoNoMes($mes));
            $serie[(string) $mes] = $total;
        }

        return $serie;
    }

    /**
     * @param  array<string, list<MovimentoGuardado>>  $porMes
     */
    private static function validado(Dinheiro $guardadoInicial, array $porMes): self
    {
        ksort($porMes, SORT_STRING);

        $total = $guardadoInicial;
        foreach ($porMes as $chave => $movimentos) {
            $total = $total->somar(self::somarEfeitos($movimentos));

            if ($total->ehNegativo()) {
                throw new GuardadoFicariaNegativo(Competencia::deTexto($chave), $total);
            }
        }

        return new self($guardadoInicial, $porMes);
    }

    /**
     * @param  list<MovimentoGuardado>  $movimentos
     */
    private static function somarEfeitos(array $movimentos): Dinheiro
    {
        return Dinheiro::somarTodos(array_map(fn (MovimentoGuardado $m) => $m->efeito(), $movimentos));
    }
}
