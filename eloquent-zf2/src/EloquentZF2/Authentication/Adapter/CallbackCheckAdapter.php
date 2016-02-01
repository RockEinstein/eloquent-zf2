<?php
/**
 * EloquentZF2 (https://github.com/RockEinstein/Eloquent-ZF2)
 * Eloquent ORM Module for Zend Framework 2 which integrates Illuminate\Database
 * from Laravel Framework with ZF2.
 *
 * @link      https://github.com/RockEinstein/Eloquent-ZF2
 * @copyright Copyright (c) 2014 Edvinas Klovas
 * @license   http://opensource.org/licenses/MIT MIT License
 * @author    Edvinas Klovas <edvinas@pnd.io> 2014
 * @author    Anderson Luciano <andersonlucianodev@gmail.com> 2016
 */

namespace EloquentZF2\Authentication\Adapter;

use Illuminate\Database\Capsule\Manager as Capsule;
use Zend\Authentication\Result as AuthenticationResult;

/**
 * CallbackCheckAdapter handles special case when user wants to supply a custom
 * callback function. This is useful when you want to check credentials with
 * e.g. bcrypt. It defaults to simple ($a == $b) comparison if callback function
 * is not provided.
 *
 * @uses EloquentZF2\Authentication\Adapter\EloquentDb
 */
class CallbackCheckAdapter extends EloquentDb
{
    /**
     * $credentialValidationCallback - This overrides the Treatment usage to provide a callback
     * that allows for validation to happen in code
     *
     * @var callable
     */
    protected $credentialValidationCallback = null;

    /**
     * __construct() - Sets configuration options
     *
     * @param string   $connection                   Optional
     * @param string   $tableName                    Optional
     * @param string   $identityColumn               Optional
     * @param string   $credentialColumn             Optional
     * @param callable $credentialValidationCallback Optional
     */
    public function __construct(
        $connection = 'default',
        $tableName = null,
        $identityColumn = null,
        $credentialColumn = null,
        $credentialValidationCallback = null
    ) {
        parent::__construct($connection, $tableName, $identityColumn, $credentialColumn);

        if (null !== $credentialValidationCallback) {
            $this->setCredentialValidationCallback($credentialValidationCallback);
        } else {
            $this->setCredentialValidationCallback(function ($a, $b) {
                return $a === $b;
            });
        }
    }

    /**
     * setCredentialValidationCallback() - allows the developer to use a callback as a way of checking the
     * credential.
     *
     * @param callable $validationCallback
     * @return DbTable
     * @throws Exception\InvalidArgumentException
     */
    public function setCredentialValidationCallback($validationCallback)
    {
        if (!is_callable($validationCallback)) {
            throw new Exception\InvalidArgumentException('Invalid callback provided');
        }
        $this->credentialValidationCallback = $validationCallback;
        return $this;
    }

    /**
     * _authenticateQuery() - This method performs an authentication query.
     *
     * @return Sql\Select
     */
    protected function authenticateQuery()
    {
        // get select
        $dbQuery = clone $this->getDbSelect();
        $results = $dbQuery->table($this->tableName)
            ->select('*')
            ->where($this->identityColumn, $this->identity)->get();

        return $results;
    }

    /**
     * _authenticateValidateResult() - This method attempts to validate that
     * the record in the resultset is indeed a record that matched the
     * identity provided to this adapter.
     *
     * @param  array $resultIdentity
     * @return AuthenticationResult
     */
    protected function authenticateValidateResult($resultIdentity)
    {
        try {
            $callbackResult = call_user_func($this->credentialValidationCallback, $resultIdentity[$this->credentialColumn], $this->credential);
        } catch (\Exception $e) {
            $this->authenticateResultInfo['code']       = AuthenticationResult::FAILURE_UNCATEGORIZED;
            $this->authenticateResultInfo['messages'][] = $e->getMessage();
            return $this->authenticateCreateAuthResult();
        }
        if ($callbackResult !== true) {
            $this->authenticateResultInfo['code']       = AuthenticationResult::FAILURE_CREDENTIAL_INVALID;
            $this->authenticateResultInfo['messages'][] = 'Supplied credential is invalid.';
            return $this->authenticateCreateAuthResult();
        }

        $this->resultRow = $resultIdentity;

        $this->authenticateResultInfo['code']       = AuthenticationResult::SUCCESS;
        $this->authenticateResultInfo['messages'][] = 'Authentication successful.';
        return $this->authenticateCreateAuthResult();
    }
}
