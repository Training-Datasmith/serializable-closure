<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure;

use Closure;
use Laravel\Serializable_Closure\Exceptions\Invalid_Signature_Exception;
use Laravel\Serializable_Closure\Serializers\Signed;
use Laravel\Serializable_Closure\Signers\Hmac;
class Serializable_Closure
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
        $this->serializable = Serializers\Signed::$signer ? new Serializers\Signed($closure) : new Serializers\Native($closure);
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
     * Create a new unsigned serializable closure instance.
     */
    public static function unsigned(Closure $closure): \Laravel\Serializable_Closure\Unsigned_Serializable_Closure
    {
        return new Unsigned_Serializable_Closure($closure);
    }
    /**
     * Sets the serializable closure secret key.
     *
     * @param  string|null  $secret
     */
    public static function set_secret_key($secret): void
    {
        Serializers\Signed::$signer = $secret ? new Hmac($secret) : null;
    }
    /**
     * Sets the serializable closure secret key.
     *
     * @param  \Closure|null  $transformer
     */
    public static function transform_use_variables_using($transformer): void
    {
        Serializers\Native::$transform_use_variables = $transformer;
    }
    /**
     * Sets the serializable closure secret key.
     *
     * @param  \Closure|null  $resolver
     */
    public static function resolve_use_variables_using($resolver): void
    {
        Serializers\Native::$resolve_use_variables = $resolver;
    }
    /**
     * Get the serializable representation of the closure.
     *
     * @return array{serializable: \Laravel\SerializableClosure\Serializers\Signed|\Laravel\SerializableClosure\Contracts\Serializable}
     */
    public function __serialize()
    {
        return ['serializable' => $this->serializable];
    }
    /**
     * Restore the closure after serialization.
     *
     * @param  array{serializable: \Laravel\SerializableClosure\Serializers\Signed|\Laravel\SerializableClosure\Contracts\Serializable}  $data
     * @return void
     *
     * @throws \Laravel\SerializableClosure\Exceptions\InvalidSignatureException
     */
    public function __unserialize(array $data)
    {
        if (Signed::$signer && !$data['serializable'] instanceof Signed) {
            throw new Invalid_Signature_Exception();
        }
        $this->serializable = $data['serializable'];
    }
}