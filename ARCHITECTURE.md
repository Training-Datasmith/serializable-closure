# Architecture: serializable-closure

## Purpose

A Laravel/PHP library that enables PHP closures to be serialised, stored (e.g., in a queue payload), and later deserialised and executed — bridging the gap that PHP's native closure serialisation does not support.

## Directory Structure

```
src/
  Serializable_Closure.php            - Primary wrapper; serialises closures with optional HMAC signing
  Unsigned_Serializable_Closure.php   - Wrapper without signing (faster, no secret key required)
  Contracts/
    Serializable.php                  - Contract for serialisable closures
    Signer.php                        - Contract for signing implementations
  Serializers/
    Native.php                        - Serialiser using ReflectionClosure to extract source code
    Signed.php                        - Adds HMAC signature wrapper around the Native serialiser
  Signers/
    Hmac.php                          - HMAC-SHA256 signer using a configurable secret key
  Support/
    Closure_Scope.php                 - Captures the closure's `$this` binding and use()-d variables
    Closure_Stream.php                - PHP stream wrapper that serves closure source code for eval()
    Reflection_Closure.php            - ReflectionFunction extension: extracts source, use-variables, etc.
    Self_Reference.php                - Handles `$this` self-reference in recursive closures
  Exceptions/
    Invalid_Signature_Exception.php   - Thrown when HMAC verification fails on deserialisation
    Missing_Secret_Key_Exception.php  - Thrown when signing is enabled but no key is configured
    Php_Version_Not_Supported_Exception.php
```

## Key Design Decisions

- **Source-code extraction**: `Reflection_Closure` reads the closure's source from the PHP file using `ReflectionFunction::getFileName()/getStartLine()/getEndLine()`, then serialises the source text — not a compiled bytecode form.
- **HMAC signing**: `Signed` wraps the serialised payload with an HMAC-SHA256 signature to detect tampering (important when closures are stored in untrusted locations like Redis or a message queue).
- **Use-variable capture**: `Closure_Scope` inspects `use (&$var)` and `use ($var)` bindings via reflection to include captured variable values in the serialised payload.
- **eval() on deserialisation**: Reconstructed closures are created via `eval()` from the stored source code — this is the fundamental trade-off for portability.

## Extension Points

- Implement `Signer` to use a different signing algorithm (e.g., Ed25519 asymmetric signing).
- Set a global secret key via `SerializableClosure::setSecretKey()` to enable signing app-wide.

## Dependency Flow

```
SerializableClosure (wraps a \Closure)
  └─> serialize() → Signed::serialize()
        └─> Native::serialize()
              └─> Reflection_Closure — extracts source + use-variables
              └─> Closure_Scope — captures $this binding
        └─> Hmac::sign()  — HMAC-SHA256 over serialised payload
  └─> unserialize() → Signed::unserialize()
        └─> Hmac::verify() — validates signature
        └─> Closure_Stream — provides source to eval()
        └─> reconstructed \Closure bound to original scope
```
