# Laravel Auditor

An audit trail manager for Laravel via Eloquent observers.

## Installation

Install the package via composer:

```shell
composer require liamdemafelix/laravel-auditor
```

This will include the auditor package in your project. Now, publish the configuration file and the migration file:

```shell
php artisan vendor:publish --provider="Liamdemafelix\LaravelAuditor\AuditorServiceProvider"
```

Finally, run the migration:

> NOTE: If you are using UUID primary keys, replace instances of `unsignedBigInteger` with `uuid` before migrating.

```shell
php artisan migrate
```

## Configuration

By default, `create`, `update`, `delete` and `restore` operations performed via Eloquent are observed. This means you do not need to call any other function, simply include the models you want to watch in the `models` key in the configuration file (`config/auditor.php`).

Options are documented in `config/auditor.php` and are provided with sane defaults. Feel free to modify as needed.

By default, `password` is defined in the `discards` key for security purposes. Please do not remove this unless you know what you're doing.

## Records

Audit trail records are saved in the `audit_logs` table and is automatically created upon every successful `created`, `updated` and `deleted` event monitored by an observer. Records are stored in JSON and can be searched via fuzzy search (using `LIKE` direct in the `record` column), or by using Laravel's [`whereJsonContains()`](https://laravel.com/docs/6.x/queries#json-where-clauses) method for more specific results.

### What does it look like?

The actual record is stored as JSON, so it's easy to do a `json_decode()` on the record and call whatever record you want to use. For example:

```php
<?php

// ... other code here ... //

$result = json_decode($trail->record);
echo "Old value: " . $result->name->old . "<br>";
echo "New value: " . $result->name->new;
```

> On update, it only saves the fields that actually changed (and because we're using observers, calling `update()` with the same data won't record a new entry)

It's clean and coherent, you can modify your spiels to look however you want, since we only store the data and not how it's constructed. In JSON, it looks like the following (an example of a `create` action log):

```json
{ 
   "name":{ 
      "old": "John Smith",
      "new": "Mario Berge"
   },
   "email":{ 
      "old": "john.smith@example.com",
      "new": "dbergstrom@stokes.biz"
   }
}
```

# Discarding Data

### Global Discards

You can discard a field name globally by setting it in `config/auditor.php`.

```php
<?php

return [
    /**
     * Specify which fields (columns) to discard in the log for data changes.
     *
     * For security purposes, "passsword" and "remember_token" are included below.
     * Since we are already recording event changes with timestamps, we also included
     * created_at, updated_at, deleted_at and banned_at by default.
     */
    "discards" => [
        "password",
        "remember_token",
        "created_at",
        "updated_at",
        "deleted_at",
        "banned_at"
    ]
];
```

### Model-specific Discards

In addition, if you want to discard a field specific to a model, you may add a `public $discarded` declaration in your model:

```php
<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The keys defined in this array is discared by the auditor.
     *
     * @var array
     */
    public $discarded = [
        'password'
    ];
}
```

**Never** store sensitive data in plaintext. Sane defaults have been provided (see `config/auditor.php`), adjust as necessary.

## License

This library is published under the [MIT Open Source license](https://github.com/liamdemafelix/auditor/blob/master/LICENSE).
