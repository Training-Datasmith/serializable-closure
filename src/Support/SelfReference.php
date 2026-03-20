<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Support;

class Self_Reference
{
    /**
     * Creates a new self reference instance.
     *
     * @param  string  $hash
     */
    public function __construct(
        /**
         * The unique hash representing the object.
         */
        public $hash
    )
    {
    }
}