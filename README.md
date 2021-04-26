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
- Setup notification channel

  ###### Slack
    - Create slack webhook for notify, and set the hook url in _.env_ file
      ```
      SLACK_URL=https://hooks.slack.com/services/....
      ```

  ###### Pushover
    - Create one (or more for every exchange) application in pushover.net
    - Set the user key and the app(s) token in _.env_ file
      ```
      PUSHOVER_KEY=pushover_userkey
      PUSHOVER_APP=pushover_app_common_token
      PUSHOVER_APP_BITTREX=pushover_app_bittrex_token
      ```

  ###### Telegram
    - Talk to [@BotFather](https://core.telegram.org/bots#6-botfather) and generate a Bot API Token.
    - Get your telegram user id
    - Configure your Telegram Bot API Token in _.env_ file
      ```
      TELEGRAM_BOT_TOKEN=12345678:foobarbazz
      TELEGRAM_USER_ID=12345
      ```

  ###### Discord

    - [Create a Discord application.](https://discord.com/developers/applications)
    - Click the `Create a Bot User` button on your Discord application.
    - Configure your bot's API token in _.env_ file
      ```
      DISCORD_TOKEN=foobarbaz
      ```
    - Add the bot to your server and identify it by running the artisan command:
      ```shell
      php exchange-notify discord:setup
      ```
    - [Get your User ID](https://support.discord.com/hc/en-us/articles/206346498-Where-can-I-find-my-User-Server-Message-ID-), and set it in _.env_ file
      ```
      DISCORD_USER_ID=123456789
      ```
    
- Setup exchange apis, in _config/exchanges.yaml_ file

  > **Note:** you can setup the notification channel individually for every exchange

  ```
  ---
  - exchange: bittrex
    apikey: 123456789
    apisecret: 98765432
    account: youraccountname
    notify: slack,telegram,pushover
  - exchange: binance
    apikey: 123456789
    apisecret: 98765432
    account: youraccountname
    notify: slack,telegram,pushover
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
