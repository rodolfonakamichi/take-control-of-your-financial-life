<?php

namespace App\Models;

use App\Infra\Casts\CompetenciaCast;
use Illuminate\Database\Eloquent\Model;

class RecorrenciaValor extends Model
{
    protected $table = 'recorrencia_valores';

    protected $fillable = ['recorrencia_id', 'vigente_desde', 'valor_centavos'];

    protected function casts(): array
    {
        return [
            'vigente_desde' => CompetenciaCast::class,
            'valor_centavos' => 'integer',
        ];
    }
}
