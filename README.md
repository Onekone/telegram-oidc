# Telegram OpenID Connect (OIDC) Provider for Laravel Socialite

![Laravel Support: v10, v11](https://img.shields.io/badge/Laravel%20Support-v10%2C%20v11-blue) ![PHP Support: 8.1, 8.2, 8.3](https://img.shields.io/badge/PHP%20Support-8.1%2C%208.2%2C%208.3-blue)

Based on the work of [jp-gauthier](https://github.com/jp-gauthier) and Kevin Woblick ([GitHub](https://github.com/kovah/laravel-socialite-oidc), [website](https://woblick.dev))

## Installation & Basic Usage

```bash
composer require onekone/telegram-oidc
```

Please see the [Base Installation Guide](https://socialiteproviders.com/usage/), then follow the provider specific instructions below.

### Add configuration to `config/services.php`

```php
'telegram' => [
    'client_id'     => env('TELEGRAM_AUTH_CLIENT_ID'),
    'client_secret' => env('TELEGRAM_AUTH_CLIENT_SECRET'),
    'redirect'      => env('TELEGRAM_AUTH_REDIRECT_URI'),
],
```

### Add provider event listener

Configure the package's listener to listen for `SocialiteWasCalled` events.

#### Laravel 11+

In Laravel 11, the default `EventServiceProvider` provider was removed. Instead, add the listener using the `listen` method on the `Event` facade, in your `AppServiceProvider` `boot` method.

```php
Event::listen(function (\SocialiteProviders\Manager\SocialiteWasCalled $event) {
    $event->extendSocialite('telegram', \Onekone\TelegramSocialite\Provider::class);
});
```

#### Laravel 10 or below

Add the event to your listen[] array in `app/Providers/EventServiceProvider`. See the [Base Installation Guide](https://socialiteproviders.com/usage/) for detailed instructions.

```php
protected $listen = [
    \SocialiteProviders\Manager\SocialiteWasCalled::class => [
        // ... other providers
        \Onekone\TelegramSocialite\TelegramExtendSocialite::class.'@handle',
    ],
];
```

### Usage

You should now be able to use the provider like you would regularly use Socialite (assuming you have the facade
installed):

```php
return Socialite::driver('telegram')->redirect();
```

### Returned User fields

- `id` - user's Telegram ID
- `nickname` - user's current handle, maps to `preferred_username`
- `name` - user's display name, maps to `name`
- `email` - set to `null`, as Telegram does not reveal user emails, even for those that do have one
- `avatar` - user's avatar, maps to `picture`

Additionally entire [data structure](https://core.telegram.org/bots/telegram-login#user-data-structure) available under `user` field

### Customizing the scopes

You may extend the default scopes (`openid profile`) by adding a `scopes` option to your Telegram service configuration and separate multiple scopes with a space:

```php
'telegram' => [
    'client_id'     => env('TELEGRAM_AUTH_CLIENT_ID'),
    'client_secret' => env('TELEGRAM_AUTH_CLIENT_SECRET'),
    'redirect'      => env('TELEGRAM_AUTH_REDIRECT_URI'),
    
    'scopes'        => 'openid profile phone',
    // or
    'scopes'        => env('TELEGRAM_SCOPES', 'openid profile phone'),
],
```

However, Telegram only accepts [following scopes](https://core.telegram.org/bots/telegram-login#available-scopes):

| Scope                 | Description                                                                                                                                                                                                                                                                                    | Claims returned                         |
|-----------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|-----------------------------------------|
| `openid`              | **Required**. Returns the user's unique identifier and auth timestamp.                                                                                                                                                                                                                         | `sub`, `iss`, `iat`, `exp`              |
| `profile`             | User's basic info: name, username, and profile photo URL. *Not strictly required, but you wouldn't be able to identify user that actually logged in otherwise*                                                                                                                                 | `name`, `preferred_username`, `picture` |
| `phone`               | User's verified **phone number**. Requires user consent - *user will be presented with a choice of whenever they want to share their phone number. If they choose not to, login is still successful, but you don't get phone_number claim*                                                     | `phone_number`                          |
| `telegram:bot_access` | Allows your bot to send direct messages to the user after login. *During login user will be presented with a choice of whenever they allow bot to message them or not. You will **not** know whenever user allowed your bot to message them or not here, but you may receive an update at bot* | None                                    |

Returned claims will be available under user field