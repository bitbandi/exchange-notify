<?php

namespace App\Commands;

use App\Interfaces\ErrorRepositoryInterface;
use App\Models\Error;
use App\Service\ExchangeConfig;
use App\Service\ExchangeService;
use Exception;
use Stringable;
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
     * @var ErrorRepositoryInterface
     */
    protected $errorRepository;

    /**
     * QueryCommand constructor.
     *
     * @param ExchangeService $exchangeService
     */
    public function __construct(ExchangeService $exchangeService, ErrorRepositoryInterface $errorRepository)
    {
        parent::__construct();

        $this->exchangeService = $exchangeService;
        $this->errorRepository = $errorRepository;
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
                    try {
                        $this->exchangeService->Query($exchangeConfig);
                        $this->errorRepository->deleteByExchange($exchangeConfig);
                    } catch (Exception $exception) {
                        $this->errorRepository->updateOrCreateByExchange($exchangeConfig, $exception->getMessage());
                        $this->error($exception->getMessage());
                    }
                }
            }
            $this->errorRepository->delete();
        } catch (Exception $exception) {
            $this->errorRepository->updateOrCreate($exception->getMessage());
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
        $schedule->command(static::class)
            ->everyTenMinutes()
            ->thenWithOutput(function (Stringable $output) {
                echo($output);
            });
    }
}
