<?php

namespace App\Domain\Resumo;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * RD-08: os 12 meses de um ano e os totais anuais.
 * Mês sem resumo informado conta como zero.
 */
final readonly class ResumoAnual
{
    /**
     * @param  array<int, ResumoMensal|null>  $meses  chave 1..12
     */
    private function __construct(
        public int $ano,
        public array $meses,
        public Dinheiro $contasAPagar,
        public Dinheiro $recebidos,
        public Dinheiro $guardadoNoAno,
        public Dinheiro $restante,
    ) {}

    /**
     * @param  iterable<ResumoMensal>  $resumos
     */
    public static function de(int $ano, iterable $resumos): self
    {
        $meses = array_fill(1, 12, null);

        foreach ($resumos as $resumo) {
            $competencia = $resumo->competencia;

            if ($competencia->ano !== $ano) {
                throw new InvalidArgumentException("O resumo de {$competencia->formatar()} não pertence ao ano {$ano}.");
            }

            if ($meses[$competencia->mes] !== null) {
                throw new InvalidArgumentException("Resumo de {$competencia->formatar()} informado duas vezes.");
            }

            $meses[$competencia->mes] = $resumo;
        }

        $informados = array_filter($meses);
        $soma = fn (callable $campo) => Dinheiro::somarTodos(array_map($campo, $informados));

        return new self(
            $ano,
            $meses,
            $soma(fn (ResumoMensal $r) => $r->contasAPagar),
            $soma(fn (ResumoMensal $r) => $r->recebidos),
            $soma(fn (ResumoMensal $r) => $r->guardadoNoMes),
            $soma(fn (ResumoMensal $r) => $r->restante),
        );
    }

    public function mes(int $mes): ?ResumoMensal
    {
        Competencia::de($this->ano, $mes); // valida o mês

        return $this->meses[$mes];
    }
}
