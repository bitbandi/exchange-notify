<?php


namespace App;


use Illuminate\Contracts\Foundation\Application;

class Configuration
{
    /**
     * The application instance.
     *
     * @var Application
     */
    protected $app;
    /**
     * Create a new service provider instance.
     *
     * @param Application $app
     * @return void
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * Get the configured exchanges.
     *
     * @return array
     */
    public function getExchanges(): array
    {
        return $this->app['config']['exchanges'];
    }
}
