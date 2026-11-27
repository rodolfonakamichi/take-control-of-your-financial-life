<?php

namespace App\Domain\Guardado;

use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * Um movimento do guardado (poupança e investimentos) num mês: guardar ou retirar.
 */
final readonly class MovimentoGuardado
{
    public function __construct(
        public TipoMovimento $tipo,
        public Dinheiro $valor,
        public ?string $descricao = null,
    ) {
        if (! $valor->ehPositivo()) {
            throw new InvalidArgumentException('O valor do movimento deve ser maior que zero.');
        }
    }

    public static function guardar(Dinheiro $valor, ?string $descricao = null): self
    {
        return new self(TipoMovimento::Guardar, $valor, $descricao);
    }

    public static function retirar(Dinheiro $valor, ?string $descricao = null): self
    {
        return new self(TipoMovimento::Retirar, $valor, $descricao);
    }

    /**
     * Efeito no total guardado: positivo ao guardar, negativo ao retirar.
     */
    public function efeito(): Dinheiro
    {
        return $this->tipo === TipoMovimento::Guardar ? $this->valor : $this->valor->negar();
    }
}
