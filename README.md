# FourCms Auth

Master: [![build status](https://gitlab.itdc.ge/fourcms/auth/badges/master/build.svg)](https://gitlab.itdc.ge/fourcms/auth/commits/master)

## Table of Contents
- [Installation](#installation)
- [Usage](#usage)
- [TODO](#todo)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)



## Installation

Install this package through [Composer](https://getcomposer.org/).

Edit your project's `composer.json` file to add custom repository and require `platfourm/auth`

Create *composer.json* file:
```js
{
    "name": "yourproject/yourproject",
    "type": "project",
    "require": {
        "platfourm/auth": "dev_master"
    },
    "repositories": [
        {
            "type": "composer",
            "url": "https://packagist.itdc.ge"
        }
    ]
}
```
And run composer update

After updating composer, add the AuthServiceProvider to the providers array in config/app.php

```php
Platfourm\Auth\AuthServiceProvider::class,
```

And add Platfourm\Auth\RemoteUserTrait to your user model.

After in config/auth.php add new guard in guards section

```php
'itdc' => [
    'driver'   => 'session',
    'provider' => 'itdc',
],
````

And add provider in providers section

```php
'itdc' => [
    'driver'      => 'itdc', // Driver name
    'model'       => App\User::class, // User model class
    'endpoint'    => 'https://service.itdc.ge/api7', // Authorization service endpoint
    'check_ip'    => true, // Check allowed or not authorization on service from user's ip
    'auto_save'   => true, // Save remote user in local database on success login. On false will be used cache storage
    'attach_role' => '', // If you are using Entrust Permissions package, provide default role for remote user
],
```

After you can change default guard in config/auth.php to itdc:

```php
'defaults' => [
    'guard'     => 'itdc',
    'passwords' => 'users',
],
```

## Usage

You must add application APP_KEY to ITDC Auth servers for authentication


## TODO

write tests

## Troubleshooting

Please report any bugs you find on the
[issues](https://gitlab.itdc.ge/fourcms/auth/issues) page.

## Contributing

Pull requests are welcome.
See [CONTRIBUTING.md](CONTRIBUTING.md) for information.

## License

Please see the [LICENSE](LICENSE.md) included in this repository for a full copy of the proprietary license,
which this project is licensed under.

## Credits

- [Avtandil Kikabidze aka LONGMAN](https://longman.me)
