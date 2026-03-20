<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Exceptions;

use Exception;
class Missing_Secret_Key_Exception extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $message
     */
    public function __construct($message = 'No serializable closure secret key has been specified.')
    {
        parent::__construct($message);
    }
}