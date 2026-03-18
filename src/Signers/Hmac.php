<?php

namespace Laravel\SerializableClosure\Signers;

use Laravel\SerializableClosure\Contracts\Signer;

class Hmac implements Signer
{
    /**
     * Creates a new signer instance.
     *
     * @param  string  $secret
     */
    public function __construct(
        /**
         * The secret key.
         */
        protected $secret
    )
    {
    }

    /**
     * Sign the given serializable.
     *
     * @param  string  $serialized
     */
    public function sign($serialized): array
    {
        return [
            'serializable' => $serialized,
            'hash' => base64_encode(hash_hmac('sha256', $serialized, $this->secret, true)),
        ];
    }

    /**
     * Verify the given signature.
     *
     * @param  array{serializable: string, hash: string}  $signature
     */
    public function verify($signature): bool
    {
        return hash_equals(base64_encode(
            hash_hmac('sha256', $signature['serializable'], $this->secret, true)
        ), $signature['hash']);
    }
}
