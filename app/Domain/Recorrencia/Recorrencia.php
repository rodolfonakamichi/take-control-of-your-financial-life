<?php

namespace App\Domain\Recorrencia;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * Despesa fixa que se repete todo mês entre `inicio` e `fim` (fim nulo = sem fim) — RD-07.
 *
 * - O valor padrão vem de vigências: a de `desde` mais recente que não passa do mês.
 * - Um mês pode ter o valor editado só nele (exceção), que vence qualquer vigência (CA-04.2).
 *
 * Imutável: cada alteração devolve uma nova recorrência.
 */
final readonly class Recorrencia
{
    /**
     * @param  list<Vigencia>  $vigencias  ordenadas por `desde`, a primeira em `inicio`
     * @param  array<string, Dinheiro>  $excecoes  chave "AAAA-MM"
     */
    private function __construct(
        public ?int $id,
        public string $descricao,
        public Competencia $inicio,
        public ?Competencia $fim,
        public array $vigencias,
        public array $excecoes,
    ) {}

    public static function nova(
        string $descricao,
        Dinheiro $valor,
        Competencia $inicio,
        ?Competencia $fim = null,
        ?int $id = null,
    ): self {
        return self::reconstituir($id, $descricao, $inicio, $fim, [new Vigencia($inicio, $valor)]);
    }

    /**
     * Monta a recorrência a partir do que está gravado, validando a consistência.
     *
     * @param  iterable<Vigencia>  $vigencias
     * @param  array<string, Dinheiro>  $excecoes  chave "AAAA-MM"
     */
    public static function reconstituir(
        ?int $id,
        string $descricao,
        Competencia $inicio,
        ?Competencia $fim,
        iterable $vigencias,
        array $excecoes = [],
    ): self {
        $descricao = trim($descricao);
        if ($descricao === '') {
            throw new InvalidArgumentException('Informe a descrição da recorrência.');
        }

        if ($fim !== null && $fim->antesDe($inicio)) {
            throw new InvalidArgumentException('O fim da recorrência não pode ser antes do início.');
        }

        $porMes = [];
        foreach ($vigencias as $vigencia) {
            $chave = (string) $vigencia->desde;
            if (isset($porMes[$chave])) {
                throw new InvalidArgumentException("Duas vigências começando em {$vigencia->desde->formatar()}.");
            }
            $porMes[$chave] = $vigencia;
        }
        ksort($porMes, SORT_STRING);
        $ordenadas = array_values($porMes);

        if ($ordenadas === [] || ! $ordenadas[0]->desde->igual($inicio)) {
            throw new InvalidArgumentException('A primeira vigência da recorrência deve começar no início.');
        }

        foreach ($excecoes as $chave => $valor) {
            $competencia = Competencia::deTexto($chave);
            if (! $competencia->entre($inicio, $fim)) {
                throw new InvalidArgumentException("A recorrência não está ativa em {$competencia->formatar()}.");
            }
            if (! $valor->ehPositivo()) {
                throw new InvalidArgumentException('O valor da recorrência deve ser maior que zero.');
            }
        }
        ksort($excecoes, SORT_STRING);

        return new self($id, $descricao, $inicio, $fim, $ordenadas, $excecoes);
    }

    public function ativaEm(Competencia $competencia): bool
    {
        return $competencia->entre($this->inicio, $this->fim);
    }

    /**
     * Valor padrão vigente no mês, ignorando exceções.
     */
    public function valorPadraoEm(Competencia $competencia): Dinheiro
    {
        $valor = $this->vigencias[0]->valor;

        foreach ($this->vigencias as $vigencia) {
            if ($vigencia->desde->depoisDe($competencia)) {
                break;
            }
            $valor = $vigencia->valor;
        }

        return $valor;
    }

    /**
     * RD-07: no máximo uma ocorrência por mês; nenhuma fora do intervalo ativo.
     */
    public function ocorrenciaEm(Competencia $competencia): ?OcorrenciaRecorrencia
    {
        if (! $this->ativaEm($competencia)) {
            return null;
        }

        $excecao = $this->excecoes[(string) $competencia] ?? null;

        return new OcorrenciaRecorrencia(
            $this->id,
            $this->descricao,
            $competencia,
            $excecao ?? $this->valorPadraoEm($competencia),
            $excecao !== null,
        );
    }

    /**
     * CA-04.2: muda o valor só no mês informado.
     */
    public function comValorNoMes(Competencia $competencia, Dinheiro $valor): self
    {
        $excecoes = $this->excecoes;
        $excecoes[(string) $competencia] = $valor;

        return $this->com(excecoes: $excecoes);
    }

    /**
     * Desfaz a edição do mês: volta a usar o valor padrão.
     */
    public function semValorNoMes(Competencia $competencia): self
    {
        $excecoes = $this->excecoes;
        unset($excecoes[(string) $competencia]);

        return $this->com(excecoes: $excecoes);
    }

    /**
     * CA-04.3: novo valor padrão de `$desde` em diante. Vigências posteriores são descartadas;
     * meses editados à mão mantêm o valor editado.
     */
    public function alterarValorAPartirDe(Competencia $desde, Dinheiro $valor): self
    {
        if ($desde->antesDe($this->inicio)) {
            throw new InvalidArgumentException('Não é possível alterar o valor antes do início da recorrência.');
        }

        $mantidas = array_filter($this->vigencias, fn (Vigencia $v) => $v->desde->antesDe($desde));
        $mantidas[] = new Vigencia($desde, $valor);

        return $this->com(vigencias: $mantidas);
    }

    /**
     * CA-04.4: a recorrência deixa de existir nos meses depois de `$ultimoMes`.
     * Exceções posteriores ao novo fim são descartadas.
     */
    public function encerrarEm(Competencia $ultimoMes): self
    {
        $excecoes = array_filter(
            $this->excecoes,
            fn (string $chave) => ! Competencia::deTexto($chave)->depoisDe($ultimoMes),
            ARRAY_FILTER_USE_KEY,
        );

        return $this->com(fim: $ultimoMes, excecoes: $excecoes, mudarFim: true);
    }

    public function renomear(string $descricao): self
    {
        return self::reconstituir($this->id, $descricao, $this->inicio, $this->fim, $this->vigencias, $this->excecoes);
    }

    /**
     * @param  list<Vigencia>|null  $vigencias
     * @param  array<string, Dinheiro>|null  $excecoes
     */
    private function com(
        ?array $vigencias = null,
        ?array $excecoes = null,
        ?Competencia $fim = null,
        bool $mudarFim = false,
    ): self {
        return self::reconstituir(
            $this->id,
            $this->descricao,
            $this->inicio,
            $mudarFim ? $fim : $this->fim,
            $vigencias ?? $this->vigencias,
            $excecoes ?? $this->excecoes,
        );
    }
}
