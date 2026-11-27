<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// RF-01, RF-02, RF-05, RF-09. Totais nunca são gravados (RV-02).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lancamentos', function (Blueprint $table) {
            $table->id();
            $table->string('tipo', 10)->comment('despesa | receita');
            $table->string('descricao');
            $table->unsignedBigInteger('valor_centavos');
            $table->char('competencia', 7)->index();
            $table->string('categoria_receita', 10)->nullable()->comment('salario | outros | comissao');
            $table->string('origem', 15)->default('avulso')->comment('avulso | parcelamento');
            $table->foreignId('parcelamento_id')->nullable()->constrained('parcelamentos')->cascadeOnDelete();
            $table->unsignedSmallInteger('parcela_numero')->nullable();
            $table->timestamp('pago_em')->nullable()->comment('RD-16 já usa na v1.0; a tela de pago/a pagar é a v1.1');
            $table->string('chave_importacao')->nullable()->unique()->comment('aba!célula da planilha (CA-09.7)');
            $table->timestamps();

            $table->unique(['parcelamento_id', 'parcela_numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lancamentos');
    }
};
