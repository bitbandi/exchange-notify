<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use PragmaRX\Yaml\Package\Yaml;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->publishConfig();
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
     * Publish config
     */
    public function publishConfig()
    {
        $this->publishes(
            [
                __DIR__ . '/../config/exchanges.yaml' => config_path('exchanges.yaml')
            ],
            'config'
        );
    }

    /**
     * Merge config
     */
    public function mergeConfig()
    {
        $app = config_path('exchanges.yaml');
//        var_dump($app);
        $package = __DIR__ . '/../config/exchanges.yaml';
//        var_dump($package);
        (new Yaml())->loadToConfig(
            file_exists($app) ? $app : $package,
            'exchanges'
        );
//         var_dump($this->app['config']);
    }
}
