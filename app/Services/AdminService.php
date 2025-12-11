<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use ReflectionMethod;
use Slowlyo\OwlAdmin\Admin;
use Slowlyo\OwlAdmin\Models\AdminUser;
use Slowlyo\OwlAdmin\Services\AdminService as OwlAdminService;

abstract class AdminService extends OwlAdminService
{
    protected ?AdminUser $adminUser = null;

    public function __construct()
    {
        parent::__construct();

        $this->adminUser = Admin::user() ?: AdminUser::query()->first();
    }

    public function adminUser(): ?AdminUser
    {
        return $this->adminUser;
    }

    public function sortColumn(): string
    {

        if ($this->hasColumn($this->getModel()->getKeyName())) {
            return $this->getModel()->getKeyName();
        }

        return Arr::first($this->getTableColumns());
    }

    /**
     * 修改
     *
     *
     * @return bool
     */
    public function update($primaryKey, $data)
    {
        DB::beginTransaction();
        try {
            $this->saving($data, $primaryKey);

            $model = $this->query()->whereKey($primaryKey)->first();
            if (! $model) {
                admin_abort('数据不存在');
            }

            [$relations, $relationKeyMap] = $this->getRelationInputs($model, $data);

            foreach ($data as $k => $v) {
                if (array_key_exists($k, $relationKeyMap) || ! $this->hasColumn($k)) {
                    continue;
                }

                $model->setAttribute($k, $v);
            }

            $result = $model->save();

            if ($result !== false && $relations) {
                $this->syncRelations($model, $relations, $relationKeyMap);
            }

            // 无论数据是否变更,都赋值当前模型实例
            $this->currentModel = $model;

            if ($result) {
                $this->saved($model, true);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            admin_abort($e->getMessage());
        }

        return $result;
    }

    /**
     * 新增
     *
     *
     * @return bool
     */
    public function store($data)
    {
        DB::beginTransaction();
        try {
            $this->saving($data);

            $model = $this->getModel();
            [$relations, $relationKeyMap] = $this->getRelationInputs($model, $data);

            foreach ($data as $k => $v) {
                if (array_key_exists($k, $relationKeyMap) || ! $this->hasColumn($k)) {
                    continue;
                }

                $model->setAttribute($k, $v);
            }

            $result = $model->save();

            if ($result !== false && $relations) {
                $this->syncRelations($model, $relations, $relationKeyMap);
            }

            // 无论是否保存成功,都赋值当前模型实例
            $this->currentModel = $model;

            if ($result) {
                $this->saved($model);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            admin_abort($e->getMessage());
        }

        return $result;
    }

    /**
     * 删除
     *
     *
     * @return int
     */
    public function delete(string $ids)
    {
        $idCollection = collect(explode(',', $ids))
            ->map(fn ($id) => trim((string) $id))
            ->filter(fn ($id) => $id !== '')
            ->values();

        if ($idCollection->isEmpty()) {
            return 0;
        }

        DB::beginTransaction();
        try {
            $query = $this->query();

            $this->addRelations($query, 'delete');

            if ($relations = $this->relationsForDelete()) {
                $query->with($relations);
            }

            $models = $query->whereIn($this->primaryKey(), $idCollection)->get();

            if ($relations = $this->relationsForDelete()) {
                $models->load($relations);
            }

            $deletedCount = 0;

            foreach ($models as $model) {
                $this->deleteRelations($model);
                $model->delete();
                $deletedCount++;
            }

            if ($deletedCount) {
                $this->deleted($ids);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            admin_abort($e->getMessage());
        }

        return $deletedCount;
    }

    protected function getRelationInputs(Model $model, array $inputs): array
    {
        $map = [];
        $relations = [];

        foreach ($inputs as $column => $value) {
            $relationName = $this->resolveRelationName($model, $column);
            if (! $relationName) {
                continue;
            }


            $relation = $model->$relationName();

            if (! $relation instanceof Relation) {
                continue;
            }

            $relations[$column] = $value;
            $map[$column] = $relationName;
        }

        return [$relations, $map];
    }

    protected function resolveRelationName(Model $model, string $column): ?string
    {
        $candidates = [$column];
        $camel = Str::camel($column);

        if ($camel !== $column) {
            $candidates[] = $camel;
        }

        foreach ($candidates as $candidate) {
            if (! method_exists($model, $candidate)) {
                continue;
            }

            $reflection = new ReflectionMethod($model, $candidate);

            if (! $reflection->isPublic() || $reflection->getNumberOfParameters() > 0) {
                continue;
            }

            return $candidate;
        }

        return null;
    }

    protected function syncRelations(Model $model, array $relations, array $relationKeyMap): void
    {
        foreach ($relations as $name => $values) {
            $relationName = $relationKeyMap[$name] ?? $name;

            if (! method_exists($model, $relationName)) {
                continue;
            }

            $relation = $model->$relationName();

            if (! $relation instanceof Relation) {
                continue;
            }

            switch (true) {
                case $relation instanceof BelongsToMany:
                case $relation instanceof MorphToMany:
                    $this->syncBelongsToManyRelation($relation, $values);
                    break;
                case $relation instanceof HasMany:
                case $relation instanceof MorphMany:
                    $this->syncHasManyRelation($relation, is_array($values) ? $values : []);
                    break;
                case $relation instanceof HasOne:
                case $relation instanceof MorphOne:
                    $this->syncHasOneRelation($relation, is_array($values) ? $values : []);
                    break;
                case $relation instanceof BelongsTo:
                    $this->syncBelongsToRelation($model, $relation, $values);
                    break;
                case $relation instanceof MorphTo:
                    $this->syncMorphToRelation($model, $relation, $values);
                    break;
            }
        }
    }

    /**
     * @param  BelongsToMany|MorphToMany  $relation
     * @param  mixed  $values
     */
    protected function syncBelongsToManyRelation($relation, $values): void
    {
        if ($values === null) {
            $relation->sync([]);

            return;
        }

        if (! is_array($values)) {
            $values = Arr::wrap($values);
        }

        $relation->sync($values);
    }

    /**
     * @param  HasOne|MorphOne  $relation
     */
    protected function syncHasOneRelation($relation, array $values): void
    {
        if ($values === []) {
            return;
        }

        $related = $relation->first();

        if (! $related) {
            $related = $relation->make();
        }

        $related->fill($values);

        if ($related->exists) {
            $related->save();
        } else {
            $relation->save($related);
        }
    }

    /**
     * @param  HasMany|MorphMany  $relation
     */
    protected function syncHasManyRelation($relation, array $values): void
    {
        $values = array_values(array_filter($values, static fn ($item) => is_array($item)));
        $keyName = $relation->getRelated()->getKeyName();

        if ($values === []) {
            $relation->delete();

            return;
        }

        $existingIds = $relation->pluck($keyName)->all();
        $retainedIds = [];

        foreach ($values as $attributes) {
            $relatedId = Arr::get($attributes, $keyName);

            $instance = $relation->findOrNew($relatedId);
            $instance->fill(Arr::except($attributes, [$keyName]));

            if ($instance->exists) {
                $instance->save();
            } else {
                $relation->save($instance);
            }

            $retainedIds[] = $instance->getKey();
        }

        $retainedIds = array_values(array_filter(array_unique($retainedIds)));
        $deleteIds = array_diff($existingIds, $retainedIds);

        if ($deleteIds) {
            $relation->whereIn($keyName, $deleteIds)->delete();
        }
    }

    /**
     * @param  mixed  $values
     */
    protected function syncBelongsToRelation(Model $model, BelongsTo $relation, $values): void
    {
        if ($values instanceof Model) {
            $relation->associate($values);
            $model->save();

            return;
        }

        if ($values === null || $values === '') {
            $relation->dissociate();
            $model->save();

            return;
        }

        if (! is_array($values)) {
            $relation->associate($values);
            $model->save();

            return;
        }

        $relatedKeyName = $relation->getRelated()->getKeyName();
        $foreignKeyName = method_exists($relation, 'getForeignKeyName')
            ? $relation->getForeignKeyName()
            : $relation->getForeignKey();

        $targetId = $values[$relatedKeyName] ?? $values[$foreignKeyName] ?? null;

        $parent = null;

        if ($targetId) {
            $parent = $relation->getRelated()->newQuery()->find($targetId);
        }

        if (! $parent) {
            $parent = $relation->getResults() ?: $relation->getRelated()->newInstance();
        }

        $parent->fill(Arr::except($values, [$relatedKeyName, $foreignKeyName]));
        $parent->save();

        $relation->associate($parent);
        $model->save();
    }

    /**
     * @param  mixed  $values
     */
    protected function syncMorphToRelation(Model $model, MorphTo $relation, $values): void
    {
        if ($values instanceof Model) {
            $relation->associate($values);
            $model->save();

            return;
        }

        if (! is_array($values)) {
            return;
        }

        $typeColumn = $relation->getMorphType();
        $idColumn = $relation->getForeignKeyName();

        $class = Arr::pull($values, $typeColumn) ?? Arr::pull($values, 'type');
        $id = Arr::pull($values, $idColumn) ?? Arr::pull($values, 'id');

        if (! $class || ! class_exists($class)) {
            return;
        }

        $related = $relation->getResults();

        if ($id) {
            $existing = $class::query()->find($id);
            if ($existing) {
                $related = $existing;
            }
        }

        if (! $related || ! $related instanceof $class) {
            $related = new $class;
        }

        $related->fill(Arr::except($values, ['id', $typeColumn, $idColumn]));
        $related->save();

        $relation->associate($related);
        $model->save();
    }

    /**
     * 可用于指定删除时需要预加载并处理的关联
     */
    protected function relationsForDelete(): array
    {
        return [];
    }

    protected function deleteRelations(Model $model): void
    {
        foreach ($model->getRelations() as $relationName => $value) {
            if (! method_exists($model, $relationName)) {
                continue;
            }

            $relation = $model->$relationName();

            if (! $relation instanceof Relation) {
                continue;
            }

            switch (true) {
                case $relation instanceof BelongsToMany:
                case $relation instanceof MorphToMany:
                    $relation->detach();
                    break;
                case $relation instanceof HasMany:
                case $relation instanceof MorphMany:
                    $relation->delete();
                    break;
                case $relation instanceof HasOne:
                case $relation instanceof MorphOne:
                    if ($related = $relation->first()) {
                        $related->delete();
                    }
                    break;
            }
        }
    }
}
