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

use stdClass;
use Illuminate\Database\Capsule\Manager as Capsule;
use Zend\Authentication\Result as AuthenticationResult;
use Zend\Authentication\Adapter\AbstractAdapter;

abstract class EloquentDb extends AbstractAdapter
{
    /**
     * Database Connection
     *
     * @var DbAdapter
     */
    protected $connection = null;

    /**
     * @var Sql\Select
     */
    protected $dbSelect = null;
    /**
     * $tableName - the table name to check
     *
     * @var string
     */
    protected $tableName = null;

    /**
     * $identityColumn - the column to use as the identity
     *
     * @var string
     */
    protected $identityColumn = null;

    /**
     * $credentialColumns - columns to be used as the credentials
     *
     * @var string
     */
    protected $credentialColumn = null;

    /**
     * $authenticateResultInfo
     *
     * @var array
     */
    protected $authenticateResultInfo = null;

    /**
     * $resultRow - Results of database authentication query
     *
     * @var array
     */
    protected $resultRow = null;

    /**
     * $ambiguityIdentity - Flag to indicate same Identity can be used with
     * different credentials. Default is FALSE and need to be set to true to
     * allow ambiguity usage.
     *
     * @var bool
     */
    protected $ambiguityIdentity = false;

    /**
     * __construct() - Sets configuration options
     *
     * @param string $connection          Optional
     * @param string $tableName           Optional
     * @param string $identityColumn      Optional
     * @param string $credentialColumn    Optional
     */
    public function __construct(
        $connection = 'default',
        $tableName = null,
        $identityColumn = null,
        $credentialColumn = null
    ) {
        $this->connection = $connection;

        if (null !== $tableName) {
            $this->setTableName($tableName);
        }

        if (null !== $identityColumn) {
            $this->setIdentityColumn($identityColumn);
        }

        if (null !== $credentialColumn) {
            $this->setCredentialColumn($credentialColumn);
        }
    }

    /**
     * setTableName() - set the table name to be used in the select query
     *
     * @param  string $tableName
     * @return DbTable Provides a fluent interface
     */
    public function setTableName($tableName)
    {
        $this->tableName = $tableName;
        return $this;
    }

    /**
     * setIdentityColumn() - set the column name to be used as the identity column
     *
     * @param  string $identityColumn
     * @return DbTable Provides a fluent interface
     */
    public function setIdentityColumn($identityColumn)
    {
        $this->identityColumn = $identityColumn;
        return $this;
    }

    /**
     * setCredentialColumn() - set the column name to be used as the credential column
     *
     * @param  string $credentialColumn
     * @return DbTable Provides a fluent interface
     */
    public function setCredentialColumn($credentialColumn)
    {
        $this->credentialColumn = $credentialColumn;
        return $this;
    }

    /**
     * setAmbiguityIdentity() - sets a flag for usage of identical identities
     * with unique credentials. It accepts integers (0, 1) or boolean (true,
     * false) parameters. Default is false.
     *
     * @param  int|bool $flag
     * @return DbTable Provides a fluent interface
     */
    public function setAmbiguityIdentity($flag)
    {
        if (is_int($flag)) {
            $this->ambiguityIdentity = (1 === $flag ? true : false);
        } elseif (is_bool($flag)) {
            $this->ambiguityIdentity = $flag;
        }
        return $this;
    }

    /**
     * getAmbiguityIdentity() - returns TRUE for usage of multiple identical
     * identities with different credentials, FALSE if not used.
     *
     * @return bool
     */
    public function getAmbiguityIdentity()
    {
        return $this->ambiguityIdentity;
    }

    /**
     * getDbSelect() - Return the preauthentication Db Select object for userland select query modification
     *
     * @return Illuminate\Database\Capsule\Manager
     */
    public function getDbSelect()
    {
        if ($this->dbSelect == null) {
            $this->dbSelect = new Capsule();
        }
        return $this->dbSelect;
    }

    /**
     * getResultRowObject() - Returns the result row as a stdClass object
     *
     * @param  string|array $returnColumns
     * @param  string|array $omitColumns
     * @return stdClass|bool
     */
    public function getResultRowObject($returnColumns = null, $omitColumns = null)
    {
        if (!$this->resultRow) {
            return false;
        }

        $returnObject = new stdClass();

        if (null !== $returnColumns) {

            $availableColumns = array_keys($this->resultRow);
            foreach ((array) $returnColumns as $returnColumn) {
                if (in_array($returnColumn, $availableColumns)) {
                    $returnObject->{$returnColumn} = $this->resultRow[$returnColumn];
                }
            }
            return $returnObject;

        } elseif (null !== $omitColumns) {

            $omitColumns = (array) $omitColumns;
            foreach ($this->resultRow as $resultColumn => $resultValue) {
                if (!in_array($resultColumn, $omitColumns)) {
                    $returnObject->{$resultColumn} = $resultValue;
                }
            }
            return $returnObject;

        }

        foreach ($this->resultRow as $resultColumn => $resultValue) {
            $returnObject->{$resultColumn} = $resultValue;
        }
        return $returnObject;
    }

    /**
     * This method is called to attempt an authentication. Previous to this
     * call, this adapter would have already been configured with all
     * necessary information to successfully connect to a database table and
     * attempt to find a record matching the provided identity.
     *
     * @throws Exception\RuntimeException if answering the authentication query is impossible
     * @return AuthenticationResult
     */
    public function authenticate()
    {
        $this->authenticateSetup();
        $resultIdentities = $this->authenticateQuery();
        if (($authResult = $this->authenticateValidateResultSet($resultIdentities)) instanceof AuthenticationResult) {
            return $authResult;
        }

        // At this point, ambiguity is already done. Loop, check and break on success.
        foreach ($resultIdentities as $identity) {
            $authResult = $this->authenticateValidateResult($identity);
            if ($authResult->isValid()) {
                break;
            }
        }

        return $authResult;
    }

    /**
     * _authenticateValidateResult() - This method attempts to validate that
     * the record in the resultset is indeed a record that matched the
     * identity provided to this adapter.
     *
     * @param  array $resultIdentity
     * @return AuthenticationResult
     */
    abstract protected function authenticateValidateResult($resultIdentity);

    /**
     * _authenticateQuery() - This method performs authentication query.
     *
     * @return result
     */
    abstract protected function authenticateQuery();

    /**
     * _authenticateSetup() - This method abstracts the steps involved with
     * making sure that this adapter was indeed setup properly with all
     * required pieces of information.
     *
     * @throws Exception\RuntimeException in the event that setup was not done properly
     * @return bool
     */
    protected function authenticateSetup()
    {
        $exception = null;

        if ($this->tableName == '') {
            $exception = 'A table must be supplied for the DbTable authentication adapter.';
        } elseif ($this->identityColumn == '') {
            $exception = 'An identity column must be supplied for the DbTable authentication adapter.';
        } elseif ($this->credentialColumn == '') {
            $exception = 'A credential column must be supplied for the DbTable authentication adapter.';
        } elseif ($this->identity == '') {
            $exception = 'A value for the identity was not provided prior to authentication with DbTable.';
        } elseif ($this->credential === null) {
            $exception = 'A credential value was not provided prior to authentication with DbTable.';
        }

        if (null !== $exception) {
            throw new \Exception($exception);
        }

        $this->authenticateResultInfo = array(
            'code'     => AuthenticationResult::FAILURE,
            'identity' => $this->identity,
            'messages' => array()
        );

        return true;
    }


    /**
     * _authenticateValidateResultSet() - This method attempts to make
     * certain that only one record was returned in the resultset
     *
     * @param  array $resultIdentities
     * @return bool|\Zend\Authentication\Result
     */
    protected function authenticateValidateResultSet(array $resultIdentities)
    {

        if (count($resultIdentities) < 1) {
            $this->authenticateResultInfo['code']       = AuthenticationResult::FAILURE_IDENTITY_NOT_FOUND;
            $this->authenticateResultInfo['messages'][] = 'A record with the supplied identity could not be found.';
            return $this->authenticateCreateAuthResult();
        } elseif (count($resultIdentities) > 1 && false === $this->getAmbiguityIdentity()) {
            $this->authenticateResultInfo['code']       = AuthenticationResult::FAILURE_IDENTITY_AMBIGUOUS;
            $this->authenticateResultInfo['messages'][] = 'More than one record matches the supplied identity.';
            return $this->authenticateCreateAuthResult();
        }

        return true;
    }

    /**
     * Creates a Zend\Authentication\Result object from the information that
     * has been collected during the authenticate() attempt.
     *
     * @return AuthenticationResult
     */
    protected function authenticateCreateAuthResult()
    {
        return new AuthenticationResult(
            $this->authenticateResultInfo['code'],
            $this->authenticateResultInfo['identity'],
            $this->authenticateResultInfo['messages']
        );
    }
}
