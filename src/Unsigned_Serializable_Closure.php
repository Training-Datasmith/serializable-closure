<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure;

use Closure;
class Unsigned_Serializable_Closure
{
    /**
     * The closure's serializable.
     *
     * @var \Laravel\SerializableClosure\Contracts\Serializable
     */
    protected $serializable;
    /**
     * Creates a new serializable closure instance.
     */
    public function __construct(Closure $closure)
    {
        $this->serializable = new Serializers\Native($closure);
    }
    /**
     * Resolve the closure with the given arguments.
     */
    public function __invoke(): mixed
    {
        return call_user_func_array($this->serializable, func_get_args());
    }
    /**
     * Gets the closure.
     *
     * @return \Closure
     */
    public function get_closure()
    {
        return $this->serializable->get_closure();
    }
    /**
     * Get the serializable representation of the closure.
     *
     * @return array{serializable: \Laravel\SerializableClosure\Contracts\Serializable}
     */
    public function __serialize()
    {
        return ['serializable' => $this->serializable];
    }
    /**
     * Restore the closure after serialization.
     *
     * @param  array{serializable: \Laravel\SerializableClosure\Contracts\Serializable}  $data
     * @return void
     */
    public function __unserialize(array $data)
    {
        $this->serializable = $data['serializable'];
    }
}