EloquentZF2
============

Eloquent ORM module for Zend Framework 2.

## Features
- `Eloquent Model` and `Capsule` suppot out of the box.
- `Validators` for forms: `RecordExists` and `NoRecordExists` (equivalent of
`Zend\Validator\Db\RecordExists` and `Zend\Validator\Db\noRecordExists`).
- `Authentication` adapters (`Abstract` implementation supporting
  AuthenticationService and two implementations `CallbackCheckAdapter` and
  `CredentialTreatmentAdapter`).

## Planned Features
- Multi-connection configuration support.
- Caching support.
- Paginator support.
- Logger support.
- Session Handlers (not sure if needed yet).
- Migration support (via ZFTool).

## Install

`$ composer require edvinasme/eloquent-zf2:dev-master`

## Setup
* Add `EloquentZF2` to the `modules` array in `config/application.config.php`
* You can now retrieve EloquentZF2 via SM: `$sm->get('EloquentZF2')` This gives
  you access to Eloquent Query Builder, Schema Builder etc. (see
  http://laravel.com/docs/database for more information)
* Copy `vendor/edvinasme/eloquent-zf2/config/database.eloquent.config.php.dist`
to `config/autoload/database.eloquent.config.php` and add database credentials.

## Models
Your models should extend `Illuminate\Database\Eloquent\Model` for example:

```php
    <?php
    namespace Album\Model;

    // skipping some code here

    use Illuminate\Database\Eloquent\Model as EloquentZF2Model;

    class Album extends EloquentZF2Model implements InputFilterAwareInterface
    {
```

## Validators
When populating InputFilter (usually in Model's getInputFilter() method), you
can use `EloquentZF2\Validator\RecordExists` or `EloquentZF2\Validator\NoRecordExists`
validators which will check records existance in the database and validate the
field accordingly.

The following option keys are supported:
* `table`      => The database table to validate against
* `schema`     => The schema (database) to check for a match
* `field`      => The field to check for a match
* `exclude`    => An optional where clause or field/value pair to exclude from the query
* `connection` => An optional database connection name to use

An example below checks table `users`, field `login` with form input's value
while excluding records where `login = test@example.org`:

```php
$inputFilter->add($factory->createInput(array(
              'name'     => 'email    ',
                'required' => true,
                'validators' => array(
                    array(
                        'name'    => 'EloquentZF2\Validator\noRecordExists',
                        'options' => array(
                            'table' => 'users',
                            'field' => 'login',
                            'exclude' => array(
                                'field' => 'login',
                                'value' => 'text@example.org',
                                ),
                            ),
                        ),
                    ),
                )
            )
        );
```

## Authentication

To help you implement Authentication with EloquentZF2 in a similar way as with
ZendDB, there is an Abstract Authentication Adapter provided
(`Authentication\\Adapter\EloquentDb.php`) and two adapters implemented:
* `Authentication\Adapter\CallbackCheckAdapter.php` - lets you supply a custom
  callback function. This is useful when you want to check credentials with
  e.g. bcrypt. Defaults to simple ($a == $b) comparison if callback function
  is not provided. See example below.
* `Authentication\Adapter\CredentialTreatmentAdapter.php` - lets you supply SQL
  statement, function or routine (e.g. MD5(?), PASSWORD(?) etc.) which should be
  applied to given gredential before checking it. Defauls to '?' which would be
  same as (`passwordField` = `password`) comparison. See example below.

Both of these Adapters are implemented in a very similar way as Zend's
Authentication adapters and they are compatible with Authentication Service.
Please refer to [Zend's Authentication Service
Documentation](http://zf2.readthedocs.org/en/latest/modules/zend.authentication.intro.html#zend-authentication-introduction-persistencel) for more information.

### CallbackCheckAdapter.php Example
This is example using Bcrypt (Zend's implementation). This would usually go in
your controller's login action:
```php
/* SomeController.php */

// use CallbackCheckAdapter as AuthAdapter
use EloquentZF2\Authentication\Adapter\CallbackCheckAdapter as AuthAdapter;

// ... controller code skipped ...

public function loginAction() {

    // ... skipping validation and form code ...

    // define custom callback function (bcrypt)
    $callback = function($a, $b) {
        $bcrypt = new \Zend\Crypt\Password\Bcrypt(array('cost' => '14'));
        return $bcrypt->verify($b, $a);
    };

    // init auth adapter
    $authAdapter = new AuthAdapter('default', 'users', 'login', 'password', $callback);

    // set auth credentials (assuming it was posted by form)
    $authAdapter
        ->setIdentity($request->getPost('login'))
        ->setCredential($request->getPost('password'));

    // authenticate
    $authResult = $authAdapter->authenticate();

```
### CredentialTreatmentAdapter.php Example
This is example using MD5(?). This would usually go in your controller's login
action:

```php
/* SomeController.php */

// use CallbackCheckAdapter as AuthAdapter
use EloquentZF2\Authentication\Adapter\CredentialTreatmentAdapter as AuthAdapter;

// ... controller code skipped ...

public function loginAction() {

    // ... skipping validation and form code ...
    $callback = 'MD5(?)';

    // init auth adapter
    $authAdapter = new AuthAdapter('default', 'users', 'login', 'password', $callback);

    // set auth credentials (assuming it was posted by from)
    $authAdapter
        ->setIdentity($request->getPost('login'))
        ->setCredential($request->getPost('password'));

    // authenticate
    $authResult = $authAdapter->authenticate();
}

```
