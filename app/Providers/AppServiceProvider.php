<?php

namespace App\Providers;

use App\Helpers\CcxtBitget;
use App\Helpers\CcxtKucoin;
use Illuminate\Foundation\AliasLoader;
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
        $loader = AliasLoader::getInstance();
        foreach (\ccxt\Exchange::$exchanges as $ex) {
            $loader->alias("exchangenotify\\ccxt\\". $ex, "\\ccxt\\". $ex);
        }
        $loader->alias("exchangenotify\\ccxt\\kucoin", CcxtKucoin::class);
        $loader->alias("exchangenotify\\ccxt\\bitget", CcxtBitget::class);
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
