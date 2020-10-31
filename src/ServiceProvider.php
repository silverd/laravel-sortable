<?php

namespace Silverd\LaravelSortable;

use Illuminate\Support\ServiceProvider;

class LaravelSortableServiceProvider extends ServiceProvider
{
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/eloquent-sortable.php' => config_path('eloquent-sortable.php'),
            ], 'config');
        }
    }
}
