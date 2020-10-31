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
            if ($model instanceof Sortable && $model->shouldSortWhenCreating()) {
                $model->setMaxOrderNumber();
            }
        });
    }

    public function setMaxOrderNumber()
    {
        $orderColumnName = $this->determineOrderColumnName();

        $this->$orderColumnName = $this->getMaxOrderNumber() + 1;
    }

    public function getMaxOrderNumber(): int
    {
        return (int) $this->buildSortQuery()->max($this->determineOrderColumnName());
    }

    public function setMinOrderNumber()
    {
        $orderColumnName = $this->determineOrderColumnName();

        $this->$orderColumnName = $this->getMinOrderNumber() - 1;
    }

    public function getMinOrderNumber(): int
    {
        return (int) $this->buildSortQuery()->min($this->determineOrderColumnName());
    }

    public function scopeOrdered(Builder $query, string $direction = 'asc')
    {
        return $query->orderBy($this->determineOrderColumnName(), $direction);
    }

    public static function setNewOrder($ids, int $startOrder = 1, string $primaryKeyColumn = null)
    {
        if (! is_array($ids) && ! $ids instanceof ArrayAccess) {
            throw new InvalidArgumentException('You must pass an array or ArrayAccess object to setNewOrder');
        }

        $model = new static;

        $orderColumnName = $model->determineOrderColumnName();

        if (is_null($primaryKeyColumn)) {
            $primaryKeyColumn = $model->getKeyName();
        }

        foreach ($ids as $id) {
            static::withoutGlobalScope(SoftDeletingScope::class)
                ->where($primaryKeyColumn, $id)
                ->update([$orderColumnName => $startOrder++]);
        }
    }

    public static function setNewOrderByCustomColumn(string $primaryKeyColumn, $ids, int $startOrder = 1)
    {
        self::setNewOrder($ids, $startOrder, $primaryKeyColumn);
    }

    public function determineOrderColumnName(): string
    {
        return $this->sortable['order_column_name'] ?? config('eloquent-sortable.order_column_name', 'weight');
    }

    /**
     * Determine if the order column should be set when saving a new model instance.
     */
    public function shouldSortWhenCreating(): bool
    {
        return $this->sortable['sort_when_creating'] ?? config('eloquent-sortable.sort_when_creating', true);
    }

    public function moveOrderDown()
    {
        $orderColumnName = $this->determineOrderColumnName();

        $swapWithModel = $this->buildSortQuery()
            ->where($orderColumnName, '<', $this->$orderColumnName)
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
        $orderColumnName = $this->determineOrderColumnName();

        $swapWithModel = $this->buildSortQuery()
            ->where($orderColumnName, '>', $this->$orderColumnName)
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
        $orderColumnName = $this->determineOrderColumnName();

        $oldOrderOfOtherModel = $otherModel->$orderColumnName;

        $otherModel->$orderColumnName = $this->$orderColumnName;
        $otherModel->save();

        $this->$orderColumnName = $oldOrderOfOtherModel;
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
            ->limit(1)
            ->first();

        if ($firstModel->getKey() === $this->getKey()) {
            return $this;
        }

        $orderColumnName = $this->determineOrderColumnName();

        $this->$orderColumnName = $firstModel->$orderColumnName;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->decrement($orderColumnName);

        return $this;
    }

    public function moveToEnd()
    {
        $minOrder = $this->getMinOrderNumber();

        $orderColumnName = $this->determineOrderColumnName();

        if ($this->$orderColumnName === $minOrder) {
            return $this;
        }

        $this->$orderColumnName = $minOrder;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->increment($orderColumnName);

        return $this;
    }

    public function insertBefore(Sortable $model)
    {
        if ($model->getKey() === $this->getKey()) {
            return $this;
        }

        $newOrder = $model->$orderColumnName;

        $this->$orderColumnName = $newOrder;
        $this->save();

        $this->buildSortQuery()
            ->where($this->getKeyName(), '!=', $this->getKey())
            ->where($orderColumnName, '<=', $newOrder)
            ->decrement($orderColumnName);

        return $this;
    }

    public function buildSortQuery()
    {
        return static::query();
    }
}
