<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RF-06.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('movimentos_guardado', function (Blueprint $table) {
            $table->id();
            $table->char('competencia', 7)->index();
            $table->string('tipo', 10)->comment('guardar | retirar');
            $table->unsignedBigInteger('valor_centavos');
            $table->string('descricao')->nullable();
            $table->string('chave_importacao')->nullable()->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimentos_guardado');
    }
};
