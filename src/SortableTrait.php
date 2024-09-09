<?php

namespace Silverd\LaravelSortable;

use ArrayAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;

trait SortableTrait
{
    // 不可上、不可下
    const UP_0_DOWN_0 = 0;

    // 不可上、可下
    const UP_0_DOWN_1 = 1;

    // 可上、不可下
    const UP_1_DOWN_0 = 2;

    // 可上、可下
    const UP_1_DOWN_1 = 3;

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

        static::created(function ($model) {
            if ($model->determineCanSortsColumnName()) {
                $model->resetCanSortsFlags();
            }
        });

        static::deleted(function ($model) {
            if ($model->determineCanSortsColumnName()) {
                $model->resetCanSortsFlags();
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

    public function getMaxOrderNumber()
    {
        return (int) $this->buildSortQuery()->max($this->determineSortColumnName());
    }

    public function setMinOrderNumber()
    {
        $sortColumnName = $this->determineSortColumnName();

        $this->$sortColumnName = $this->getMinOrderNumber() - 1;
    }

    public function getMinOrderNumber()
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

    public function determineSortColumnName()
    {
        return $this->sortable['sort_column_name'] ?? config('laravel-sortable.sort_column_name', 'weight');
    }

    public function determineCanSortsColumnName()
    {
        return $this->sortable['can_sorts_column_name'] ?? config('laravel-sortable.can_sorts_column_name');
    }

    /**
     * Determine if the order column should be set when saving a new model instance.
     */
    public function shouldSortWhenCreating()
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

        $canSortsColumnName = $this->determineCanSortsColumnName();
        $oldCanSortsOfOtherModel = $otherModel->$canSortsColumnName;

        $otherModel->$sortColumnName = $this->$sortColumnName;

        if ($canSortsColumnName) {
            $otherModel->$canSortsColumnName = $this->$canSortsColumnName;
        }

        $otherModel->save();

        $this->$sortColumnName = $oldOrderOfOtherModel;

        if ($canSortsColumnName) {
            $this->$canSortsColumnName = $oldCanSortsOfOtherModel;
        }

        $this->save();

        return $this;
    }

    public static function swapOrder(Sortable $model, Sortable $otherModel)
    {
        $model->swapOrderWithModel($otherModel);
    }

    public function moveToStart()
    {
        return $this->insertBefore($this->getTheFirstModel());
    }

    public function moveToEnd()
    {
        return $this->insertAfter($this->getTheLastModel());
    }

    public function insertBefore(Sortable $reference)
    {
        if ($reference->getKey() === $this->getKey()) {
            return $this;
        }

        $sortColumnName = $this->determineSortColumnName();

        $newOrder = $reference->$sortColumnName;

        $this->$sortColumnName = $newOrder;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->where($sortColumnName, '<=', $newOrder)
            ->decrement($sortColumnName);

        $this->resetCanSortsFlags();

        return $this;
    }

    public function insertAfter(Sortable $reference)
    {
        if ($reference->getKey() === $this->getKey()) {
            return $this;
        }

        $sortColumnName = $this->determineSortColumnName();

        $newOrder = $reference->$sortColumnName;

        $this->$sortColumnName = $newOrder;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->where($sortColumnName, '>=', $newOrder)
            ->increment($sortColumnName);

        $this->resetCanSortsFlags();

        return $this;
    }

    public function getTheFirstModel()
    {
        return $this->buildSortQuery()->ordered('desc')->first();
    }

    public function getTheLastModel()
    {
        return $this->buildSortQuery()->ordered('asc')->first();
    }

    public function resetCanSortsFlags()
    {
        if (! $canSortsColumnName = $this->determineCanSortsColumnName()) {
            return $this;
        }

        $firstModel = $this->getTheFirstModel();
        $lastModel  = $this->getTheLastModel();

        if ($firstModel->getKey() == $lastModel->getKey()) {
            $firstModel->update([
                $canSortsColumnName => self::UP_0_DOWN_0,
            ]);
        }
        else {
            $firstModel->update([
                $canSortsColumnName => self::UP_0_DOWN_1,
            ]);
            $lastModel->update([
                $canSortsColumnName => self::UP_1_DOWN_0,
            ]);
        }

        $this->buildSortQuery()
            ->whereNotIn($this->getKeyName(), [
                $firstModel->getKey(),
                $lastModel->getKey(),
            ])
            ->update([
                $canSortsColumnName => self::UP_1_DOWN_1,
            ]);
    }
}
