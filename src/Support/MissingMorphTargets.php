<?php

namespace XLaravel\Listmonk\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * @internal
 */
final class MissingMorphTargets
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public static function where(Builder $query, string $morph): Builder
    {
        $model = $query->getModel();
        $typeColumn = $model->qualifyColumn("{$morph}_type");
        $idColumn = $model->qualifyColumn("{$morph}_id");
        $types = $model->newQueryWithoutScopes()->distinct()->pluck("{$morph}_type");

        return $query->where(function (Builder $query) use ($model, $types, $typeColumn, $idColumn) {
            $query->whereRaw('1 = 0');

            foreach ($types as $type) {
                $related = is_string($type) ? self::related($type, $model) : null;

                if ($related === null) {
                    continue;
                }

                $query->orWhere(fn (Builder $query) => $query
                    ->where($typeColumn, $type)
                    ->whereNotExists(fn (QueryBuilder $exists) => $exists
                        ->from($related->getTable())
                        ->whereColumn($related->getQualifiedKeyName(), $idColumn)));
            }
        });
    }

    private static function related(string $type, Model $model): ?Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;

        if (! is_subclass_of($class, Model::class)) {
            return null;
        }

        $related = new $class();

        return $related->getConnection()->getName() === $model->getConnection()->getName() ? $related : null;
    }
}
