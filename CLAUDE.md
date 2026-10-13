# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Overview

Exchange Notify is a stand-alone CLI application (Laravel Zero) that polls crypto exchange APIs via [ccxt](https://github.com/ccxt/ccxt) for trades, deposits and withdrawals, records them in a database, and sends notifications (Slack, Pushover, Telegram, Discord) when new activity is found.

## Common commands

```bash
composer install                    # install dependencies

./exchange-notify migrate:install   # create the migrations table (first run)
./exchange-notify migrate           # run migrations

./exchange-notify query                       # poll every configured exchange
./exchange-notify query bittrex binance       # poll only the named exchange(s)
./exchange-notify query --no-notify           # poll without sending notifications

php exchange-notify discord:setup   # one-time helper to identify the bot for Discord notifications

vendor/bin/pest                     # run the full test suite
vendor/bin/pest tests/Feature/QueryCommandTest.php   # run a single test file
vendor/bin/pest --filter "query command"             # run a single test by name
```

Tests use Pest (with the Laravel Zero test harness in `tests/TestCase.php` / `tests/CreatesApplication.php`); `phpunit.xml.dist` defines the `Feature`/`Unit` suites.

There is no configured linter (StyleCI is defined in `.styleci.yml` with the `laravel` preset but runs externally, not locally).

## Configuration

- Exchange accounts are configured in a YAML file (`config/exchanges.sample.yaml` is the template), one entry per exchange/account, with keys like `exchange`, `apikey`, `apisecret`, `account`, `notify` (comma list of channels), and optional `password`, `customlogin`, `nofetch` (list of `balance`/`trades`/`deposits`/`withdrawals` to skip), and `tradesymbols`/`depositsymbols`/`withdrawalsymbols` (per-symbol queries). The file path comes from `EXCHANGES_LOC` (see `config/services.php`), defaulting to `config/exchanges.yaml`, and is merged into Laravel config under the `exchanges` key at boot (`App\Providers\AppServiceProvider::mergeConfig`, via `pragmarx/yaml`).
- Notification channel credentials (Slack webhook, Pushover keys/per-exchange app tokens, Telegram bot token/user id, Discord token/user id) are set via `.env` (see `.env.example`) and read in `config/notifications.php` and `config/services.php`.
- Database connection (`mysql` or `pgsql`) is configured via the standard `DB_*` env vars in `config/database.php`.

## Architecture

**Command flow**: `App\Commands\QueryCommand` (the `query` artisan command, also scheduled every 10 minutes via `schedule()`) iterates the `exchanges` config array, wraps each entry in an `App\Service\ExchangeConfig` value object, and delegates to `App\Service\ExchangeService::Query()`. Per-exchange failures are caught and recorded via `ErrorRepositoryInterface` (bound to `App\Repositories\ErrorRepository` in `RepositoryServiceProvider`) rather than aborting the whole run; on success any prior error record for that exchange/account is cleared.

**Dynamic ccxt class resolution**: `ExchangeService::Query()` instantiates exchange clients by string class name under a fake namespace, `\exchangenotify\ccxt\{name}`. These names don't really exist as classes — `App\Providers\AppServiceProvider::register()` uses Laravel's `AliasLoader` to alias every `exchangenotify\ccxt\{exchange}` to the real `\ccxt\{exchange}` class from the ccxt library, then overrides specific exchanges (kraken, kucoin, bitget, bitstamp) to point at custom subclasses in `app/Helpers/` instead. This indirection is what lets per-exchange bug fixes/quirks live in `App\Helpers\Ccxt*` classes without touching `ExchangeService`. When ccxt's behavior needs patching for a given exchange (missing endpoint, wrong data mapping, symbol renaming, etc.), add/extend a class in `app/Helpers/` and wire it into the alias list in `AppServiceProvider::register()`. Fake exchanges that are not in ccxt at all (`cowswap`, `etherscan`) are appended to `\ccxt\Exchange::$exchanges` and aliased to their `App\Helpers` class the same way; they take a wallet address as the symbol (`network:0xaddress`) via `tradesymbols`/`depositsymbols`/`withdrawalsymbols`.

**Data model**: `Trade`, `Transaction`, `Balance`, and `Error` all extend `App\Models\CompositeKeysModel`, a base Eloquent model that supports composite primary keys (arrays in `$primaryKey`) by overriding `setKeysForSaveQuery`/`getKeyForSaveQuery`, since stock Eloquent only supports single-column keys. New/changed records go through `updateOrCreate()`, and `wasRecentlyCreated`/`wasChanged()` on the result determines whether a notification fires — this is what prevents duplicate notifications on repeated polls of the same trade/transaction id.

**Notifications**: `App\Notifications\BaseNotification` picks delivery channels based on the per-exchange `notify` config value (`slack`, `pushover`, `telegram`, `discord`); concrete notifications (`TradeComplete`, `TransactionComplete`) implement a `to{Channel}()` method per channel. Models route notifications via the shared `App\Notifications\Notifiable` trait, which resolves Slack webhook URL, Pushover user/app tokens (exchange-specific token falls back to a common one via `notifications.pushover_tokens`), Telegram user id, and Discord private channel from config.

**Balance tracking is currently disabled**: `ExchangeService::updateBalances()` exists but is not called (the `fetchBalance` block in `Query()` is commented out).
