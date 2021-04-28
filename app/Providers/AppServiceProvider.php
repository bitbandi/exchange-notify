<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PragmaRX\Yaml\Package\Facade as Yaml;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        \DB::listen(function ($query) {
            var_dump($query->sql, $query->bindings);
//            \Log::debug("DB: " . $query->sql . "[".  implode(",",$query->bindings). "]");
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfig();
    }

    /**
     * Merge config
     */
    public function mergeConfig()
    {
        $exchanges = config('services.exchanges.config');
        if (file_exists($exchanges)) {
            Yaml::loadToConfig($exchanges, 'exchanges');
        }
    }
}
