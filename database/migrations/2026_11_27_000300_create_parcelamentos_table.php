<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RF-05: as parcelas ficam em `lancamentos` (origem = parcelamento).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('parcelamentos', function (Blueprint $table) {
            $table->id();
            $table->string('descricao');
            $table->unsignedBigInteger('valor_parcela_centavos');
            $table->unsignedSmallInteger('total_parcelas');
            $table->char('primeira_competencia', 7);
            $table->timestamp('cancelado_em')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('parcelamentos');
    }
};
