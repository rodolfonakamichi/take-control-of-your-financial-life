<?php

namespace App\Models;

use App\Infra\Casts\CompetenciaCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Recorrencia extends Model
{
    protected $table = 'recorrencias';

    protected $fillable = ['descricao', 'inicio', 'fim'];

    protected function casts(): array
    {
        return [
            'inicio' => CompetenciaCast::class,
            'fim' => CompetenciaCast::class,
        ];
    }

    public function valores(): HasMany
    {
        return $this->hasMany(RecorrenciaValor::class)->orderBy('vigente_desde');
    }

    public function ocorrencias(): HasMany
    {
        return $this->hasMany(RecorrenciaOcorrencia::class);
    }
}
