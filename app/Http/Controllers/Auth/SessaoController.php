<?php

namespace App\Http\Controllers\Auth;

use App\Application\Acesso\Dono;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * RF-18: entrar com a senha única e sair.
 */
class SessaoController extends Controller
{
    public function create(): View
    {
        return view('auth.entrar', ['senhaDefinida' => Dono::senhaDefinida()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['senha' => ['required', 'string']], ['senha.required' => 'Informe a senha.']);

        $chave = 'entrar:'.$request->ip();

        // CA-18.5: 5 tentativas erradas em 1 minuto bloqueiam o aparelho por 1 minuto.
        if (RateLimiter::tooManyAttempts($chave, Dono::TENTATIVAS)) {
            throw ValidationException::withMessages([
                'senha' => 'Muitas tentativas. Tente de novo em '.RateLimiter::availableIn($chave).' segundos.',
            ]);
        }

        $guard = Auth::guard('web');
        $guard->setRememberDuration(Dono::LEMBRAR_MINUTOS); // CA-18.4

        $entrou = $guard->attempt(
            ['email' => Dono::EMAIL, 'password' => $request->string('senha')->toString()],
            $request->boolean('lembrar'),
        );

        if (! $entrou) {
            RateLimiter::hit($chave, Dono::BLOQUEIO_SEGUNDOS);

            throw ValidationException::withMessages(['senha' => 'Senha incorreta.']);
        }

        RateLimiter::clear($chave);
        $request->session()->regenerate();

        return redirect()->intended(route('inicio'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
