<?php

namespace Silverd\LaravelSortable;

use ArrayAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;

trait SortableTrait
{
    public static function bootSortableTrait()
    {
        static::creating(function ($model) {
            if ($model instanceof Sortable) {
                $newSort = $model->shouldSortWhenCreating();
                if ($newSort === 'end') {
                    $model->setMinOrderNumber();
                }
                elseif ($newSort === 'start') {
                    $model->setMaxOrderNumber();
                }
            }
        });
    }

    protected function buildSortQuery()
    {
        return static::query();
    }

    public function setMaxOrderNumber()
    {
        $sortColumnName = $this->determineSortColumnName();

        $this->$sortColumnName = $this->getMaxOrderNumber() + 1;
    }

    public function getMaxOrderNumber(): int
    {
        return (int) $this->buildSortQuery()->max($this->determineSortColumnName());
    }

    public function setMinOrderNumber()
    {
        $sortColumnName = $this->determineSortColumnName();

        $this->$sortColumnName = $this->getMinOrderNumber() - 1;
    }

    public function getMinOrderNumber(): int
    {
        return (int) $this->buildSortQuery()->min($this->determineSortColumnName());
    }

    public function scopeOrdered(Builder $query, string $direction = 'asc')
    {
        return $query->orderBy($this->determineSortColumnName(), $direction);
    }

    public static function setNewOrder($ids, int $startOrder = 1, string $primaryKeyColumn = null)
    {
        if (! is_array($ids) && ! $ids instanceof ArrayAccess) {
            throw new InvalidArgumentException('You must pass an array or ArrayAccess object to setNewOrder');
        }

        $model = new static;

        $sortColumnName = $model->determineSortColumnName();

        if (is_null($primaryKeyColumn)) {
            $primaryKeyColumn = $model->getKeyName();
        }

        foreach ($ids as $id) {
            static::withoutGlobalScope(SoftDeletingScope::class)
                ->where($primaryKeyColumn, $id)
                ->update([$sortColumnName => $startOrder++]);
        }
    }

    public static function setNewOrderByCustomColumn(string $primaryKeyColumn, $ids, int $startOrder = 1)
    {
        self::setNewOrder($ids, $startOrder, $primaryKeyColumn);
    }

    public function determineSortColumnName(): string
    {
        return $this->sortable['sort_column_name'] ?? config('laravel-sortable.sort_column_name', 'weight');
    }

    /**
     * Determine if the order column should be set when saving a new model instance.
     */
    public function shouldSortWhenCreating(): bool
    {
        return $this->sortable['sort_when_creating'] ?? config('laravel-sortable.sort_when_creating', 'end');
    }

    public function moveOrderDown()
    {
        $sortColumnName = $this->determineSortColumnName();

        $swapWithModel = $this->buildSortQuery()
            ->where($sortColumnName, '<', $this->$sortColumnName)
            ->ordered('desc')
            ->limit(1)
            ->first();

        if (! $swapWithModel) {
            return $this;
        }

        return $this->swapOrderWithModel($swapWithModel);
    }

    public function moveOrderUp()
    {
        $sortColumnName = $this->determineSortColumnName();

        $swapWithModel = $this->buildSortQuery()
            ->where($sortColumnName, '>', $this->$sortColumnName)
            ->ordered('asc')
            ->limit(1)
            ->first();

        if (! $swapWithModel) {
            return $this;
        }

        return $this->swapOrderWithModel($swapWithModel);
    }

    public function swapOrderWithModel(Sortable $otherModel)
    {
        $sortColumnName = $this->determineSortColumnName();

        $oldOrderOfOtherModel = $otherModel->$sortColumnName;

        $otherModel->$sortColumnName = $this->$sortColumnName;
        $otherModel->save();

        $this->$sortColumnName = $oldOrderOfOtherModel;
        $this->save();

        return $this;
    }

    public static function swapOrder(Sortable $model, Sortable $otherModel)
    {
        $model->swapOrderWithModel($otherModel);
    }

    public function moveToStart()
    {
        $firstModel = $this->buildSortQuery()
            ->ordered('desc')
            ->first();

        // 已是最前
        if ($firstModel->getKey() === $this->getKey()) {
            return $this;
        }

        $sortColumnName = $this->determineSortColumnName();

        $this->$sortColumnName = $firstModel->$sortColumnName;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->decrement($sortColumnName);

        return $this;
    }

    public function moveToEnd()
    {
        $minOrder = $this->getMinOrderNumber();

        $sortColumnName = $this->determineSortColumnName();

        // 已是最尾
        if ($this->$sortColumnName === $minOrder) {
            return $this;
        }

        $this->$sortColumnName = $minOrder;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->increment($sortColumnName);

        return $this;
    }

    public function insertBefore(Sortable $reference)
    {
        if ($reference->getKey() === $this->getKey()) {
            return $this;
        }

        $maxOrder = $this->getMaxOrderNumber();

        $sortColumnName = $this->determineSortColumnName();

        // 已是最顶
        if ($this->$sortColumnName === $maxOrder) {
            return $this;
        }

        $newOrder = $reference->$sortColumnName;

        $this->$sortColumnName = $newOrder;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->where($sortColumnName, '<=', $newOrder)
            ->decrement($sortColumnName);

        return $this;
    }

    public function insertAfter(Sortable $reference)
    {
        if ($reference->getKey() === $this->getKey()) {
            return $this;
        }

        $minOrder = $this->getMinOrderNumber();

        $sortColumnName = $this->determineSortColumnName();

        // 已是最底
        if ($this->$sortColumnName === $minOrder) {
            return $this;
        }

        $newOrder = $reference->$sortColumnName;

        $this->$sortColumnName = $newOrder;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->where($sortColumnName, '>=', $newOrder)
            ->increment($sortColumnName);

        return $this;
    }
}
