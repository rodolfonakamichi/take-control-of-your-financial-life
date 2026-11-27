<?php

use App\Http\Controllers\Auth\SessaoController;
use App\Http\Controllers\Configuracoes\SenhaController;
use App\Http\Controllers\Navegacao\AnoController;
use App\Http\Controllers\Navegacao\MesController;
use Illuminate\Support\Facades\Route;

// RF-18: tudo exige a senha, exceto a própria tela de entrar (e o /up do healthcheck).
Route::middleware('guest')->group(function () {
    Route::get('/entrar', [SessaoController::class, 'create'])->name('login');
    Route::post('/entrar', [SessaoController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::view('/', 'inicio')->name('inicio');

    Route::get('/mes/{competencia?}', [MesController::class, 'show'])
        ->where('competencia', '\d{4}-\d{2}')
        ->name('mes');

    Route::get('/ano/{ano?}', [AnoController::class, 'show'])
        ->where('ano', '\d{4}')
        ->name('ano');

    Route::view('/mais', 'mais')->name('mais');

    Route::view('/recorrencias', 'recorrencias.index')->name('recorrencias');

    Route::post('/sair', [SessaoController::class, 'destroy'])->name('logout');

    Route::get('/configuracoes/senha', [SenhaController::class, 'edit'])->name('configuracoes.senha');
    Route::put('/configuracoes/senha', [SenhaController::class, 'update'])->name('configuracoes.senha.update');
});
