<?php

namespace App\Commands;

use App\Service\ExchangeConfig;
use App\Service\ExchangeService;
use Exception;
use Illuminate\Console\Scheduling\Schedule;
use LaravelZero\Framework\Commands\Command;

class QueryCommand extends Command
{
    /**
     * The signature of the command.
     *
     * @var string
     */
    protected $signature = 'query {exchange?* : Query only this exchange(s)}
    {--no-notify : Do not send notify message (optional)}';

    /**
     * The description of the command.
     *
     * @var string
     */
    protected $description = 'Display an inspiring quote';

    /**
     * @var ExchangeService
     */
    protected $exchangeService;

    /**
     * QueryCommand constructor.
     *
     * @param ExchangeService $exchangeService
     */
    public function __construct(ExchangeService $exchangeService)
    {
        parent::__construct();

        $this->exchangeService = $exchangeService;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        try {
//
            if (empty($exchanges = config('exchanges'))) {
                $this->error('There are no registered exchanges.');
                return;
            }
            $this->exchangeService->setNotify(!$this->option('no-notify'));
            $request_exchanges = $this->argument('exchange');
            foreach ($exchanges as $exchange) {
                $exchangeConfig = new ExchangeConfig($exchange);
                if (empty($request_exchanges) || in_array($exchangeConfig->getName(), $request_exchanges)) {
                    $this->exchangeService->Query($exchangeConfig);
                }
            }
        } catch (Exception $exception) {
            $this->error($exception->getMessage());
        }
    }

    /**
     * Define the command's schedule.
     *
     * @param Schedule $schedule
     * @return void
     */
    public function schedule(Schedule $schedule)
    {
        //$schedule->command(static::class)->dailyAt(config('fathom.notify_at'));
        // $schedule->command(static::class)->everyMinute();
    }
}
