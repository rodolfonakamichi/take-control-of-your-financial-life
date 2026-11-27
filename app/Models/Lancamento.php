<?php

namespace App\Models;

use App\Infra\Casts\CompetenciaCast;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lancamento extends Model
{
    public const DESPESA = 'despesa';

    public const RECEITA = 'receita';

    public const AVULSO = 'avulso';

    public const PARCELAMENTO = 'parcelamento';

    protected $table = 'lancamentos';

    protected $fillable = [
        'tipo', 'descricao', 'valor_centavos', 'competencia', 'categoria_receita',
        'origem', 'parcelamento_id', 'parcela_numero', 'pago_em', 'chave_importacao',
    ];

    protected function casts(): array
    {
        return [
            'competencia' => CompetenciaCast::class,
            'valor_centavos' => 'integer',
            'parcela_numero' => 'integer',
            'pago_em' => 'immutable_datetime',
        ];
    }

    public function parcelamento(): BelongsTo
    {
        return $this->belongsTo(Parcelamento::class);
    }

    public function scopeDoMes(Builder $query, string $competencia): void
    {
        $query->where('competencia', $competencia);
    }
}
