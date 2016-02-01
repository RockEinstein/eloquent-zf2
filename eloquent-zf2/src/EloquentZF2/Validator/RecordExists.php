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

use Zend\Validator\Exception;

/**
 * Confirms a record exists in a table.
 */
class RecordExists extends EloquentDb
{

    public function isValid($value)
    {
        /*
         * Check for an adapter being defined. If not, throw an exception.
         */

        $valid = true;
        $this->setValue($value);

        $result = $this->query($value);
        if (!$result) {
            $valid = false;
            $this->error(self::ERROR_NO_RECORD_FOUND);
        }

        return $valid;
    }
}
