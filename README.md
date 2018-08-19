Laravel Couchbase
===============

[![Build Status](http://img.shields.io/travis/mpociot/laravel-couchbase.svg)](https://travis-ci.org/mpociot/laravel-couchbase)
[![codecov](https://codecov.io/gh/mpociot/laravel-couchbase/branch/master/graph/badge.svg)](https://codecov.io/gh/mpociot/laravel-couchbase)

An Eloquent model and Query builder with support for Couchbase, using the original Laravel API. *This library extends the original Laravel classes, so it uses exactly the same methods.*

Table of contents
-----------------
* [Installation](#installation)
* [Configuration](#configuration)
* [Eloquent](#eloquent)
* [Optional: Alias](#optional-alias)
* [Query Builder](#query-builder)
* [Schema](#schema)
* [Extensions](#extensions)
* [Troubleshooting](#troubleshooting)
* [Examples](#examples)

Installation
------------

Make sure you have the Couchbase PHP driver installed. You can find installation instructions at http://developer.couchbase.com/documentation/server/current/sdk/php/start-using-sdk.html

Installation using composer:

```
composer require pedmindset/laravel-couchbase dev-master
```

And add the service provider in `config/app.php`:

```php
Mpociot\Couchbase\CouchbaseServiceProvider::class,
```

For usage with [Lumen](http://lumen.laravel.com), add the service provider in `bootstrap/app.php`. In this file, you will also need to enable Eloquent. You must however ensure that your call to `$app->withEloquent();` is **below** where you have registered the `CouchbaseServiceProvider `:

```php
$app->register(Mpociot\Couchbase\CouchbaseServiceProvider::class);

$app->withEloquent();
```

The service provider will register a couchbase database extension with the original database manager. There is no need to register additional facades or objects. When using couchbase connections, Laravel will automatically provide you with the corresponding couchbase objects.

For usage outside Laravel, check out the [Capsule manager](https://github.com/illuminate/database/blob/master/README.md) and add:

```php
$capsule->getDatabaseManager()->extend('couchbase', function($config)
{
    return new Mpociot\Couchbase\Connection($config);
});
```

Configuration
-------------

Change your default database connection name in `config/database.php`:

```php
'default' => env('DB_CONNECTION', 'couchbase'),
```

And add a new couchbase connection:

```php
'couchbase' => [
    'driver'   => 'couchbase',
    'host'     => env('DB_HOST', 'localhost'),
    'port'     => env('DB_PORT', 8091),
    'bucket'   => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'n1ql_hosts' => [
        'http://'.env('DB_HOST', 'localhost').':8093'
    ]
],
```

You can connect to multiple servers or replica sets with the following configuration:

```php
'couchbase' => [
    'driver'   => 'couchbase',
    'host'     => ['host1', 'host2'],
    'port'     => env('DB_PORT', 8091),
    'bucket'   => env('DB_DATABASE'),
    'username' => env('DB_USERNAME'),
    'n1ql_hosts' => [
        'http://host1:8093',
        'http://host2:8093'
    ]
],
```

Eloquent
--------

This package includes a Couchbase enabled Eloquent class that you can use to define models for corresponding collections.

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class User extends Eloquent {}
```

As Couchbase does not provide the concept of tables, documents will instead be defined by a property called `_type`. Like the original Eloquent, the lower-casem plural name of the class will be used as the "table" name and will be placed inside the `_type` property of each document.

You may specify a custom type (alias for table) by defining a `table` property on your model:

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class User extends Eloquent {

    protected $table = 'my_users';

}
```

**NOTE:** Eloquent will also assume that each collection has a primary key column named `_id`. You may define a `primaryKey` property to override this convention. Likewise, you may define a `connection` property to override the name of the database connection that should be used when utilizing the model.

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class MyModel extends Eloquent {

    protected $connection = 'couchbase';

}
```

Everything else (should) work just like the original Eloquent model. Read more about the Eloquent on http://laravel.com/docs/eloquent

### Optional: Alias

You may also register an alias for the Couchbase model by adding the following to the alias array in `config/app.php`:

```php
'CouchbaseModel'       => 'Mpociot\Couchbase\Eloquent\Model',
```

This will allow you to use the registered alias like:

```php
class MyModel extends CouchbaseModel {}
```

Query Builder
-------------

The database driver plugs right into the original query builder. When using couchbase connections, you will be able to build fluent queries to perform database operations.


```php
$users = DB::table('users')->get();

$user = DB::table('users')->where('name', 'John')->first();
```

If you did not change your default database connection, you will need to specify it when querying.

```php
$user = DB::connection('couchbase')->table('users')->get();
```

Read more about the query builder on http://laravel.com/docs/queries

Schema
------
As this Couchbase driver implementation uses a single bucket for all documents, a Schema builder is not implemented.


Examples
--------

### Basic Usage

**Retrieving All Models**

```php
$users = User::all();
```

**Retrieving A Record By Primary Key**

```php
$user = User::find('517c43667db388101e00000f');
```

**Wheres**

```php
$users = User::where('votes', '>', 100)->take(10)->get();
```

**Or Statements**

```php
$users = User::where('votes', '>', 100)->orWhere('name', 'John')->get();
```

**And Statements**

```php
$users = User::where('votes', '>', 100)->where('name', '=', 'John')->get();
```

**Using Where In With An Array**

```php
$users = User::whereIn('age', [16, 18, 20])->get();
```

**Using Where Between**

```php
$users = User::whereBetween('votes', [1, 100])->get();
```

**Where null**

```php
$users = User::whereNull('updated_at')->get();
```

**Order By**

```php
$users = User::orderBy('name', 'desc')->get();
```

**Offset & Limit**

```php
$users = User::skip(10)->take(5)->get();
```

**Distinct**

Distinct requires a field for which to return the distinct values.

```php
$users = User::distinct()->get(['name']);
// or
$users = User::distinct('name')->get();
```

Distinct can be combined with **where**:

```php
$users = User::where('active', true)->distinct('name')->get();
```

**Advanced Wheres**

```php
$users = User::where('name', '=', 'John')->orWhere(function($query)
    {
        $query->where('votes', '>', 100)
              ->where('title', '!=', 'Admin');
    })
    ->get();
```

**Group By**

Selected columns that are not grouped will be aggregated with the $last function.

```php
$users = Users::groupBy('title')->get(['title', 'name']);
```

**Aggregation**

```php
$total = Order::count();
$price = Order::max('price');
$price = Order::min('price');
$price = Order::avg('price');
$total = Order::sum('price');
```

Aggregations can be combined with **where**:

```php
$sold = Orders::where('sold', true)->sum('price');
```

**Like**

```php
$user = Comment::where('body', 'like', '%spam%')->get();
```
**NOTE**: Like checks in Couchbase are case sensitive

**Incrementing or decrementing a value of a column**

Perform increments or decrements (default 1) on specified attributes:

```php
User::where('name', 'John Doe')->increment('age');
User::where('name', 'Jaques')->decrement('weight', 50);
```

The number of updated objects is returned:

```php
$count = User->increment('age');
```

You may also specify additional columns to update:

```php
User::where('age', '29')->increment('age', 1, ['group' => 'thirty something']);
User::where('bmi', 30)->decrement('bmi', 1, ['category' => 'overweight']);
```

**Soft deleting**

When soft deleting a model, it is not actually removed from your database. Instead, a deleted_at timestamp is set on the record. To enable soft deletes for a model, apply the SoftDeletingTrait to the model:

```php
use Mpociot\Couchbase\Eloquent\SoftDeletes;

class User extends Eloquent {

    use SoftDeletes;

    protected $dates = ['deleted_at'];

}
```

For more information check http://laravel.com/docs/eloquent#soft-deleting

### Inserts, updates and deletes

Inserting, updating and deleting records works just like the original Eloquent.

**Saving a new model**

```php
$user = new User;
$user->name = 'John';
$user->save();
```

You may also use the create method to save a new model in a single line:

```php
User::create(['name' => 'John']);
```

**Updating a model**

To update a model, you may retrieve it, change an attribute, and use the save method.

```php
$user = User::first();
$user->email = 'john@foo.com';
$user->save();
```

**Deleting a model**

To delete a model, simply call the delete method on the instance:

```php
$user = User::first();
$user->delete();
```

Or deleting a model by its key:

```php
User::destroy('517c43667db388101e00000f');
```

For more information about model manipulation, check http://laravel.com/docs/eloquent#insert-update-delete

### Relations

Supported relations are:

 - hasOne
 - hasMany
 - belongsTo
 - belongsToMany
 - embedsOne
 - embedsMany

Example:

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class User extends Eloquent {

    public function items()
    {
        return $this->hasMany('Item');
    }

}
```

And the inverse relation:

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class Item extends Eloquent {

    public function user()
    {
        return $this->belongsTo('User');
    }

}
```

The belongsToMany relation will not use a pivot "table", but will push id's to a __related_ids__ attribute instead. This makes the second parameter for the belongsToMany method useless. If you want to define custom keys for your relation, set it to `null`:

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class User extends Eloquent {

    public function groups()
    {
        return $this->belongsToMany('Group', null, 'user_ids', 'group_ids');
    }

}
```


Other relations are not yet supported, but may be added in the future. Read more about these relations on http://laravel.com/docs/eloquent#relationships

### EmbedsMany Relations

If you want to embed models, rather than referencing them, you can use the `embedsMany` relation. This relation is similar to the `hasMany` relation, but embeds the models inside the parent object.

**REMEMBER**: these relations return Eloquent collections, they don't return query builder objects!

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class User extends Eloquent {

    public function books()
    {
        return $this->embedsMany('Book');
    }

}
```

You access the embedded models through the dynamic property:

```php
$books = User::first()->books;
```

The inverse relation is auto*magically* available, you don't need to define this reverse relation.

```php
$user = $book->user;
```

Inserting and updating embedded models works similar to the `hasMany` relation:

```php
$book = new Book(['title' => 'A Game of Thrones']);

$user = User::first();

$book = $user->books()->save($book);
// or
$book = $user->books()->create(['title' => 'A Game of Thrones'])
```

You can update embedded models using their `save` method:

```php
$book = $user->books()->first();

$book->title = 'A Game of Thrones';

$book->save();
```

You can remove an embedded model by using the `destroy` method on the relation, or the `delete` method on the model:

```php
$book = $user->books()->first();

$book->delete();
// or
$user->books()->destroy($book);
```

If you want to add or remove an embedded model, without touching the database, you can use the `associate` and `dissociate` methods. To eventually write the changes to the database, save the parent object:

```php
$user->books()->associate($book);

$user->save();
```

Like other relations, embedsMany assumes the local key of the relationship based on the model name. You can override the default local key by passing a second argument to the embedsMany method:

```php
return $this->embedsMany('Book', 'local_key');
```

Embedded relations will return a Collection of embedded items instead of a query builder. Check out the available operations here: https://laravel.com/docs/master/collections

### EmbedsOne Relations

The embedsOne relation is similar to the EmbedsMany relation, but only embeds a single model.

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class Book extends Eloquent {

    public function author()
    {
        return $this->embedsOne('Author');
    }

}
```

You access the embedded models through the dynamic property:

```php
$author = Book::first()->author;
```

Inserting and updating embedded models works similar to the `hasOne` relation:

```php
$author = new Author(['name' => 'John Doe']);

$book = Books::first();

$author = $book->author()->save($author);
// or
$author = $book->author()->create(['name' => 'John Doe']);
```

You can update the embedded model using the `save` method:

```php
$author = $book->author;

$author->name = 'Jane Doe';
$author->save();
```

You can replace the embedded model with a new model like this:

```php
$newAuthor = new Author(['name' => 'Jane Doe']);
$book->author()->save($newAuthor);
```

### MySQL Relations

If you're using a hybrid Couchbase and SQL setup, you're in luck! The model will automatically return a Couchbase- or SQL-relation based on the type of the related model. Of course, if you want this functionality to work both ways, your SQL-models will need use the `Mpociot\Couchbase\Eloquent\HybridRelations` trait. Note that this functionality only works for hasOne, hasMany and belongsTo relations.

Example SQL-based User model:

```php
use Mpociot\Couchbase\Eloquent\HybridRelations;

class User extends Eloquent {

    use HybridRelations;

    protected $connection = 'mysql';

    public function messages()
    {
        return $this->hasMany('Message');
    }

}
```

And the Couchbase-based Message model:

```php
use Mpociot\Couchbase\Eloquent\Model as Eloquent;

class Message extends Eloquent {

    protected $connection = 'couchbase';

    public function user()
    {
        return $this->belongsTo('User');
    }

}
```

### Query Caching

You may easily cache the results of a query using the remember method:

```php
$users = User::remember(10)->get();
```

*From: http://laravel.com/docs/queries#caching-queries*

### Query Logging

By default, Laravel keeps a log in memory of all queries that have been run for the current request. However, in some cases, such as when inserting a large number of rows, this can cause the application to use excess memory. To disable the log, you may use the `disableQueryLog` method:

```php
DB::connection()->disableQueryLog();
```

*From: http://laravel.com/docs/database#query-logging*
