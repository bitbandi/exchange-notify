# Exchange notify

A small stand-alone CLI app, built with [Laravel Zero](https://laravel-zero.com/), that notifies you about your trades and transactions history in crypto exchanges.

------

## Install

- Clone the repository
  ```bash
  git clone https://github.com/bitbandi/exchange-notify.git
  cd exchange-notify/
  ```
- Install vendor libraries
  ```bash
  composer install
  ```
- Copy .env.example to .env
  ```bash
  cp .env.example .env
  ```
- Create database layout, edit .env. "mysql" and "postgresql" are acceptable `DB_CONNECTION` types.
  ```mysql
  CREATE DATABASE exchangenotify;
  GRANT ALL on exchangenotify.* to exchangenotify@localhost IDENTIFIED BY 'MAKEUPYOUROWNPASSWORD';
  ```
  Update the _.env_ file, set the `DB_USERNAME`, `DB_PASSWORD` and `DB_DATABASE` fields.
- Migrate the database
  ```bash
  ./exchange-notify migrate:install
  ./exchange-notify migrate
  ```
- Create slack webhook for notify, and set the hook url in _.env_ file
  ```
  SLACK_URL=https://hooks.slack.com/services/....
  ```
- Setup exchange apis, in _config/exchanges.yaml_ file
  ```
  ---
  - exchange: bittrex
    apikey: 123456789
    apisecret: 98765432
    account: youraccountname
    notify: slack
  - exchange: binance
    apikey: 123456789
    apisecret: 98765432
    account: youraccountname
    notify: slack
  
  ```

### Use

#### Basic usage

```shell
    php exchange-notify query
```

## Documentation

Coming soon.

## License

Exchange Notify is an open-source software licensed under the [MIT license](LICENSE.md).
