<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Contracts;

interface Serializable
{
    /**
     * Resolve the closure with the given arguments.
     *
     * @return mixed
     */
    public function __invoke();
    /**
     * Gets the closure that got serialized/unserialized.
     *
     * @return \Closure
     */
    public function get_closure();
}