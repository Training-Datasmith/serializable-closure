<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Exceptions;

use Exception;
class Php_Version_Not_Supported_Exception extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $message
     */
    public function __construct($message = 'PHP 7.3 is not supported.')
    {
        parent::__construct($message);
    }
}