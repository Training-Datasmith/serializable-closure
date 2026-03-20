<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Serializers;

use Laravel\Serializable_Closure\Contracts\Serializable;
use Laravel\Serializable_Closure\Exceptions\Invalid_Signature_Exception;
use Laravel\Serializable_Closure\Exceptions\Missing_Secret_Key_Exception;
class Signed implements Serializable
{
    /**
     * The signer that will sign and verify the closure's signature.
     *
     * @var \Laravel\SerializableClosure\Contracts\Signer|null
     */
    public static $signer;
    /**
     * Creates a new serializable closure instance.
     *
     * @param  \Closure  $closure
     */
    public function __construct(
        /**
         * The closure to be serialized/unserialized.
         */
        protected $closure
    )
    {
    }
    /**
     * Resolve the closure with the given arguments.
     */
    public function __invoke(): mixed
    {
        return call_user_func_array($this->closure, func_get_args());
    }
    /**
     * Gets the closure.
     *
     * @return \Closure
     */
    public function get_closure()
    {
        return $this->closure;
    }
    /**
     * Get the serializable representation of the closure.
     *
     * @return array
     */
    public function __serialize()
    {
        if (!static::$signer) {
            throw new Missing_Secret_Key_Exception();
        }
        return static::$signer->sign(serialize(new Native($this->closure)));
    }
    /**
     * Restore the closure after serialization.
     *
     * @param  array{serializable: string, hash: string}  $signature
     * @return void
     *
     * @throws \Laravel\SerializableClosure\Exceptions\InvalidSignatureException
     * @throws \Laravel\SerializableClosure\Exceptions\MissingSecretKeyException
     */
    public function __unserialize(array $signature)
    {
        if (!static::$signer) {
            // No signer is configured. Calling unserialize() on an unsigned payload is a PHP
            // object injection risk — deserializing untrusted data can trigger arbitrary gadget
            // chains. Refuse to proceed rather than silently skip signature verification.
            throw new Missing_Secret_Key_Exception();
        }
        if (!static::$signer->verify($signature)) {
            throw new Invalid_Signature_Exception();
        }
        /** @var \Laravel\SerializableClosure\Contracts\Serializable $serializable */
        $serializable = unserialize($signature['serializable']);
        $this->closure = $serializable->get_closure();
    }
}