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

namespace EloquentZF2\Validator;

use Traversable;
use Illuminate\Database\Capsule\Manager as Capsule;
use Zend\Stdlib\ArrayUtils;
use Zend\Validator\AbstractValidator;
use Zend\Validator\Exception;

/**
 * Class for Database record validation using Eloquent ORM
 */
abstract class EloquentDb extends AbstractValidator
{
    /**
     * Error constants
     */
    const ERROR_NO_RECORD_FOUND = 'noRecordFound';
    const ERROR_RECORD_FOUND    = 'recordFound';

    /**
     * Validator message templates
     * @var array $messageTemplates
     */
    protected $messageTemplates = array(
        self::ERROR_NO_RECORD_FOUND => "No record matching the input was found",
        self::ERROR_RECORD_FOUND    => "A record matching the input was found",
    );

    /**
     * The schema (database) to check in
     * @var string $schema
     */
    protected $schema = null;

    /**
     * The database table to validate against
     * @var string $table
     */
    protected $table = '';

    /**
     * The field (column) to check for a match
     * @var string $field
     */
    protected $field = '';

    /**
     * An optional where clause or field/value pair to exclude from the query
     * @var mixed $exclude
     */
    protected $exclude = null;

    /**
     * Connection name to use. Defaults to 'default'
     *
     * @var string $connection
     */
    protected $connection = 'default';


    /**
     * Provides basic configuration similar to Zend\Validator\Db Validators.
     * Setting $exclude allows a single record to be excluded from matching.
     * Exclude can either be a String containing a where clause, or an array
     * with `field` and `value` keys to define the where clause added to the
     * sql. A connection name may optionally be supplied to avoid using the
     * default database connection defined in database.eloquent.config.php
     *
     * The following option keys are supported:
     * 'table'      => The database table to validate against
     * 'schema'     => The schema (database) to check for a match
     * 'field'      => The field to check for a match
     * 'exclude'    => An optional where clause or field/value pair to exclude from the query
     * 'connection' => An optional database connection name to use
     *
     * @param array|Traversable $options Options to use for this validator
     * @throws \Zend\Validator\Exception\InvalidArgumentException
     */
    public function __construct($options = null)
    {
        parent::__construct($options);

        if ($options instanceof Traversable) {
            $options = ArrayUtils::iteratorToArray($options);
        } elseif (func_num_args() > 1) {
            $options       = func_get_args();
            $firstArgument = array_shift($options);
            if (is_array($firstArgument)) {
                $temp = ArrayUtils::iteratorToArray($firstArgument);
            } else {
                $temp['table'] = $firstArgument;
            }

            $temp['field'] = array_shift($options);

            if (!empty($options)) {
                $temp['exclude'] = array_shift($options);
            }

            if (!empty($options)) {
                $temp['connection'] = array_shift($options);
            }

            $options = $temp;
        }

        if (!array_key_exists('table', $options) && !array_key_exists('schema', $options)) {
            throw new Exception\InvalidArgumentException('Table or Schema option missing!');
        }

        if (!array_key_exists('field', $options)) {
            throw new Exception\InvalidArgumentException('Field option missing!');
        }

        if (array_key_exists('connection', $options)) {
            $this->adapter = $options['connection'];
        }

        if (array_key_exists('exclude', $options)) {
            $this->exclude = $options['exclude'];
        }

        $this->field = $options['field'];
        if (array_key_exists('table', $options)) {
            $this->table = $options['table'];
        }

        if (array_key_exists('schema', $options)) {
            $this->schema = $options['schema'];
        }
    }

    /**
     * query and return matches or null if no matches are found
     *
     * @param string $value value
     * @return int number of found results
     */
    protected function query($value)
    {
        $results = Capsule::connection($this->connection ?: 'default')
            ->table($this->table)
            ->where($this->field, $value);
        if (isset($this->exclude['field'])) {
            $results = $results
                ->where($this->exclude['field'], '<>', $this->exclude['value']);
        }

        return $results->count();
    }
}
