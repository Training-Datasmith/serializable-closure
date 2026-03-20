<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Support;

use Spl_Object_Storage;
class Closure_Scope extends Spl_Object_Storage
{
    /**
     * The number of serializations in current scope.
     *
     * @var int
     */
    public $serializations = 0;
    /**
     * The number of closures that have to be serialized.
     *
     * @var int
     */
    public $to_serialize = 0;
}