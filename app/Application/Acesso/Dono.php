<?php

namespace App\Application\Acesso;

use App\Models\User;

/**
 * RF-18: o sistema tem um único usuário (o dono) e só pede a senha.
 * O e-mail é fixo e interno; nunca aparece na tela.
 */
final class Dono
{
    public const EMAIL = 'dono@financas.local';

    public const SENHA_MINIMA = 6;

    public const LEMBRAR_MINUTOS = 60 * 24 * 30; // CA-18.4: 30 dias

    public const TENTATIVAS = 5; // CA-18.5

    public const BLOQUEIO_SEGUNDOS = 60;

    public static function usuario(): ?User
    {
        return User::query()->where('email', self::EMAIL)->first();
    }

    public static function senhaDefinida(): bool
    {
        return self::usuario() !== null;
    }
}
