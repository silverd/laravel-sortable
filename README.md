# Sortable behaviour for Eloquent models

@thanks spatie/eloquent-sortable

## Installation

This package can be installed through Composer.

```
composer require silverd/laravel-sortable:dev-master
```

Optionally you can publish the config file with:

```bash
php artisan vendor:publish --provider="Silverd\LaravelSortable\ServiceProvider"
```

## Usage

To add sortable behaviour to your model you must:
1. Implement the `Silverd\LaravelSortable\Sortable` interface.
2. Use the trait `Silverd\LaravelSortable\SortableTrait`.
3. Optionally specify which column will be used as the order column. The default is `weight`.

### Example

```php
use Silverd\LaravelSortable\Sortable;
use Silverd\LaravelSortable\SortableTrait;

class MyModel extends Eloquent implements Sortable
{
    use SortableTrait;

    public $sortable = [
        'sort_column_name' => 'weight',
        'sort_when_creating' => 'end',
    ];
    ...
}
```

If you don't set a value `$sortable['sort_column_name']` the package will assume that your order column name will be named `weight`.

If you don't set a value `$sortable['sort_when_creating']` the package will automatically assign the lowest order number to a new model;

Assuming that the db-table for `MyModel` is empty:

```php
$myModel = new MyModel();
$myModel->save(); // weight for this record will be set to 1

$myModel = new MyModel();
$myModel->save(); // weight for this record will be set to 2

$myModel = new MyModel();
$myModel->save(); // weight for this record will be set to 3


//the trait also provides the ordered query scope
$orderedRecords = MyModel::ordered()->get();
```

You can set a new order for all the records using the `setNewOrder`-method

```php
/**
 * the record for model id 3 will have weight value 1
 * the record for model id 1 will have weight value 2
 * the record for model id 2 will have weight value 3
 */
MyModel::setNewOrder([3,1,2]);
```

Optionally you can pass the starting order number as the second argument.

```php
/**
 * the record for model id 3 will have weight value 11
 * the record for model id 1 will have weight value 12
 * the record for model id 2 will have weight value 13
 */
MyModel::setNewOrder([3,1,2], 10);
```

To sort using a column other than the primary key, use the `setNewOrderByCustomColumn`-method.

```php
/**
 * the record for model uuid '7a051131-d387-4276-bfda-e7c376099715' will have weight value 1
 * the record for model uuid '40324562-c7ca-4c69-8018-aff81bff8c95' will have weight value 2
 * the record for model uuid '5dc4d0f4-0c88-43a4-b293-7c7902a3cfd1' will have weight value 3
 */
MyModel::setNewOrderByCustomColumn('uuid', [
   '7a051131-d387-4276-bfda-e7c376099715',
   '40324562-c7ca-4c69-8018-aff81bff8c95',
   '5dc4d0f4-0c88-43a4-b293-7c7902a3cfd1'
]);
```

As with `setNewOrder`, `setNewOrderByCustomColumn` will also accept an optional starting order argument.

```php
/**
 * the record for model uuid '7a051131-d387-4276-bfda-e7c376099715' will have weight value 10
 * the record for model uuid '40324562-c7ca-4c69-8018-aff81bff8c95' will have weight value 11
 * the record for model uuid '5dc4d0f4-0c88-43a4-b293-7c7902a3cfd1' will have weight value 12
 */
MyModel::setNewOrderByCustomColumn('uuid', [
   '7a051131-d387-4276-bfda-e7c376099715',
   '40324562-c7ca-4c69-8018-aff81bff8c95',
   '5dc4d0f4-0c88-43a4-b293-7c7902a3cfd1'
], 10);
```

You can also move a model up or down with these methods:

```php
$myModel->moveOrderDown();
$myModel->moveOrderUp();
```

You can also move a model to the first or last position:

```php
$myModel->moveToStart();
$myModel->moveToEnd();
```

You can swap the order of two models:

```php
MyModel::swapOrder($myModel, $anotherModel);
```

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
