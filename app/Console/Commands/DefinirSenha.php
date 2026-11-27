<?php

namespace App\Console\Commands;

use App\Application\Acesso\Dono;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * CA-18.2: define (ou redefine) a senha única pelo terminal.
 * Não existe tela de "primeiro acesso", para que ninguém na rede defina a senha antes do dono.
 */
class DefinirSenha extends Command
{
    protected $signature = 'financas:definir-senha';

    protected $description = 'Define ou redefine a senha de acesso ao sistema';

    public function handle(): int
    {
        $senha = (string) $this->secret('Nova senha (mínimo '.Dono::SENHA_MINIMA.' caracteres)');

        if (mb_strlen($senha) < Dono::SENHA_MINIMA) {
            $this->error('A senha precisa ter pelo menos '.Dono::SENHA_MINIMA.' caracteres.');

            return self::FAILURE;
        }

        if ($senha !== (string) $this->secret('Repita a senha')) {
            $this->error('As senhas não conferem.');

            return self::FAILURE;
        }

        $dono = User::query()->firstOrNew(['email' => Dono::EMAIL]);
        $dono->name = 'Dono';
        $dono->password = $senha; // o model aplica o hash (cast "hashed") — CA-18.3
        $dono->setRememberToken(Str::random(60)); // derruba "lembrar neste aparelho" antigos
        $dono->save();

        $this->info('Senha definida. Acesse o sistema e entre com ela.');

        return self::SUCCESS;
    }
}
