<?php

namespace App\Infra\Persistencia;

use App\Domain\Dinheiro;
use App\Models\Configuracao;

/**
 * Configurações chave/valor (design §3.1).
 */
final class Configuracoes
{
    public const GUARDADO_INICIAL = 'guardado_inicial_centavos';

    public function obter(string $chave, ?string $padrao = null): ?string
    {
        return Configuracao::query()->find($chave)?->valor ?? $padrao;
    }

    public function definir(string $chave, string $valor): void
    {
        Configuracao::query()->updateOrCreate(['chave' => $chave], ['valor' => $valor]);
    }

    public function guardadoInicial(): Dinheiro
    {
        return Dinheiro::centavos((int) $this->obter(self::GUARDADO_INICIAL, '0'));
    }

    public function definirGuardadoInicial(Dinheiro $valor): void
    {
        $this->definir(self::GUARDADO_INICIAL, (string) $valor->centavos);
    }
}
