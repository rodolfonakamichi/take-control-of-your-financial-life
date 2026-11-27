<?php

namespace App\Livewire\Concerns;

use App\Domain\Competencia;
use App\Domain\Dinheiro;
use InvalidArgumentException;

/**
 * Converte o que foi digitado nos formulários em objetos do domínio,
 * registrando a mensagem de erro no campo quando não der.
 */
trait LeCampos
{
    /**
     * Valor obrigatório, no formato brasileiro e maior que zero (RV-01).
     */
    protected function lerValor(string $campo): ?Dinheiro
    {
        $texto = trim((string) $this->{$campo});

        if ($texto === '') {
            $this->addError($campo, 'Informe o valor.');

            return null;
        }

        try {
            $valor = Dinheiro::deTexto($texto);
        } catch (InvalidArgumentException) {
            $this->addError($campo, 'Valor inválido. Use o formato 1.234,56.');

            return null;
        }

        if (! $valor->ehPositivo()) {
            $this->addError($campo, 'O valor deve ser maior que zero.');

            return null;
        }

        return $valor;
    }

    /**
     * Mês no formato AAAA-MM (o que o <input type="month"> envia).
     */
    protected function lerCompetencia(string $campo, bool $obrigatorio = true): ?Competencia
    {
        $texto = trim((string) $this->{$campo});

        if ($texto === '') {
            if ($obrigatorio) {
                $this->addError($campo, 'Informe o mês.');
            }

            return null;
        }

        try {
            return Competencia::deTexto($texto);
        } catch (InvalidArgumentException) {
            $this->addError($campo, 'Mês inválido. Use o formato AAAA-MM.');

            return null;
        }
    }

    protected static function valorParaCampo(Dinheiro $valor): string
    {
        return number_format($valor->centavos / 100, 2, ',', '.');
    }
}
