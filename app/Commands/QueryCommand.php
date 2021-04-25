<?php

namespace App\Commands;

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
/*            $exchange_name = '\\ccxt\\' . $item;
            if (class_exists($exchange_name)) {
            }*/
            $request_exchanges = $this->argument('exchange');
            var_dump($request_exchanges);
            foreach ($exchanges as $exchange) {
                if (empty($request_exchanges) || in_array($exchange["exchange"], $request_exchanges)) {
                    var_dump($exchange);
                    $this->exchangeService->Query($exchange);
                }
            }
            if (!$this->option('no-notify')) {
                // $this->call('import:images');
                $this->info('ide kerul majd a notifiy');
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
