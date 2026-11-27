<?php

namespace App\Infra\Casts;

use App\Domain\Competencia;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Coluna CHAR(7) "AAAA-MM" <-> Competencia.
 *
 * @implements CastsAttributes<Competencia|null, Competencia|string|null>
 */
final class CompetenciaCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Competencia
    {
        return $value === null ? null : Competencia::deTexto($value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) ($value instanceof Competencia ? $value : Competencia::deTexto($value));
    }
}
