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
use Zend\Crypt\Password\Bcrypt;

/**
 * CredentialTreatmentAdapter handles special SQL treament (such as MD5(?),
 * PASSWORD(?) or any custom routine) which should be applied to given
 * credential before checking it
 *
 * @uses EloquentZF2\Authentication\Adapter\EloquentDb
 */
class CredentialTreatmentAdapter extends EloquentDb
{
    /**
     * $credentialTreatment - Treatment applied to the credential, such as MD5(?) or PASSWORD(?)
     *
     * @var string
     */
    protected $credentialTreatment = null;

    /**
     * __construct() - Sets configuration options
     *
     * @param string $connection          Optional
     * @param string $tableName           Optional
     * @param string $identityColumn      Optional
     * @param string $credentialColumn    Optional
     * @param string $credentialTreatment Optional
     */
    public function __construct(
        $connection = 'default',
        $tableName = null,
        $identityColumn = null,
        $credentialColumn = null,
        $credentialTreatment = null
    ) {
        parent::__construct($connection, $tableName, $identityColumn, $credentialColumn);

        if (null !== $credentialTreatment) {
            $this->setCredentialTreatment($credentialTreatment);
        }
    }

    /**
     * setCredentialTreatment() - allows the developer to pass a parametrized string that is
     * used to transform or treat the input credential data.
     *
     * In many cases, passwords and other sensitive data are encrypted, hashed, encoded,
     * obscured, or otherwise treated through some function or algorithm. By specifying a
     * parametrized treatment string with this method, a developer may apply arbitrary SQL
     * upon input credential data.
     *
     * Examples:
     *
     *  'PASSWORD(?)'
     *  'MD5(?)'
     *
     * @param  string $treatment
     * @return DbTable Provides a fluent interface
     */
    public function setCredentialTreatment($treatment)
    {
        $this->credentialTreatment = $treatment;
        return $this;
    }

    /**
     * _authenticateQuery() - This method performs an authentication query.
     *
     * @return result
     */
    protected function authenticateQuery()
    {
        // build credential expression
        if (empty($this->credentialTreatment) || (strpos($this->credentialTreatment, '?') === false)) {
            $this->credentialTreatment = '?';
        }


        $credentialExpression = str_replace(
            '?',
            "'{$this->credential}'",
            $this->credentialTreatment);

        // get select
        $dbQuery = clone $this->getDbSelect();
        $results = $dbQuery->connection($this->connection)
            ->table($this->tableName)
            ->selectRaw(
                    "*, (CASE
                        WHEN `{$this->credentialColumn}` = {$credentialExpression}
                        THEN 1
                        ELSE 0
                    END) AS `zend_auth_credential_match`")
                //array(
                //$this->credentialColumn,
                //$this->credential,
                //'zend_auth_credential_match'
                //)
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
        if ($resultIdentity['zend_auth_credential_match'] != '1') {
            $this->authenticateResultInfo['code']       = AuthenticationResult::FAILURE_CREDENTIAL_INVALID;
            $thie->authenticateResultInfo['messages'][] = 'Supplied credential is invalid.';
            return $this->authenticateCreateAuthResult();
        }

        unset($resultIdentity['zend_auth_credential_match']);
        $this->resultRow = $resultIdentity;

        $this->authenticateResultInfo['code']       = AuthenticationResult::SUCCESS;
        $this->authenticateResultInfo['messages'][] = 'Authentication successful.';
        return $this->authenticateCreateAuthResult();
    }
}
