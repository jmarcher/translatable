# Translatable Eloquent models

[![Total Downloads](https://img.shields.io/packagist/dt/spletna-postaja/translatable?label=Downloads&style=flat-square&cacheSeconds=600)](https://packagist.org/packages/spletna-postaja/translatable)
[![Build Status](https://img.shields.io/travis/spletna-postaja/translatable/master?label=Build&style=flat-square&cacheSeconds=600)](https://travis-ci.org/spletna-postaja/translatable) 
[![CircleCI](https://img.shields.io/circleci/build/github/spletna-postaja/translatable/master?label=CircleCI&style=flat-square&cacheSeconds=600)](https://circleci.com/gh/spletna-postaja/translatable) 
[![StyleCI](https://github.styleci.io/repos/215050904/shield?branch=master)](https://github.styleci.io/repos/215050904) 
[![ScrutinizerCI](https://img.shields.io/scrutinizer/quality/g/spletna-postaja/translatable/master?label=ScrutinizerCI&style=flat-square&cacheSeconds=600)](https://scrutinizer-ci.com/g/spletna-postaja/translatable/) 
[![GitHub issues](https://img.shields.io/github/issues/spletna-postaja/translatable?label=Issues&style=flat-square)](https://github.com/spletna-postaja/translatable/issues) 
[![GitHub release (latest SemVer)](https://img.shields.io/github/v/release/spletna-postaja/translatable?label=Release&style=flat-square&cacheSeconds=600)](https://github.com/spletna-postaja/translatable)
[![MIT License](https://img.shields.io/github/license/spletna-postaja/translatable?label=License&color=blue&style=flat-square&cacheSeconds=600)](https://github.com/spletna-postaja/translatable/blob/master/LICENSE)

This package provides a powerful and transparent way of managing multilingual models in Eloquent.

It makes use of Laravel's enhanced global scopes to join translated attributes to every query rather than utilizing
relations as some alternative packages. As a result, only a single query is required to fetch translated attributes and
there is no need to create separate models for translation tables, making this package easier to use.

* [Quick demo](#quick-demo)
* [Versions](#versions)
* [Installation](#installation)
  * [Configuration in Laravel](#configuration-in-laravel)
  * [Configuration outside Laravel](#configuration-outside-laravel)
* [Creating migrations](#creating-migrations)
* [Configuring models](#configuring-models)
* [CRUD operations](#crud-operations)
  * [Selecting rows](#selecting-rows)
  * [Inserting rows](#inserting-rows)
  * [Updating rows](#updating-rows)
  * [Deleting rows](#deleting-rows)
* [Translations as a relation](#translations-as-a-relation)
* [Author and maintenance](#author-and-maintenance)
* [License](#licence)

## Quick demo

To enable translations in your models, you first need to prepare your schema according to the
[convention](#creating-migrations). After that you can pull in the ``Translatable`` trait:

```php
use Laraplus\Data\Translatable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Translatable;
}
```

And that's it! No other configuration is required. The translated attributes will be automatically cached and all your
queries will start returning translated attributes:

```php
Post::first();
$post->title; // title in the current locale

Post::translateInto('de')->first();
$post->title; // title in 'de' locale

Post::translateInto('de')->withFallback('en')->first();
$post->title; // title in 'de' if available, otherwise in 'en'
```

Since translations are joined to the query it's also very easy to filter and sort by translated attributes:

```php
Post::where('body', 'LIKE', '%Laravel%')->orderBy('title', 'desc');
```

Or even return only translated records:

```php
Post::onlyTranslated()->all()
```

Multiple [helpers](#crud-operations) are available for all basic CRUD operations. For all available options, read the
[full documentation](#crud-operations) below.

## Versions

| Package | Laravel | PHP |
| :--- | :--- | :--- |
| **v1.0.0 - v1.0.22** | `5.2.* - 5.8.*` | `5.6.* / 7.0.* - 7.2.*` |
| **v2.0.0 - v2.0.*** | `>=6.0` | `>=7.3.*` |

## Installation

This package can be used within Laravel or Lumen applications as well as any other application that utilizes Laravel's
database component https://github.com/illuminate/database. The package can be installed through composer:

```
composer require spletna-postaja/translatable
```

### Configuration in Laravel

The package will be auto-discovered in Laravel although you can still manually add a service provider to your
 ``/config/app.php`` configuration file, under the ``providers`` key:

```php
'providers' => [
    // Other providers
    Laraplus\Data\TranslatableServiceProvider::class,
],
```

Optionally you can configure some other options by publishing the ``translatable.php`` configuration file:

```
php artisan vendor:publish --provider="Laraplus\Data\TranslatableServiceProvider" --tag="config"
```

Open the configuration file to check all available settings:
https://github.com/spletna-postaja/translatable/blob/master/config/translatable.php

### Configuration outside Laravel

When using this package outside Laravel, you can configure it using ``TranslatableConfig`` class:

```php
TranslatableConfig::currentLocaleGetter(function() {
    // Return the current locale of the application
});

TranslatableConfig::fallbackLocaleGetter(function() {
    // Return the fallback locale of the application
});
```

You can optionally adjust some other settings as well. To see all available options inspect Laravel's Service Provider:
https://github.com/spletna-postaja/translatable/blob/master/src/TranslatableServiceProvider.php

## Creating migrations

To utilize multilingual models you need to prepare your database tables in a certain way. Each translatable table
consists of translatable and non translatable attributes. While non translatable attributes can be added to your table
normally, translatable fields need to be in their own table named according to the convention.

Below you can see a sample migration for the ``posts`` table:

```php
Schema::create('posts', function(Blueprint $table)
{
    $table->increments('id');
    $table->datetime('published_at');
    $table->timestamps();
});

Schema::create('posts_i18n', function(Blueprint $table)
{
    $table->integer('post_id')->unsigned();
    $table->string('locale', 6);
    $table->string('title');
    $table->string('body');
    
    $table->primary(['post_id', 'locale']);
});
```

By default, translation tables must end with ``_i18n`` suffix although this can be changed in the previously mentioned 
configuration file. Translation table must always contain a foreign key to the parent table as well as a ``locale`` 
field (also configurable) which will store the locale of translated attributes. Incrementing keys are not allowed on
translation models. A composite key containing ``locale`` and foreign key reference to the parent model needs to be 
defined instead. Optionally you may define foreign key constraints, but the package will work without them as well.

**Important: make sure that no translated attributes are named the same as any non translated attribute since that will
break the queries. This also applies to timestamps (which should not be added to the translation tables but to primary
tables only) and for incrementing keys (not allowed on translation tables).**

## Configuring models

To make your models aware of the translated attributes you need to pull in the ``Translatable`` trait:

```php
use Laraplus\Data\Translatable;
use Illuminate\Database\Eloquent\Model;

class Post extends Model
{
    use Translatable;
}
```

Optionally you may define an array of ``$translatable`` attributes, but the package is designed to work without it.
In that case translatable attributes will be automatically determined from the database schema and cached indefinitely.
If you are using the cache approach, don't forget to clear the cache every time the schema changes.

By default, if the model is not translated into the current locale, fallback translations will be selected instead.
If no translations are available, ``null`` will be returned for all translatable attributes. If you wish to change that
behavior you can either modify the ``translatable.php`` configuration file or adjust the behavior on "per model" basis:

```php
class Post extends Model
{
    use Translatable;
    
    protected $withFallback = false;
    
    protected $onlyTranslated = true;
}
```

## CRUD operations

### Selecting rows

To select rows from your translatable models, you can use all of the usual Eloquent query helpers. Translatable
attributes will be returned in your current locale. To learn more about how to configure localization in Laravel,
please refer to the official documentation: https://laravel.com/docs/6.0/localization

```php
Post::where('active', 1)->orderBy('title')->get();
```

#### Query helpers

The above query will by default also return records that don't have any translations in the current or fallback locale.
To return only translated rows, you can change the ``defaults.only_translated`` config option to ``true``, or use the
``onlyTranslated()`` query helper:

```php
Post::onlyTranslated()->get();
```

Sometimes you may want to disable fallback translations altogether. To do this, you may either change the
``defaults.with_fallback`` configuration option to ``false`` or use the ``withoutFallback()`` query helper:

```php
Post::withoutFallback()->get();
```

Both of the helpers above have their opposite forms: ``withUntranslated()`` and ``withFallback()``. You may also
provide an optional ``$locale`` argument to the ``withFallback()`` helper to change the default fallback locale:

```php
Post::withUntranslated()->withFallback()->get();
Post::withUntranslated()->withFallback('de')->get();
```

Sometimes you may wish to retrieve translations in a locale different from the current one. To achieve that, you may
use the ``translateInto($locale)`` helper:

```php
Post::translateInto('de')->get();
```

In case you do not need the translated attributes at all, you may use the ```withoutTranslations()``` helper, which 
will remove the translatable global scope from your query

```php
Post::withoutTranslations()->get();
```

#### Filtering and sorting by translated attributes

Often you may wish to filter query results by translated attributes. This package allows you to use all of the usual
Eloquent ``where`` clauses normally. This will work even with fallback translations since all of the columns within
where clauses will be automatically wrapped in the ``ifnull`` statements and prefixed with the appropriate table names:

```php
Post::where('title', 'LIKE', '%Laravel%')->orWhere('description', 'LIKE', '%Laravel%')->get();
```

The same logic applies for ``order by`` clauses, which will also be automatically transformed to the correct format:

```php
Post::orderBy('title')->get();
```

**Notice: if you are using ``whereRaw`` clauses, we will not be able to format your expressions automatically since
we do not parse whereRaw expressions. Instead you will need to include the appropriate table prefix manually.**

### Inserting rows

When creating new models in the current locale, you may use the normal Laravel syntax, as if you were inserting rows
into a single table:

```php
Post::create([
    'title'        => 'My title',
    'published_at' => Carbon::now(),
]);
```

If you want to store the record in an alternative locale, you may use the ``createInLocale($locale, $attributes)`` helper:

```php
Post::createInLocale('de', [
    'title'        => 'Title in DE',
    'published_at' => Carbon::now(),
]);
```

Often you will need to store a new record together with all translations. To do that, you may list translatable 
attributes as a second argument of the ``create()`` method:

```php
Post::create([
    'published_at' => Carbon::now()
], [
    'en' => ['title' => 'Title in EN'],
    'de' => ['title' => 'Title in DE'],
]);
```

All of the above helpers also have their ``force`` forms that let you bust the mass assignment protection.

```php
Post::forceCreate([/*attributes*/], [/*translations*/]);
Post::forceCreateInLocale($locale, [/*attributes*/]);
```

### Updating rows

Updating records in the current locale is as easy as if you were updating a single table:

```php
$user = User::first();

$user->title = 'New title';
$user->save();
```

If you wish to update a record in another locale, you may use the ``saveTranslation($locale, $attributes)`` helper
that will either update an existing translation or create a new one (if it doesn't exist yet):

```php
$user = User::first();

$user->saveTranslation('en', [
    'title' => 'Title in EN'
]);

$user->saveTranslation('de', [
    'title' => 'Title in DE'
]);
```

A ``forceSaveTranslation($locale, $attributes)`` helper is also available to bust mass assignment protection.

To update multiple rows at once, you may also use the query builder:

```php
User::where('published_at', '>', Carbon::now())->update(['title' => 'New title']);
```

To update a different locale using the query builder, you can call the ``transleteInto($locale)`` helper:

```php
User::where('published_at', '>', Carbon::now())->translateInto('de')->update(['title' => 'New title']);
```

### Deleting rows

Deleting rows couldn't be easier. Do it as per usual and translations will be automatically deleted together with
the parent row:

```php
$user = User::first();

$user->delete();
```

To delete multiple rows at once, you may also use the query builder. Translations will be cleaned up automatically:

```php
User::where('published_at', '>', Carbon::now())->delete();
```

## Translations as a relation

Sometimes you may wish to retrieve all translations of a certain model. Luckily the package implements a ``hasMany`` 
relation which will help you do just that:

```php
$user = $user->first();

foreach ($user->translations as $translation) {
    echo "Title in {$translation->locale}: {$translation->title}";
}
```

A ``translate($locale)`` helper is available when you wish to access an attribute in a specific locale:

```php
$user = $user->first();

$user->translate('en')->title; // Title in EN
$user->translate('de')->title; // Title in DE
```

When using the relation, you will usually want to preload it without joining translated attributes to the query.
There is a ``withAllTranslations()`` helper available to do just that:

```php
User::withAllTranslations()->get();
```

**Notice: there is currently limited support for updating and inserting new records using the relation. Instead you
can use the helpers described above.**

## Author and maintenance

Author and lead developer of this project is [Anže Časar](https://github.com/acasar).

This project is supported and maintained by [Spletna postaja](https://spletna-postaja.com/), a web development company.

Ta projekt podpira in vzdržuje [Spletna postaja](https://spletna-postaja.com/), podjetje za razvoj in [izdelavo spletnih strani](https://spletna-postaja.com/izdelava-spletnih-strani) in [izdelavo spletnih trgovin](https://spletna-postaja.com/izdelava-spletnih-trgovin).

[![Twitter](https://img.shields.io/twitter/url?label=Tweet%20about%20this%20project&style=social&url=https%3A%2F%2Fgithub.com%2Fspletna-postaja%2Ftranslatable)](https://twitter.com/intent/tweet?text=Wow:&url=https%3A%2F%2Fgithub.com%2Fspletna-postaja%2Ftranslatable)

## License

This project is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

[![MIT License](https://img.shields.io/github/license/spletna-postaja/translatable?label=License&color=blue&style=flat-square&cacheSeconds=600)](https://github.com/spletna-postaja/translatable/blob/master/LICENSE)
