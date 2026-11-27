<?php

namespace App\Models;

use App\Infra\Casts\CompetenciaCast;
use Illuminate\Database\Eloquent\Model;

class RecorrenciaOcorrencia extends Model
{
    protected $table = 'recorrencia_ocorrencias';

    protected $fillable = ['recorrencia_id', 'competencia', 'valor_centavos'];

    protected function casts(): array
    {
        return [
            'competencia' => CompetenciaCast::class,
            'valor_centavos' => 'integer',
        ];
    }
}
