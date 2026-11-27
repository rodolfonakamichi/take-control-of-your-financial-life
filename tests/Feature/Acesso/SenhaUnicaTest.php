<?php

use App\Application\Acesso\Dono;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Cookie;

// T09 — RF-18: senha única de acesso.

function definirSenha(string $senha = 'segredo123'): User
{
    return User::query()->create(['name' => 'Dono', 'email' => Dono::EMAIL, 'password' => $senha]);
}

function cookieLembrar($response): ?Cookie
{
    foreach ($response->headers->getCookies() as $cookie) {
        if (str_starts_with($cookie->getName(), 'remember_web_')) {
            return $cookie;
        }
    }

    return null;
}

describe('CA-18.1 toda página exige estar autenticado', function () {
    it('manda para a tela de senha quem não entrou', function (string $metodo, string $url) {
        $this->call($metodo, $url)->assertRedirect(route('login'));
    })->with([
        ['GET', '/'],
        ['GET', '/configuracoes/senha'],
        ['PUT', '/configuracoes/senha'],
        ['POST', '/sair'],
    ]);

    it('abre as páginas para quem entrou', function () {
        $this->actingAs(definirSenha())->get('/')->assertOk()->assertSee('Início');
    });

    it('deixa o healthcheck público', function () {
        $this->get('/up')->assertOk();
    });

    it('volta para a página pedida depois de entrar', function () {
        definirSenha();
        $this->get('/configuracoes/senha');

        $this->post('/entrar', ['senha' => 'segredo123'])->assertRedirect('/configuracoes/senha');
    });
});

describe('CA-18.2 senha única, definida na instalação e trocável', function () {
    it('pede só a senha, sem nome de usuário', function () {
        definirSenha();

        $this->get('/entrar')->assertOk()
            ->assertSee('name="senha"', escape: false)
            ->assertDontSee('name="email"', escape: false)
            ->assertDontSee(Dono::EMAIL);
    });

    it('entra com a senha certa e recusa a errada', function () {
        definirSenha();

        $this->post('/entrar', ['senha' => 'errada'])->assertSessionHasErrors(['senha' => 'Senha incorreta.']);
        $this->assertGuest();

        $this->post('/entrar', ['senha' => 'segredo123'])->assertRedirect(route('inicio'));
        $this->assertAuthenticated();
    });

    it('avisa como definir a senha quando ela ainda não existe', function () {
        $this->get('/entrar')->assertOk()->assertSee('financas:definir-senha');

        $this->post('/entrar', ['senha' => 'qualquer'])->assertSessionHasErrors('senha');
        $this->assertGuest();
    });

    it('define a senha pelo comando de instalação', function () {
        $this->artisan('financas:definir-senha')
            ->expectsQuestion('Nova senha (mínimo 6 caracteres)', 'segredo123')
            ->expectsQuestion('Repita a senha', 'segredo123')
            ->assertSuccessful();

        $this->post('/entrar', ['senha' => 'segredo123']);
        $this->assertAuthenticated();
    });

    it('redefine a senha pelo comando sem criar outro usuário', function () {
        definirSenha('antiga123');

        $this->artisan('financas:definir-senha')
            ->expectsQuestion('Nova senha (mínimo 6 caracteres)', 'nova12345')
            ->expectsQuestion('Repita a senha', 'nova12345')
            ->assertSuccessful();

        expect(User::query()->count())->toBe(1)
            ->and(Hash::check('nova12345', Dono::usuario()->password))->toBeTrue();
    });

    it('recusa no comando senha curta ou repetição diferente', function (string $senha, string $repeticao) {
        $comando = $this->artisan('financas:definir-senha')
            ->expectsQuestion('Nova senha (mínimo 6 caracteres)', $senha);

        if (mb_strlen($senha) >= Dono::SENHA_MINIMA) {
            $comando->expectsQuestion('Repita a senha', $repeticao);
        }

        $comando->assertFailed();
        expect(User::query()->count())->toBe(0);
    })->with([
        'curta' => ['12345', '12345'],
        'diferente' => ['segredo123', 'segredo124'],
    ]);

    it('troca a senha informando a atual', function () {
        $this->actingAs(definirSenha());

        $this->put('/configuracoes/senha', [
            'senha_atual' => 'segredo123',
            'nova_senha' => 'outra456',
            'nova_senha_confirmation' => 'outra456',
        ])->assertRedirect(route('configuracoes.senha'))->assertSessionHas('status', 'Senha alterada.');

        expect(Hash::check('outra456', Dono::usuario()->password))->toBeTrue();
    });

    it('recusa a troca com dados inválidos', function (array $dados, string $campo, string $mensagem) {
        $this->actingAs(definirSenha());

        $this->put('/configuracoes/senha', $dados)->assertSessionHasErrors([$campo => $mensagem]);

        expect(Hash::check('segredo123', Dono::usuario()->password))->toBeTrue();
    })->with([
        'senha atual errada' => [['senha_atual' => 'errada', 'nova_senha' => 'outra456', 'nova_senha_confirmation' => 'outra456'], 'senha_atual', 'A senha atual está incorreta.'],
        'nova senha curta' => [['senha_atual' => 'segredo123', 'nova_senha' => '123', 'nova_senha_confirmation' => '123'], 'nova_senha', 'A nova senha precisa ter pelo menos 6 caracteres.'],
        'confirmação diferente' => [['senha_atual' => 'segredo123', 'nova_senha' => 'outra456', 'nova_senha_confirmation' => 'outra789'], 'nova_senha', 'A confirmação não confere com a nova senha.'],
    ]);
});

it('CA-18.3 guarda a senha só como hash', function () {
    $this->artisan('financas:definir-senha')
        ->expectsQuestion('Nova senha (mínimo 6 caracteres)', 'segredo123')
        ->expectsQuestion('Repita a senha', 'segredo123');

    $gravada = User::query()->value('password');

    expect($gravada)->not->toBe('segredo123')
        ->and($gravada)->not->toContain('segredo123')
        ->and(Hash::check('segredo123', $gravada))->toBeTrue();
});

describe('CA-18.4 lembrar neste aparelho', function () {
    it('mantém a sessão por 30 dias quando marcado', function () {
        definirSenha();

        $cookie = cookieLembrar($this->post('/entrar', ['senha' => 'segredo123', 'lembrar' => '1']));

        expect($cookie)->not->toBeNull()
            ->and($cookie->getExpiresTime())->toBeGreaterThan(now()->addDays(29)->getTimestamp())
            ->and($cookie->getExpiresTime())->toBeLessThanOrEqual(now()->addDays(30)->getTimestamp() + 5);
    });

    it('não lembra o aparelho quando não marcado e encerra a sessão ao fechar o navegador', function () {
        definirSenha();

        $resposta = $this->post('/entrar', ['senha' => 'segredo123']);

        expect(cookieLembrar($resposta))->toBeNull()
            ->and(config('session.expire_on_close'))->toBeTrue();
    });
});

describe('CA-18.5 limite de tentativas', function () {
    it('bloqueia o aparelho por 1 minuto depois de 5 senhas erradas', function () {
        definirSenha();

        foreach (range(1, 5) as $tentativa) {
            $this->post('/entrar', ['senha' => "errada{$tentativa}"])->assertSessionHasErrors(['senha' => 'Senha incorreta.']);
        }

        // Nem a senha certa entra durante o bloqueio.
        $this->post('/entrar', ['senha' => 'segredo123'])->assertSessionHasErrors('senha');
        expect(session('errors')->first('senha'))->toStartWith('Muitas tentativas.');
        $this->assertGuest();

        $this->travel(61)->seconds();

        $this->post('/entrar', ['senha' => 'segredo123'])->assertRedirect(route('inicio'));
        $this->assertAuthenticated();
    });

    it('não bloqueia outro aparelho', function () {
        definirSenha();

        foreach (range(1, 5) as $tentativa) {
            $this->post('/entrar', ['senha' => 'errada']);
        }

        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
            ->post('/entrar', ['senha' => 'segredo123'])
            ->assertRedirect(route('inicio'));
    });

    it('zera a contagem depois de entrar', function () {
        definirSenha();

        foreach (range(1, 4) as $tentativa) {
            $this->post('/entrar', ['senha' => 'errada']);
        }
        $this->post('/entrar', ['senha' => 'segredo123']);
        $this->post('/sair');

        foreach (range(1, 4) as $tentativa) {
            $this->post('/entrar', ['senha' => 'errada'])->assertSessionHasErrors(['senha' => 'Senha incorreta.']);
        }
    });
});

it('CA-18.6 sai do sistema', function () {
    $this->actingAs(definirSenha());

    $this->get('/mais')->assertSee('Sair');
    $this->post('/sair')->assertRedirect(route('login'));

    $this->assertGuest();
    $this->get('/')->assertRedirect(route('login'));
});
