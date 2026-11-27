<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RF-04 / design §3.2: recorrências são virtuais; só vigências e exceções são gravadas.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recorrencias', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->char('inicio', 7);
            $table->char('fim', 7)->nullable();
            $table->timestamps();

            $table->index(['inicio', 'fim']);
        });

        Schema::create('recorrencia_valores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorrencia_id')->constrained('recorrencias')->cascadeOnDelete();
            $table->char('vigente_desde', 7);
            $table->unsignedBigInteger('valor_centavos');
            $table->timestamps();

            $table->unique(['recorrencia_id', 'vigente_desde']);
        });

        Schema::create('recorrencia_ocorrencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recorrencia_id')->constrained('recorrencias')->cascadeOnDelete();
            $table->char('competencia', 7);
            $table->unsignedBigInteger('valor_centavos')->nullable()->comment('nulo = usa o valor vigente');
            $table->timestamps();

            $table->unique(['recorrencia_id', 'competencia']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recorrencia_ocorrencias');
        Schema::dropIfExists('recorrencia_valores');
        Schema::dropIfExists('recorrencias');
    }
};
