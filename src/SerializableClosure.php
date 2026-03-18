<?php

namespace Laravel\SerializableClosure;

use Closure;
use Laravel\SerializableClosure\Exceptions\InvalidSignatureException;
use Laravel\SerializableClosure\Serializers\Signed;
use Laravel\SerializableClosure\Signers\Hmac;

class SerializableClosure
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
        $this->serializable = Serializers\Signed::$signer
            ? new Serializers\Signed($closure)
            : new Serializers\Native($closure);
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
    public function getClosure()
    {
        return $this->serializable->getClosure();
    }

    /**
     * Create a new unsigned serializable closure instance.
     */
    public static function unsigned(Closure $closure): \Laravel\SerializableClosure\UnsignedSerializableClosure
    {
        return new UnsignedSerializableClosure($closure);
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param  string|null  $secret
     */
    public static function setSecretKey($secret): void
    {
        Serializers\Signed::$signer = $secret
            ? new Hmac($secret)
            : null;
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param  \Closure|null  $transformer
     */
    public static function transformUseVariablesUsing($transformer): void
    {
        Serializers\Native::$transformUseVariables = $transformer;
    }

    /**
     * Sets the serializable closure secret key.
     *
     * @param  \Closure|null  $resolver
     */
    public static function resolveUseVariablesUsing($resolver): void
    {
        Serializers\Native::$resolveUseVariables = $resolver;
    }

    /**
     * Get the serializable representation of the closure.
     *
     * @return array{serializable: \Laravel\SerializableClosure\Serializers\Signed|\Laravel\SerializableClosure\Contracts\Serializable}
     */
    public function __serialize()
    {
        return [
            'serializable' => $this->serializable,
        ];
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
        if (Signed::$signer && ! $data['serializable'] instanceof Signed) {
            throw new InvalidSignatureException();
        }

        $this->serializable = $data['serializable'];
    }
}
