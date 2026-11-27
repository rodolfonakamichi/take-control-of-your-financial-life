<?php

use App\Http\Controllers\Auth\SessaoController;
use App\Http\Controllers\Configuracoes\SenhaController;
use Illuminate\Support\Facades\Route;

// RF-18: tudo exige a senha, exceto a própria tela de entrar (e o /up do healthcheck).
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [SessaoController::class, 'create'])->name('login');
    Route::post('/entrar', [SessaoController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'inicio')->name('inicio');

    Route::post('/sair', [SessaoController::class, 'destroy'])->name('logout');

    Route::get('/configuracoes/senha', [SenhaController::class, 'edit'])->name('configuracoes.senha');
    Route::put('/configuracoes/senha', [SenhaController::class, 'update'])->name('configuracoes.senha.update');
});
