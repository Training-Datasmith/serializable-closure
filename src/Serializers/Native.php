<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Serializers;

use Closure;
use DateTimeInterface;
use Laravel\Serializable_Closure\Contracts\Serializable;
use Laravel\Serializable_Closure\Serializable_Closure;
use Laravel\Serializable_Closure\Support\Closure_Scope;
use Laravel\Serializable_Closure\Support\Closure_Stream;
use Laravel\Serializable_Closure\Support\Reflection_Closure;
use Laravel\Serializable_Closure\Support\Self_Reference;
use Laravel\Serializable_Closure\Unsigned_Serializable_Closure;
use Reflection_Object;
use ReflectionProperty;
use Unit_Enum;
class Native implements Serializable
{
    /**
     * Transform the use variables before serialization.
     *
     * @var \Closure|null
     */
    public static $transform_use_variables;
    /**
     * Resolve the use variables after unserialization.
     *
     * @var \Closure|null
     */
    public static $resolve_use_variables;
    /**
     * The closure's reflection.
     *
     * @var \Laravel\SerializableClosure\Support\ReflectionClosure|null
     */
    protected $reflector;
    /**
     * The closure's code.
     *
     * @var array|null
     */
    protected $code;
    /**
     * The closure's reference.
     *
     * @var string
     */
    protected $reference;
    /**
     * The closure's scope.
     *
     * @var \Laravel\SerializableClosure\Support\ClosureScope|null
     */
    protected $scope;
    /**
     * The "key" that marks an array as recursive.
     */
    public const ARRAY_RECURSIVE_KEY = 'LARAVEL_SERIALIZABLE_RECURSIVE_KEY';
    /**
     * Creates a new serializable closure instance.
     */
    public function __construct(
        /**
         * The closure to be serialized/unserialized.
         */
        protected \Closure $closure
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
        if ($this->scope === null) {
            $this->scope = new Closure_Scope();
            $this->scope->to_serialize++;
        }
        $this->scope->serializations++;
        $scope = $object = null;
        $reflector = $this->get_reflector();
        if ($reflector->is_binding_required()) {
            $object = $reflector->get_closure_this();
            static::wrap_closures($object, $this->scope);
        }
        if ($scope = $reflector->get_closure_scope_class()) {
            $scope = $scope->name;
        }
        $this->reference = spl_object_hash($this->closure);
        $this->scope[$this->closure] = $this;
        $use = $reflector->get_use_variables();
        if (static::$transform_use_variables) {
            $use = call_user_func(static::$transform_use_variables, $reflector->get_use_variables());
        }
        $code = $reflector->get_code();
        $this->map_by_reference($use);
        $data = ['use' => $use, 'function' => $code, 'scope' => $scope, 'this' => $object, 'self' => $this->reference];
        if (!--$this->scope->serializations && !--$this->scope->to_serialize) {
            $this->scope = null;
        }
        return $data;
    }
    /**
     * Restore the closure after serialization.
     *
     * @param  array  $data
     * @return void
     */
    public function __unserialize($data)
    {
        Closure_Stream::register();
        $this->code = $data;
        unset($data);
        $this->code['objects'] = [];
        if ($this->code['use']) {
            $this->scope = new Closure_Scope();
            if (static::$resolve_use_variables) {
                $this->code['use'] = call_user_func(static::$resolve_use_variables, $this->code['use']);
            }
            $this->map_pointers($this->code['use']);
            extract($this->code['use'], EXTR_OVERWRITE | EXTR_REFS);
            $this->scope = null;
        }
        $this->closure = include Closure_Stream::STREAM_PROTO . '://' . $this->code['function'];
        if ($this->code['this'] === $this) {
            $this->code['this'] = null;
        }
        $this->closure = $this->closure->bind_to($this->code['this'], $this->code['scope']);
        if (!empty($this->code['objects'])) {
            foreach ($this->code['objects'] as $item) {
                $item['property']->set_value($item['instance'], $item['object']->get_closure());
            }
        }
        $this->code = $this->code['function'];
    }
    /**
     * Ensures the given closures are serializable.
     *
     * @param  mixed  $data
     * @param  \Laravel\SerializableClosure\Support\ClosureScope  $storage
     */
    public static function wrap_closures(&$data, $storage): void
    {
        if ($data instanceof Closure) {
            $data = new static($data);
        } elseif (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }
            $data[self::ARRAY_RECURSIVE_KEY] = true;
            foreach ($data as $key => &$value) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                }
                static::wrap_closures($value, $storage);
            }
            unset($value);
            unset($data[self::ARRAY_RECURSIVE_KEY]);
        } elseif ($data instanceof \stdClass) {
            if (isset($storage[$data])) {
                $data = $storage[$data];
                return;
            }
            $data = $storage[$data] = clone $data;
            foreach ($data as &$value) {
                static::wrap_closures($value, $storage);
            }
            unset($value);
        } elseif (is_object($data) && !$data instanceof static && !$data instanceof Unit_Enum) {
            if (isset($storage[$data])) {
                $data = $storage[$data];
                return;
            }
            $instance = $data;
            $reflection = new Reflection_Object($instance);
            if (!$reflection->is_user_defined() || $reflection->has_method('__serialize')) {
                $storage[$instance] = $data;
                return;
            }
            $storage[$instance] = $data = $reflection->new_instance_without_constructor();
            do {
                if (!$reflection->is_user_defined()) {
                    break;
                }
                foreach ($reflection->get_properties() as $property) {
                    if ($property->is_static()) {
                        continue;
                    }
                    if (!$property->get_declaring_class()->is_user_defined()) {
                        continue;
                    }
                    if (static::is_virtual_property($property)) {
                        continue;
                    }
                    if (!$property->is_initialized($instance)) {
                        continue;
                    }
                    $value = $property->get_value($instance);
                    if (is_array($value) || is_object($value)) {
                        static::wrap_closures($value, $storage);
                    }
                    $property->set_value($data, $value);
                }
            } while ($reflection = $reflection->get_parent_class());
        }
    }
    /**
     * Gets the closure's reflector.
     *
     * @return \Laravel\SerializableClosure\Support\ReflectionClosure
     */
    public function get_reflector()
    {
        if ($this->reflector === null) {
            $this->code = null;
            $this->reflector = new Reflection_Closure($this->closure);
        }
        return $this->reflector;
    }
    /**
     * Internal method used to map closure pointers.
     *
     * @param  mixed  $data
     * @return void
     */
    protected function map_pointers(&$data)
    {
        $scope = $this->scope;
        if ($data instanceof static) {
            $data =& $data->closure;
        } elseif (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }
            $data[self::ARRAY_RECURSIVE_KEY] = true;
            foreach ($data as $key => &$value) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                }
                if ($value instanceof static) {
                    $data[$key] =& $value->closure;
                } elseif ($value instanceof Self_Reference && $value->hash === $this->code['self']) {
                    $data[$key] =& $this->closure;
                } else {
                    $this->map_pointers($value);
                }
            }
            unset($value);
            unset($data[self::ARRAY_RECURSIVE_KEY]);
        } elseif ($data instanceof \stdClass) {
            if (isset($scope[$data])) {
                return;
            }
            $scope[$data] = true;
            foreach ($data as $key => &$value) {
                if ($value instanceof Self_Reference && $value->hash === $this->code['self']) {
                    $data->{$key} =& $this->closure;
                } elseif (is_array($value) || is_object($value)) {
                    $this->map_pointers($value);
                }
            }
            unset($value);
        } elseif (is_object($data) && !$data instanceof Closure) {
            if (isset($scope[$data])) {
                return;
            }
            $scope[$data] = true;
            $reflection = new Reflection_Object($data);
            do {
                if (!$reflection->is_user_defined()) {
                    break;
                }
                foreach ($reflection->get_properties() as $property) {
                    if ($property->is_static()) {
                        continue;
                    }
                    if (!$property->get_declaring_class()->is_user_defined()) {
                        continue;
                    }
                    if (static::is_virtual_property($property)) {
                        continue;
                    }
                    if (!$property->is_initialized($data)) {
                        continue;
                    }
                    if ($property->is_read_only()) {
                        continue;
                    }
                    $item = $property->get_value($data);
                    if ($item instanceof Serializable_Closure || $item instanceof Unsigned_Serializable_Closure || $item instanceof Self_Reference && $item->hash === $this->code['self']) {
                        $this->code['objects'][] = ['instance' => $data, 'property' => $property, 'object' => $item instanceof Self_Reference ? $this : $item];
                    } elseif (is_array($item) || is_object($item)) {
                        $this->map_pointers($item);
                        $property->set_value($data, $item);
                    }
                }
            } while ($reflection = $reflection->get_parent_class());
        }
    }
    /**
     * Internal method used to map closures by reference.
     *
     * @param  mixed  $data
     * @return void
     */
    protected function map_by_reference(&$data)
    {
        if ($data instanceof Closure) {
            if ($data === $this->closure) {
                $data = new Self_Reference($this->reference);
                return;
            }
            if (isset($this->scope[$data])) {
                $data = $this->scope[$data];
                return;
            }
            $instance = new static($data);
            $instance->scope = $this->scope;
            $data = $this->scope[$data] = $instance;
        } elseif (is_array($data)) {
            if (isset($data[self::ARRAY_RECURSIVE_KEY])) {
                return;
            }
            $data[self::ARRAY_RECURSIVE_KEY] = true;
            foreach ($data as $key => &$value) {
                if ($key === self::ARRAY_RECURSIVE_KEY) {
                    continue;
                }
                $this->map_by_reference($value);
            }
            unset($value);
            unset($data[self::ARRAY_RECURSIVE_KEY]);
        } elseif ($data instanceof \stdClass) {
            if (isset($this->scope[$data])) {
                $data = $this->scope[$data];
                return;
            }
            $instance = $data;
            $this->scope[$instance] = $data = clone $data;
            foreach ($data as &$value) {
                $this->map_by_reference($value);
            }
            unset($value);
        } elseif (is_object($data) && !$data instanceof Serializable_Closure && !$data instanceof Unsigned_Serializable_Closure) {
            if (isset($this->scope[$data])) {
                $data = $this->scope[$data];
                return;
            }
            $instance = $data;
            if ($data instanceof DateTimeInterface) {
                $this->scope[$instance] = $data;
                return;
            }
            if ($data instanceof Unit_Enum) {
                $this->scope[$instance] = $data;
                return;
            }
            $reflection = new Reflection_Object($data);
            if (!$reflection->is_user_defined() || $reflection->has_method('__serialize')) {
                $this->scope[$instance] = $data;
                return;
            }
            $this->scope[$instance] = $data = $reflection->new_instance_without_constructor();
            do {
                if (!$reflection->is_user_defined()) {
                    break;
                }
                foreach ($reflection->get_properties() as $property) {
                    if ($property->is_static()) {
                        continue;
                    }
                    if (!$property->get_declaring_class()->is_user_defined()) {
                        continue;
                    }
                    if (static::is_virtual_property($property)) {
                        continue;
                    }
                    if (!$property->is_initialized($instance)) {
                        continue;
                    }
                    if ($property->is_read_only() && $property->class !== $reflection->name) {
                        continue;
                    }
                    $value = $property->get_value($instance);
                    if (is_array($value) || is_object($value)) {
                        $this->map_by_reference($value);
                    }
                    $property->set_value($data, $value);
                }
            } while ($reflection = $reflection->get_parent_class());
        }
    }
    /**
     * Determine is virtual property.
     */
    protected static function is_virtual_property(ReflectionProperty $property): bool
    {
        return method_exists($property, 'isVirtual') && $property->is_virtual();
    }
}