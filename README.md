# Drupal Robo Tasks

PHP library based in [Robo](https://robo.li/) that provides handy tasks to manage a Drupal 8 site.

## Requirements

* PHP 7.0 or higher
* [Composer](https://getcomposer.org/)
* [Drush](https://www.drush.org/)
* [drupal-potx](https://packagist.org/packages/kgaut/potx)
* [PHP dotenv](https://packagist.org/packages/vlucas/phpdotenv)

## Install

Via Composer

``` bash
$ composer require ymbra/drupalrobotasks
```

## Available tasks

#### Install Drupal site.
``` bash
$ vendor/bin/drobo site:install
```

#### Update Drupal site.
``` bash
$ vendor/bin/drobo site:update
```

#### Backup Drupal database.
``` bash
$ vendor/bin/drobo db:backup
```

## License

Please see [License File](LICENSE) for more information.
