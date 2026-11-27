<?php

namespace App\Models;

use App\Infra\Casts\CompetenciaCast;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Parcelamento extends Model
{
    protected $table = 'parcelamentos';

    protected $fillable = ['descricao', 'valor_parcela_centavos', 'total_parcelas', 'primeira_competencia', 'cancelado_em'];

    protected function casts(): array
    {
        return [
            'primeira_competencia' => CompetenciaCast::class,
            'valor_parcela_centavos' => 'integer',
            'total_parcelas' => 'integer',
            'cancelado_em' => 'immutable_datetime',
        ];
    }

    public function parcelas(): HasMany
    {
        return $this->hasMany(Lancamento::class)->orderBy('parcela_numero');
    }
}
