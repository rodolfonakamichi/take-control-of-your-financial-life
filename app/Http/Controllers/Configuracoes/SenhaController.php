<?php

namespace App\Http\Controllers\Configuracoes;

use App\Application\Acesso\Dono;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * CA-18.2: trocar a senha informando a atual.
 */
class SenhaController extends Controller
{
    public function edit(): View
    {
        return view('configuracoes.senha');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'senha_atual' => ['required', 'current_password:web'],
            'nova_senha' => ['required', 'string', 'min:'.Dono::SENHA_MINIMA, 'confirmed'],
        ], [
            'senha_atual.required' => 'Informe a senha atual.',
            'senha_atual.current_password' => 'A senha atual está incorreta.',
            'nova_senha.required' => 'Informe a nova senha.',
            'nova_senha.min' => 'A nova senha precisa ter pelo menos '.Dono::SENHA_MINIMA.' caracteres.',
            'nova_senha.confirmed' => 'A confirmação não confere com a nova senha.',
        ]);

        $dono = $request->user();
        $dono->password = $request->string('nova_senha')->toString();
        $dono->setRememberToken(Str::random(60)); // outros aparelhos "lembrados" precisam entrar de novo
        $dono->save();

        return redirect()->route('configuracoes.senha')->with('status', 'Senha alterada.');
    }
}
