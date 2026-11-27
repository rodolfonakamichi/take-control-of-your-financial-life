<?php

namespace App\Domain\Parcelamento;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use DateTimeImmutable;
use DomainException;
use InvalidArgumentException;

/**
 * Compra parcelada ou dívida: N parcelas em meses consecutivos (RD-06).
 *
 * As parcelas são gravadas na criação (design §3.2). Alterar o valor ou cancelar
 * só afeta parcelas do mês atual em diante que ainda não foram pagas (RD-16).
 *
 * Imutável: cada operação devolve um novo parcelamento.
 */
final readonly class Parcelamento
{
    /**
     * @param  list<Parcela>  $parcelas  ordenadas pelo número
     */
    private function __construct(
        public ?int $id,
        public string $descricao,
        public Dinheiro $valorParcela,
        public int $totalParcelas,
        public Competencia $primeiraCompetencia,
        public array $parcelas,
        public ?DateTimeImmutable $canceladoEm,
    ) {}

    public static function novo(
        string $descricao,
        Dinheiro $valorParcela,
        int $totalParcelas,
        Competencia $primeira,
        ?int $id = null,
    ): self {
        return self::reconstituir(
            $id,
            $descricao,
            $valorParcela,
            $totalParcelas,
            $primeira,
            GeradorParcelas::gerar($primeira, $totalParcelas, $valorParcela),
        );
    }

    /**
     * Monta o parcelamento a partir do que está gravado, validando a consistência.
     * Após um cancelamento, parte das parcelas pode não existir mais.
     *
     * @param  iterable<Parcela>  $parcelas
     */
    public static function reconstituir(
        ?int $id,
        string $descricao,
        Dinheiro $valorParcela,
        int $totalParcelas,
        Competencia $primeira,
        iterable $parcelas,
        ?DateTimeImmutable $canceladoEm = null,
    ): self {
        $descricao = self::validarDescricao($descricao);

        if ($totalParcelas < 1) {
            throw new InvalidArgumentException('O parcelamento precisa de pelo menos 1 parcela.');
        }

        if (! $valorParcela->ehPositivo()) {
            throw new InvalidArgumentException('O valor da parcela deve ser maior que zero.');
        }

        $porNumero = [];
        foreach ($parcelas as $parcela) {
            if ($parcela->numero < 1 || $parcela->numero > $totalParcelas || $parcela->total !== $totalParcelas) {
                throw new InvalidArgumentException("Parcela {$parcela->numero}/{$parcela->total} não pertence a um parcelamento em {$totalParcelas}×.");
            }

            if (isset($porNumero[$parcela->numero])) {
                throw new InvalidArgumentException("Parcela {$parcela->numero}/{$totalParcelas} repetida.");
            }

            $esperada = $primeira->somarMeses($parcela->numero - 1);
            if (! $parcela->competencia->igual($esperada)) {
                throw new InvalidArgumentException("A parcela {$parcela->numero}/{$totalParcelas} deveria cair em {$esperada->formatar()}.");
            }

            $porNumero[$parcela->numero] = $parcela;
        }
        ksort($porNumero);

        if ($canceladoEm === null && count($porNumero) !== $totalParcelas) {
            throw new InvalidArgumentException('Um parcelamento ativo precisa ter todas as parcelas.');
        }

        return new self($id, $descricao, $valorParcela, $totalParcelas, $primeira, array_values($porNumero), $canceladoEm);
    }

    public function cancelado(): bool
    {
        return $this->canceladoEm !== null;
    }

    public function ultimaCompetencia(): Competencia
    {
        return $this->primeiraCompetencia->somarMeses($this->totalParcelas - 1);
    }

    public function parcelaEm(Competencia $competencia): ?Parcela
    {
        foreach ($this->parcelas as $parcela) {
            if ($parcela->competencia->igual($competencia)) {
                return $parcela;
            }
        }

        return null;
    }

    public function parcela(int $numero): ?Parcela
    {
        foreach ($this->parcelas as $parcela) {
            if ($parcela->numero === $numero) {
                return $parcela;
            }
        }

        return null;
    }

    /**
     * CA-05.3 / RD-16: novo valor só para parcelas do mês atual em diante ainda não pagas.
     */
    public function alterarValorParcela(Dinheiro $novoValor, Competencia $mesAtual): self
    {
        $this->exigirAtivo();

        if (! $novoValor->ehPositivo()) {
            throw new InvalidArgumentException('O valor da parcela deve ser maior que zero.');
        }

        $parcelas = array_map(
            fn (Parcela $p) => $p->podeSerAlterada($mesAtual) ? $p->comValor($novoValor) : $p,
            $this->parcelas,
        );

        return new self($this->id, $this->descricao, $novoValor, $this->totalParcelas, $this->primeiraCompetencia, $parcelas, null);
    }

    /**
     * CA-05.4 / RD-16: remove as parcelas do mês atual em diante ainda não pagas; o resto permanece.
     */
    public function cancelar(Competencia $mesAtual, DateTimeImmutable $em): self
    {
        $this->exigirAtivo();

        $mantidas = array_values(array_filter(
            $this->parcelas,
            fn (Parcela $p) => ! $p->podeSerAlterada($mesAtual),
        ));

        return new self($this->id, $this->descricao, $this->valorParcela, $this->totalParcelas, $this->primeiraCompetencia, $mantidas, $em);
    }

    public function marcarParcela(int $numero, bool $paga): self
    {
        if ($this->parcela($numero) === null) {
            throw new InvalidArgumentException("A parcela {$numero}/{$this->totalParcelas} não existe.");
        }

        $parcelas = array_map(
            fn (Parcela $p) => $p->numero === $numero ? $p->comPagamento($paga) : $p,
            $this->parcelas,
        );

        return new self($this->id, $this->descricao, $this->valorParcela, $this->totalParcelas, $this->primeiraCompetencia, $parcelas, $this->canceladoEm);
    }

    public function renomear(string $descricao): self
    {
        return new self($this->id, self::validarDescricao($descricao), $this->valorParcela, $this->totalParcelas, $this->primeiraCompetencia, $this->parcelas, $this->canceladoEm);
    }

    private function exigirAtivo(): void
    {
        if ($this->cancelado()) {
            throw new DomainException('Este parcelamento já foi cancelado.');
        }
    }

    private static function validarDescricao(string $descricao): string
    {
        $descricao = trim($descricao);

        if ($descricao === '') {
            throw new InvalidArgumentException('Informe a descrição do parcelamento.');
        }

        // CA-05.2: a numeração "(k/N)" é do sistema; não pode vir digitada na descrição.
        if (preg_match('/\(\s*\d+\s*\/\s*\d+\s*\)\s*$/', $descricao)) {
            throw new InvalidArgumentException('Não inclua a numeração da parcela na descrição: ela é gerada automaticamente.');
        }

        return $descricao;
    }
}
