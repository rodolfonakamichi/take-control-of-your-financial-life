<?php

namespace App\Models;

use App\Infra\Casts\CompetenciaCast;
use Illuminate\Database\Eloquent\Model;

class MovimentoGuardado extends Model
{
    protected $table = 'movimentos_guardado';

    protected $fillable = ['competencia', 'tipo', 'valor_centavos', 'descricao', 'chave_importacao'];

    protected function casts(): array
    {
        return [
            'competencia' => CompetenciaCast::class,
            'valor_centavos' => 'integer',
        ];
    }
}
