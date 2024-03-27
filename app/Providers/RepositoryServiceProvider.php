<?php

namespace App\Providers;

use App\Interfaces\ErrorRepositoryInterface;
use App\Repositories\ErrorRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(ErrorRepositoryInterface::class, ErrorRepository::class);

    }
}
