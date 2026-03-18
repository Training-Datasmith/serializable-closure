<?php

namespace Laravel\SerializableClosure\Serializers;

use Laravel\SerializableClosure\Contracts\Serializable;
use Laravel\SerializableClosure\Exceptions\InvalidSignatureException;
use Laravel\SerializableClosure\Exceptions\MissingSecretKeyException;

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
    public function getClosure()
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
        if (! static::$signer) {
            throw new MissingSecretKeyException();
        }

        return static::$signer->sign(
            serialize(new Native($this->closure))
        );
    }

    /**
     * Restore the closure after serialization.
     *
     * @param  array{serializable: string, hash: string}  $signature
     * @return void
     *
     * @throws \Laravel\SerializableClosure\Exceptions\InvalidSignatureException
     */
    public function __unserialize(array $signature)
    {
        if (static::$signer && ! static::$signer->verify($signature)) {
            throw new InvalidSignatureException();
        }

        /** @var \Laravel\SerializableClosure\Contracts\Serializable $serializable */
        $serializable = unserialize($signature['serializable']);

        $this->closure = $serializable->getClosure();
    }
}
