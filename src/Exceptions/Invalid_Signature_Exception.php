<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Exceptions;

use Exception;
class Invalid_Signature_Exception extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param  string  $message
     */
    public function __construct($message = 'Your serialized closure might have been modified or it\'s unsafe to be unserialized.')
    {
        parent::__construct($message);
    }
}