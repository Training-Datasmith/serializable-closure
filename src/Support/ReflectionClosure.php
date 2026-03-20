<?php

declare (strict_types=1);
namespace Laravel\Serializable_Closure\Support;

defined('T_NAME_QUALIFIED') || define('T_NAME_QUALIFIED', -4);
defined('T_NAME_FULLY_QUALIFIED') || define('T_NAME_FULLY_QUALIFIED', -5);
defined('T_FN') || define('T_FN', -6);
defined('T_NULLSAFE_OBJECT_OPERATOR') || define('T_NULLSAFE_OBJECT_OPERATOR', -7);
use Closure;
use ReflectionFunction;
class Reflection_Closure extends ReflectionFunction
{
    protected $code;
    protected $tokens;
    protected $hashed_name;
    protected $use_variables;
    protected $is_static_closure;
    protected $is_scope_required;
    protected $is_binding_required;
    protected $is_short_closure;
    protected static $files = [];
    protected static $classes = [];
    protected static $functions = [];
    protected static $constants = [];
    protected static $structures = [];
    /**
     * Creates a new reflection closure instance.
     */
    public function __construct(Closure $closure)
    {
        parent::__construct($closure);
    }
    /**
     * Checks if the closure is "static".
     */
    public function is_static(): bool
    {
        if ($this->is_static_closure === null) {
            $this->is_static_closure = strtolower(substr($this->get_code(), 0, 6)) === 'static';
        }
        return $this->is_static_closure;
    }
    /**
     * Checks if the closure is a "short closure".
     *
     * @return bool
     */
    public function is_short_closure()
    {
        if ($this->is_short_closure === null) {
            $code = $this->get_code();
            if ($this->is_static()) {
                $code = substr($code, 6);
            }
            $this->is_short_closure = strtolower(substr(trim($code), 0, 2)) === 'fn';
        }
        return $this->is_short_closure;
    }
    /**
     * Get the closure's code.
     *
     * @return string
     */
    public function get_code()
    {
        if ($this->code !== null) {
            return $this->code;
        }
        $file_name = $this->get_file_name();
        $line = $this->get_start_line() - 1;
        $class_name = null;
        if (null !== $class_name = $this->get_closure_scope_class()) {
            $class_name = '\\' . trim($class_name->get_name(), '\\');
        }
        $builtin_types = self::get_builtin_types();
        $class_keywords = ['self', 'static', 'parent'];
        $ns = $this->get_closure_namespace_name();
        $nsf = $ns == '' ? '' : ($ns[0] == '\\' ? $ns : '\\' . $ns);
        $_file = var_export($file_name, true);
        $_dir = var_export(dirname($file_name), true);
        $_namespace = var_export($ns, true);
        $_class = var_export(trim($class_name ?: '', '\\'), true);
        $_function = $ns . ($ns == '' ? '' : '\\') . '{closure}';
        $_method = ($class_name == '' ? '' : trim($class_name, '\\') . '::') . $_function;
        $_function = var_export($_function, true);
        $_method = var_export($_method, true);
        $_trait = null;
        $tokens = $this->get_tokens();
        $state = $last_state = 'start';
        $inside_structure = false;
        $is_first_class_callable = false;
        $is_short_closure = false;
        $inside_structure_mark = 0;
        $open = 0;
        $code = '';
        $id_start = $id_start_ci = $id_name = $context = '';
        $classes = $functions = $constants = null;
        $use = [];
        $line_add = 0;
        $is_using_scope = false;
        $is_using_this_object = false;
        $candidates = [];
        for ($i = 0, $l = count($tokens); $i < $l; $i++) {
            $token = $tokens[$i];
            switch ($state) {
                case 'start':
                    if ($token[0] === T_FUNCTION || $token[0] === T_STATIC) {
                        $code .= $token[1];
                        $state = $token[0] === T_FUNCTION ? 'function' : 'static';
                    } elseif ($token[0] === T_FN) {
                        $is_short_closure = true;
                        $code .= $token[1];
                        $state = 'closure_args';
                    } elseif ($token[0] === T_PUBLIC || $token[0] === T_PROTECTED || $token[0] === T_PRIVATE) {
                        $code = '';
                        $is_first_class_callable = true;
                    }
                    break;
                case 'static':
                    if ($token[0] === T_WHITESPACE || $token[0] === T_COMMENT || $token[0] === T_FUNCTION) {
                        $code .= $token[1];
                        if ($token[0] === T_FUNCTION) {
                            $state = 'function';
                        }
                    } elseif ($token[0] === T_FN) {
                        $is_short_closure = true;
                        $code .= $token[1];
                        $state = 'closure_args';
                    } else {
                        $code = '';
                        $state = 'start';
                    }
                    break;
                case 'function':
                    switch ($token[0]) {
                        case T_STRING:
                            if ($is_first_class_callable) {
                                $state = 'closure_args';
                                break;
                            }
                            $code = '';
                            $state = 'named_function';
                            break;
                        case '(':
                            $code .= '(';
                            $state = 'closure_args';
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }
                    break;
                case 'named_function':
                    if ($token[0] === T_FUNCTION || $token[0] === T_STATIC) {
                        $code = $token[1];
                        $state = $token[0] === T_FUNCTION ? 'function' : 'static';
                    } elseif ($token[0] === T_FN) {
                        $is_short_closure = true;
                        $code .= $token[1];
                        $state = 'closure_args';
                    }
                    break;
                case 'closure_args':
                    switch ($token[0]) {
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parse_name_qualified($token[1]);
                            $context = 'args';
                            $state = 'id_name';
                            $last_state = 'closure_args';
                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start = $token[1];
                            $id_start_ci = strtolower((string) $id_start);
                            $id_name = '';
                            $context = 'args';
                            $state = 'id_name';
                            $last_state = 'closure_args';
                            break;
                        case T_USE:
                            $code .= $token[1];
                            $state = 'use';
                            break;
                        case T_DOUBLE_ARROW:
                            $code .= $token[1];
                            if ($is_short_closure) {
                                $state = 'closure';
                            }
                            break;
                        case ':':
                            $code .= ':';
                            $state = 'return';
                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            $open++;
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }
                    break;
                case 'use':
                    switch ($token[0]) {
                        case T_VARIABLE:
                            $use[] = substr((string) $token[1], 1);
                            $code .= $token[1];
                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            $open++;
                            break;
                        case ':':
                            $code .= ':';
                            $state = 'return';
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                            break;
                    }
                    break;
                case 'return':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $code .= $token[1];
                            break;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start = $token[1];
                            $id_start_ci = strtolower((string) $id_start);
                            $id_name = '';
                            $context = 'return_type';
                            $state = 'id_name';
                            $last_state = 'return';
                            break 2;
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parse_name_qualified($token[1]);
                            $context = 'return_type';
                            $state = 'id_name';
                            $last_state = 'return';
                            break 2;
                        case T_DOUBLE_ARROW:
                            $code .= $token[1];
                            if ($is_short_closure) {
                                $state = 'closure';
                            }
                            break;
                        case '{':
                            $code .= '{';
                            $state = 'closure';
                            $open++;
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                            break;
                    }
                    break;
                case 'closure':
                    switch ($token[0]) {
                        case T_CURLY_OPEN:
                        case T_DOLLAR_OPEN_CURLY_BRACES:
                        case '{':
                            $code .= is_array($token) ? $token[1] : $token;
                            $open++;
                            break;
                        case '}':
                            $code .= '}';
                            if (--$open === 0 && !$is_short_closure) {
                                $reset = $this->collect_candidate($candidates, $code, $use, $is_short_closure, $is_using_this_object, $is_using_scope);
                                $code = $reset['code'];
                                $state = $reset['state'];
                                $open = $reset['open'];
                                $use = $reset['use'];
                                $is_short_closure = $reset['isShortClosure'];
                                $is_using_this_object = $reset['isUsingThisObject'];
                                $is_using_scope = $reset['isUsingScope'];
                            } elseif ($inside_structure) {
                                $inside_structure = !($open === $inside_structure_mark);
                            }
                            break;
                        case '(':
                        case '[':
                            $code .= $token[0];
                            if ($is_short_closure) {
                                $open++;
                            }
                            break;
                        case ')':
                        case ']':
                            if ($is_short_closure) {
                                if ($open === 0) {
                                    $reset = $this->collect_candidate($candidates, $code, $use, $is_short_closure, $is_using_this_object, $is_using_scope);
                                    $code = $reset['code'];
                                    $state = $reset['state'];
                                    $open = $reset['open'];
                                    $use = $reset['use'];
                                    $is_short_closure = $reset['isShortClosure'];
                                    $is_using_this_object = $reset['isUsingThisObject'];
                                    $is_using_scope = $reset['isUsingScope'];
                                    continue 3;
                                }
                                $open--;
                            }
                            $code .= $token[0];
                            break;
                        case ',':
                        case ';':
                            if ($is_short_closure && $open === 0) {
                                $reset = $this->collect_candidate($candidates, $code, $use, $is_short_closure, $is_using_this_object, $is_using_scope);
                                $code = $reset['code'];
                                $state = $reset['state'];
                                $open = $reset['open'];
                                $use = $reset['use'];
                                $is_short_closure = $reset['isShortClosure'];
                                $is_using_this_object = $reset['isUsingThisObject'];
                                $is_using_scope = $reset['isUsingScope'];
                                continue 3;
                            }
                            $code .= $token[0];
                            break;
                        case T_LINE:
                            $code .= $token[2] - $line + $line_add;
                            break;
                        case T_FILE:
                            $code .= $_file;
                            break;
                        case T_DIR:
                            $code .= $_dir;
                            break;
                        case T_NS_C:
                            $code .= $_namespace;
                            break;
                        case T_CLASS_C:
                            $code .= $inside_structure ? $token[1] : $_class;
                            break;
                        case T_FUNC_C:
                            $code .= $inside_structure ? $token[1] : $_function;
                            break;
                        case T_METHOD_C:
                            $code .= $inside_structure ? $token[1] : $_method;
                            break;
                        case T_COMMENT:
                            if (str_starts_with((string) $token[1], '#trackme')) {
                                $timestamp = time();
                                $code .= '/**' . PHP_EOL;
                                $code .= '* Date      : ' . date(DATE_W3C, $timestamp) . PHP_EOL;
                                $code .= '* Timestamp : ' . $timestamp . PHP_EOL;
                                $code .= '* Line      : ' . ($line + 1) . PHP_EOL;
                                $code .= '* File      : ' . $_file . PHP_EOL . '*/' . PHP_EOL;
                                $line_add += 5;
                            } else {
                                $code .= $token[1];
                            }
                            break;
                        case T_VARIABLE:
                            if ($token[1] == '$this' && !$inside_structure) {
                                $is_using_this_object = true;
                            }
                            $code .= $token[1];
                            break;
                        case T_STATIC:
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start = $token[1];
                            $id_start_ci = strtolower((string) $id_start);
                            $id_name = '';
                            $context = 'root';
                            $state = 'id_name';
                            $last_state = 'closure';
                            break 2;
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parse_name_qualified($token[1]);
                            $context = 'root';
                            $state = 'id_name';
                            $last_state = 'closure';
                            break 2;
                        case T_NEW:
                            $code .= $token[1];
                            $context = 'new';
                            $state = 'id_start';
                            $last_state = 'closure';
                            break 2;
                        case T_USE:
                            $code .= $token[1];
                            $context = 'use';
                            $state = 'id_start';
                            $last_state = 'closure';
                            break;
                        case T_INSTANCEOF:
                        case T_INSTEADOF:
                            $code .= $token[1];
                            $context = 'instanceof';
                            $state = 'id_start';
                            $last_state = 'closure';
                            break;
                        case T_OBJECT_OPERATOR:
                        case T_NULLSAFE_OBJECT_OPERATOR:
                        case T_DOUBLE_COLON:
                            $code .= $token[1];
                            $last_state = 'closure';
                            $state = 'ignore_next';
                            break;
                        case T_FUNCTION:
                            $code .= $token[1];
                            $state = 'closure_args';
                            if (!$inside_structure) {
                                $inside_structure = true;
                                $inside_structure_mark = $open;
                            }
                            break;
                        case T_TRAIT_C:
                            if ($_trait === null) {
                                $start_line = $this->get_start_line();
                                $end_line = $this->get_end_line();
                                $structures = $this->get_structures();
                                $_trait = '';
                                foreach ($structures as &$struct) {
                                    if ($struct['type'] === 'trait' && $struct['start'] <= $start_line && $struct['end'] >= $end_line) {
                                        $_trait = ($ns == '' ? '' : $ns . '\\') . $struct['name'];
                                        break;
                                    }
                                }
                                $_trait = var_export($_trait, true);
                            }
                            $code .= $_trait;
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }
                    break;
                case 'ignore_next':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $code .= $token[1];
                            break;
                        case T_CLASS:
                        case T_NEW:
                        case T_STATIC:
                        case T_VARIABLE:
                        case T_STRING:
                        case T_CLASS_C:
                        case T_FILE:
                        case T_DIR:
                        case T_METHOD_C:
                        case T_FUNC_C:
                        case T_FUNCTION:
                        case T_INSTANCEOF:
                        case T_LINE:
                        case T_NS_C:
                        case T_TRAIT_C:
                        case T_USE:
                            $code .= $token[1];
                            $state = $last_state;
                            break;
                        default:
                            $state = $last_state;
                            $i--;
                    }
                    break;
                case 'id_start':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $code .= $token[1];
                            break;
                        case T_NS_SEPARATOR:
                        case T_NAME_FULLY_QUALIFIED:
                        case T_STRING:
                        case T_STATIC:
                            $id_start = $token[1];
                            $id_start_ci = strtolower((string) $id_start);
                            $id_name = '';
                            $state = 'id_name';
                            break 2;
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parse_name_qualified($token[1]);
                            $state = 'id_name';
                            break 2;
                        case T_VARIABLE:
                            $code .= $token[1];
                            $state = $last_state;
                            break;
                        case T_CLASS:
                            $code .= $token[1];
                            $state = 'anonymous';
                            break;
                        default:
                            $i--;
                            //reprocess last
                            $state = 'id_name';
                    }
                    break;
                case 'id_name':
                    switch ($token[0]) {
                        case $token[0] === ':' && !in_array($context, ['instanceof', 'new'], true):
                            if ($last_state === 'closure' && $context === 'root') {
                                $state = 'closure';
                                $code .= $id_start . $token;
                            }
                            break;
                        case T_NAME_QUALIFIED:
                        case T_NS_SEPARATOR:
                        case T_STRING:
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            $id_name .= $token[1];
                            break;
                        case '(':
                            if ($is_short_closure) {
                                $open++;
                            }
                            if ($context === 'new' || str_contains((string) $id_name, '\\')) {
                                if ($id_start_ci === 'self' || $id_start_ci === 'static') {
                                    if (!$inside_structure) {
                                        $is_using_scope = true;
                                    }
                                } elseif ($id_start !== '\\' && !in_array($id_start_ci, $class_keywords)) {
                                    if ($classes === null) {
                                        $classes = $this->get_classes();
                                    }
                                    if (isset($classes[$id_start_ci])) {
                                        $id_start = $classes[$id_start_ci];
                                    }
                                    if ($id_start[0] !== '\\') {
                                        $id_start = $nsf . '\\' . $id_start;
                                    }
                                }
                            } else if ($id_start !== '\\') {
                                if ($functions === null) {
                                    $functions = $this->get_functions();
                                }
                                if (isset($functions[$id_start_ci])) {
                                    $id_start = $functions[$id_start_ci];
                                } elseif ($nsf !== '\\' && function_exists($nsf . '\\' . $id_start)) {
                                    $id_start = $nsf . '\\' . $id_start;
                                    // Cache it to functions array
                                    $functions[$id_start_ci] = $id_start;
                                }
                            }
                            $code .= $id_start . $id_name . '(';
                            $state = $last_state;
                            break;
                        case T_VARIABLE:
                        case T_DOUBLE_COLON:
                            if ($id_start !== '\\') {
                                if ($id_start_ci === 'self' || $id_start_ci === 'parent') {
                                    if (!$inside_structure) {
                                        $is_using_scope = true;
                                    }
                                } elseif ($id_start_ci === 'static') {
                                    if (!$inside_structure) {
                                        $is_using_scope = $token[0] === T_DOUBLE_COLON;
                                    }
                                } elseif (!(\PHP_MAJOR_VERSION >= 7 && in_array($id_start_ci, $builtin_types))) {
                                    if ($classes === null) {
                                        $classes = $this->get_classes();
                                    }
                                    if (isset($classes[$id_start_ci])) {
                                        $id_start = $classes[$id_start_ci];
                                    }
                                    if ($id_start[0] !== '\\') {
                                        $id_start = $nsf . '\\' . $id_start;
                                    }
                                }
                            }
                            $code .= $id_start . $id_name . $token[1];
                            $state = $token[0] === T_DOUBLE_COLON ? 'ignore_next' : $last_state;
                            break;
                        default:
                            if ($id_start !== '\\' && !defined($id_start)) {
                                if ($constants === null) {
                                    $constants = $this->get_constants();
                                }
                                if (isset($constants[$id_start])) {
                                    $id_start = $constants[$id_start];
                                } elseif ($context === 'new') {
                                    if (in_array($id_start_ci, $class_keywords)) {
                                        if (!$inside_structure) {
                                            $is_using_scope = true;
                                        }
                                    } else {
                                        if ($classes === null) {
                                            $classes = $this->get_classes();
                                        }
                                        if (isset($classes[$id_start_ci])) {
                                            $id_start = $classes[$id_start_ci];
                                        }
                                        if ($id_start[0] !== '\\') {
                                            $id_start = $nsf . '\\' . $id_start;
                                        }
                                    }
                                } elseif ($context === 'use' || $context === 'instanceof' || $context === 'args' || $context === 'return_type' || $context === 'extends' || $context === 'root') {
                                    if (in_array($id_start_ci, $class_keywords)) {
                                        if (!$inside_structure && !$id_start_ci === 'static') {
                                            $is_using_scope = true;
                                        }
                                    } elseif (!(\PHP_MAJOR_VERSION >= 7 && in_array($id_start_ci, $builtin_types))) {
                                        if ($classes === null) {
                                            $classes = $this->get_classes();
                                        }
                                        if (isset($classes[$id_start_ci])) {
                                            $id_start = $classes[$id_start_ci];
                                        }
                                        if ($id_start[0] !== '\\') {
                                            $id_start = $nsf . '\\' . $id_start;
                                        }
                                    }
                                }
                            }
                            $code .= $id_start . $id_name;
                            $state = $last_state;
                            $i--;
                    }
                    break;
                case 'anonymous':
                    switch ($token[0]) {
                        case T_NAME_QUALIFIED:
                            [$id_start, $id_start_ci, $id_name] = $this->parse_name_qualified($token[1]);
                            $state = 'id_name';
                            $last_state = 'anonymous';
                            break 2;
                        case T_NS_SEPARATOR:
                        case T_STRING:
                            $id_start = $token[1];
                            $id_start_ci = strtolower((string) $id_start);
                            $id_name = '';
                            $state = 'id_name';
                            $context = 'extends';
                            $last_state = 'anonymous';
                            break;
                        case '{':
                            $state = 'closure';
                            if (!$inside_structure) {
                                $inside_structure = true;
                                $inside_structure_mark = $open;
                            }
                            $i--;
                            break;
                        default:
                            $code .= is_array($token) ? $token[1] : $token;
                    }
                    break;
            }
        }
        $attributes_code = array_map(function (\Reflection_Attribute $attribute): string {
            $arguments = $attribute->get_arguments();
            $name = $attribute->get_name();
            $arguments = implode(', ', array_map(function ($argument, int|string $key): ?string {
                $argument = var_export($argument, true);
                if (is_string($key)) {
                    return sprintf('%s: %s', $key, $argument);
                }
                return $argument;
            }, $arguments, array_keys($arguments)));
            return "#[{$name}({$arguments})]";
        }, $this->get_attributes());
        if (count($candidates) > 1) {
            $last_item = array_pop($candidates);
            foreach ($candidates as $candidate) {
                if (!$this->verify_candidate_signature($candidate)) {
                    continue;
                }
                $this->apply_candidate($candidate);
                $code = $candidate['code'];
                if (!empty($attributes_code)) {
                    $code = implode("\n", array_merge($attributes_code, [$code]));
                }
                $this->code = $code;
                return $this->code;
            }
            $candidates[] = $last_item;
        }
        $last_item = array_pop($candidates);
        if ($last_item) {
            $this->apply_candidate($last_item);
            $code = $last_item['code'];
        } else {
            if ($is_short_closure) {
                $this->use_variables = $this->get_static_variables();
            } else {
                $this->use_variables = empty($use) ? $use : array_intersect_key($this->get_static_variables(), array_flip($use));
            }
            $this->is_short_closure = $is_short_closure;
            $this->is_binding_required = $is_using_this_object;
            $this->is_scope_required = $is_using_scope;
        }
        if (!empty($attributes_code)) {
            $code = implode("\n", array_merge($attributes_code, [$code]));
        }
        $this->code = $code;
        return $this->code;
    }
    /**
     * Get PHP native built in types.
     */
    protected static function get_builtin_types(): array
    {
        return ['array', 'callable', 'string', 'int', 'bool', 'float', 'iterable', 'void', 'object', 'mixed', 'false', 'null', 'never'];
    }
    /**
     * Gets the use variables by the closure.
     *
     * @return array
     */
    public function get_use_variables()
    {
        if ($this->use_variables !== null) {
            return $this->use_variables;
        }
        if ($this->is_short_closure()) {
            return $this->use_variables;
        }
        $tokens = $this->get_tokens();
        $use = [];
        $state = 'start';
        foreach ($tokens as &$token) {
            $is_array = is_array($token);
            switch ($state) {
                case 'start':
                    if ($is_array && $token[0] === T_USE) {
                        $state = 'use';
                    }
                    break;
                case 'use':
                    if ($is_array) {
                        if ($token[0] === T_VARIABLE) {
                            $use[] = substr((string) $token[1], 1);
                        }
                    } elseif ($token == ')') {
                        break 2;
                    }
                    break;
            }
        }
        $this->use_variables = empty($use) ? $use : array_intersect_key($this->get_static_variables(), array_flip($use));
        return $this->use_variables;
    }
    /**
     * Checks if binding is required.
     *
     * @return bool
     */
    public function is_binding_required()
    {
        if ($this->is_binding_required === null) {
            $this->get_code();
        }
        return $this->is_binding_required;
    }
    /**
     * Checks if access to the scope is required.
     *
     * @return bool
     */
    public function is_scope_required()
    {
        if ($this->is_scope_required === null) {
            $this->get_code();
        }
        return $this->is_scope_required;
    }
    /**
     * The hash of the current file name.
     *
     * @return string
     */
    protected function get_hashed_file_name()
    {
        if ($this->hashed_name === null) {
            $this->hashed_name = sha1($this->get_file_name());
        }
        return $this->hashed_name;
    }
    /**
     * Get the file tokens.
     *
     * @return array
     */
    protected function get_file_tokens()
    {
        $key = $this->get_hashed_file_name();
        if (!isset(static::$files[$key])) {
            static::$files[$key] = token_get_all(file_get_contents($this->get_file_name()));
        }
        return static::$files[$key];
    }
    /**
     * Get the tokens.
     *
     * @return array
     */
    protected function get_tokens()
    {
        if ($this->tokens === null) {
            $tokens = $this->get_file_tokens();
            $start_line = $this->get_start_line();
            $end_line = $this->get_end_line();
            $results = [];
            $start = false;
            foreach ($tokens as &$token) {
                if (!is_array($token)) {
                    if ($start) {
                        $results[] = $token;
                    }
                    continue;
                }
                $line = $token[2];
                if ($line <= $end_line) {
                    if ($line >= $start_line) {
                        $start = true;
                        $results[] = $token;
                    }
                    continue;
                }
                break;
            }
            $this->tokens = $results;
        }
        return $this->tokens;
    }
    /**
     * Get the classes.
     *
     * @return array
     */
    protected function get_classes()
    {
        $line = $this->get_start_line();
        foreach ($this->get_structures() as $struct) {
            if ($struct['type'] === 'namespace' && $struct['start'] <= $line && $struct['end'] >= $line) {
                return $struct['classes'];
            }
        }
        return [];
    }
    /**
     * Get the functions.
     *
     * @return array
     */
    protected function get_functions()
    {
        $key = $this->get_hashed_file_name();
        if (!isset(static::$functions[$key])) {
            $this->fetch_items();
        }
        return static::$functions[$key];
    }
    /**
     * Gets the constants.
     *
     * @return array
     */
    protected function get_constants()
    {
        $key = $this->get_hashed_file_name();
        if (!isset(static::$constants[$key])) {
            $this->fetch_items();
        }
        return static::$constants[$key];
    }
    /**
     * Get the structures.
     *
     * @return array
     */
    protected function get_structures()
    {
        $key = $this->get_hashed_file_name();
        if (!isset(static::$structures[$key])) {
            $this->fetch_items();
        }
        return static::$structures[$key];
    }
    /**
     * Fetch the items.
     *
     * @return void.
     */
    protected function fetch_items()
    {
        $key = $this->get_hashed_file_name();
        $classes = [];
        $functions = [];
        $constants = [];
        $structures = [];
        $tokens = $this->get_file_tokens();
        $open = 0;
        $state = 'start';
        $last_state = '';
        $prefix = '';
        $name = '';
        $alias = '';
        $is_func = $is_const = false;
        $start_line = $last_known_line = 0;
        $struct_type = $struct_name = '';
        $struct_ignore = false;
        $namespace = '';
        $namespace_start_line = 0;
        $namespace_braced = false;
        $namespace_classes = [];
        foreach ($tokens as $token) {
            if (is_array($token)) {
                $last_known_line = $token[2];
            }
            switch ($state) {
                case 'start':
                    switch ($token[0]) {
                        case T_NAMESPACE:
                            $structures[] = ['type' => 'namespace', 'name' => $namespace, 'start' => $namespace_start_line, 'end' => $token[2] - 1, 'classes' => $namespace_classes];
                            $namespace = '';
                            $namespace_classes = [];
                            $state = 'namespace';
                            $namespace_start_line = $token[2];
                            break;
                        case T_CLASS:
                        case T_INTERFACE:
                        case T_TRAIT:
                            $state = 'before_structure';
                            $start_line = $token[2];
                            $struct_type = $token[0] == T_CLASS ? 'class' : ($token[0] == T_INTERFACE ? 'interface' : 'trait');
                            break;
                        case T_USE:
                            $state = 'use';
                            $prefix = $name = $alias = '';
                            $is_func = $is_const = false;
                            break;
                        case T_FUNCTION:
                            $state = 'structure';
                            $struct_ignore = true;
                            break;
                        case T_NEW:
                            $state = 'new';
                            break;
                        case T_OBJECT_OPERATOR:
                        case T_DOUBLE_COLON:
                            $state = 'invoke';
                            break;
                        case '}':
                            if ($namespace_braced) {
                                $structures[] = ['type' => 'namespace', 'name' => $namespace, 'start' => $namespace_start_line, 'end' => $last_known_line, 'classes' => $namespace_classes];
                                $namespace_braced = false;
                                $namespace = '';
                                $namespace_classes = [];
                            }
                            break;
                    }
                    break;
                case 'namespace':
                    switch ($token[0]) {
                        case T_STRING:
                        case T_NAME_QUALIFIED:
                            $namespace = $token[1];
                            break;
                        case ';':
                        case '{':
                            $state = 'start';
                            $namespace_braced = $token[0] === '{';
                            break;
                    }
                    break;
                case 'use':
                    switch ($token[0]) {
                        case T_FUNCTION:
                            $is_func = true;
                            break;
                        case T_CONST:
                            $is_const = true;
                            break;
                        case T_NS_SEPARATOR:
                            $name .= $token[1];
                            break;
                        case T_STRING:
                            $name .= $token[1];
                            $alias = $token[1];
                            break;
                        case T_NAME_QUALIFIED:
                            $name .= $token[1];
                            $pieces = explode('\\', (string) $token[1]);
                            $alias = end($pieces);
                            break;
                        case T_AS:
                            $last_state = 'use';
                            $state = 'alias';
                            break;
                        case '{':
                            $prefix = $name;
                            $name = $alias = '';
                            $state = 'use-group';
                            break;
                        case ',':
                        case ';':
                            if ($name === '' || $name[0] !== '\\') {
                                $name = '\\' . $name;
                            }
                            if ($alias !== '') {
                                if ($is_func) {
                                    $functions[strtolower($alias)] = $name;
                                } elseif ($is_const) {
                                    $constants[$alias] = $name;
                                } else {
                                    $classes[strtolower($alias)] = $name;
                                    $namespace_classes[strtolower($alias)] = $name;
                                }
                            }
                            $name = $alias = '';
                            $state = $token === ';' ? 'start' : 'use';
                            break;
                    }
                    break;
                case 'use-group':
                    switch ($token[0]) {
                        case T_NS_SEPARATOR:
                            $name .= $token[1];
                            break;
                        case T_NAME_QUALIFIED:
                            $name .= $token[1];
                            $pieces = explode('\\', (string) $token[1]);
                            $alias = end($pieces);
                            break;
                        case T_STRING:
                            $name .= $token[1];
                            $alias = $token[1];
                            break;
                        case T_AS:
                            $last_state = 'use-group';
                            $state = 'alias';
                            break;
                        case ',':
                        case '}':
                            if ($prefix === '' || $prefix[0] !== '\\') {
                                $prefix = '\\' . $prefix;
                            }
                            if ($alias !== '') {
                                if ($is_func) {
                                    $functions[strtolower($alias)] = $prefix . $name;
                                } elseif ($is_const) {
                                    $constants[$alias] = $prefix . $name;
                                } else {
                                    $classes[strtolower($alias)] = $prefix . $name;
                                    $namespace_classes[strtolower($alias)] = $prefix . $name;
                                }
                            }
                            $name = $alias = '';
                            $state = $token === '}' ? 'use' : 'use-group';
                            break;
                    }
                    break;
                case 'alias':
                    if ($token[0] === T_STRING) {
                        $alias = $token[1];
                        $state = $last_state;
                    }
                    break;
                case 'new':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            break 2;
                        case T_CLASS:
                            $state = 'structure';
                            $struct_ignore = true;
                            break;
                        default:
                            $state = 'start';
                    }
                    break;
                case 'invoke':
                    switch ($token[0]) {
                        case T_WHITESPACE:
                        case T_COMMENT:
                        case T_DOC_COMMENT:
                            break 2;
                        default:
                            $state = 'start';
                    }
                    break;
                case 'before_structure':
                    if ($token[0] == T_STRING) {
                        $struct_name = $token[1];
                        $state = 'structure';
                    }
                    break;
                case 'structure':
                    switch ($token[0]) {
                        case '{':
                        case T_CURLY_OPEN:
                        case T_DOLLAR_OPEN_CURLY_BRACES:
                            $open++;
                            break;
                        case '}':
                            if (--$open == 0) {
                                if (!$struct_ignore) {
                                    $structures[] = ['type' => $struct_type, 'name' => $struct_name, 'start' => $start_line, 'end' => $last_known_line];
                                }
                                $struct_ignore = false;
                                $state = 'start';
                            }
                            break;
                    }
                    break;
            }
        }
        $structures[] = ['type' => 'namespace', 'name' => $namespace, 'start' => $namespace_start_line, 'end' => PHP_INT_MAX, 'classes' => $namespace_classes];
        static::$classes[$key] = $classes;
        static::$functions[$key] = $functions;
        static::$constants[$key] = $constants;
        static::$structures[$key] = $structures;
    }
    /**
     * Returns the namespace associated to the closure.
     *
     * @return string
     */
    protected function get_closure_namespace_name()
    {
        $start_line = $this->get_start_line();
        $end_line = $this->get_end_line();
        foreach ($this->get_structures() as $struct) {
            if ($struct['type'] === 'namespace' && $struct['start'] <= $start_line && $struct['end'] >= $end_line) {
                return $struct['name'];
            }
        }
        return '';
    }
    /**
     * Parse the given token.
     *
     * @param  string  $token
     */
    protected function parse_name_qualified($token): array
    {
        $pieces = explode('\\', $token);
        $id_start = array_shift($pieces);
        $id_start_ci = strtolower($id_start);
        $id_name = '\\' . implode('\\', $pieces);
        return [$id_start, $id_start_ci, $id_name];
    }
    /**
     * Collect a closure candidate and reset state for finding the next one.
     *
     * @param  array  $candidates
     * @param  string  $code
     * @param  array  $use
     * @param  bool  $isShortClosure
     * @param  bool  $isUsingThisObject
     * @param  bool  $isUsingScope
     */
    protected function collect_candidate(&$candidates, $code, $use, $is_short_closure, $is_using_this_object, $is_using_scope): array
    {
        $candidates[] = ['code' => $code, 'use' => $use, 'isShortClosure' => $is_short_closure, 'isUsingThisObject' => $is_using_this_object, 'isUsingScope' => $is_using_scope];
        return ['code' => '', 'state' => 'start', 'open' => 0, 'use' => [], 'isShortClosure' => false, 'isUsingThisObject' => false, 'isUsingScope' => false];
    }
    /**
     * Apply a candidate's properties to this instance.
     *
     * @return void
     */
    protected function apply_candidate(array $candidate)
    {
        if ($candidate['isShortClosure']) {
            $this->use_variables = $this->get_static_variables();
        } else {
            $this->use_variables = empty($candidate['use']) ? $candidate['use'] : array_intersect_key($this->get_static_variables(), array_flip($candidate['use']));
        }
        $this->is_short_closure = $candidate['isShortClosure'];
        $this->is_binding_required = $candidate['isUsingThisObject'];
        $this->is_scope_required = $candidate['isUsingScope'];
    }
    /**
     * Verify that a candidate matches the closure's signature.
     */
    protected function verify_candidate_signature(array $candidate): bool
    {
        $code = $candidate['code'];
        $use = $candidate['use'];
        $is_short_closure = $candidate['isShortClosure'];
        // Check if code starts with 'static' (more precise than searching anywhere in code)
        $is_static_code = strtolower(substr(ltrim((string) $code), 0, 6)) === 'static';
        if (parent::is_static() !== $is_static_code) {
            return false;
        }
        // Parse the candidate to extract parameters and variables
        $tokens = token_get_all('<?php ' . $code);
        $params = [];
        $vars = [];
        $state = 'start';
        foreach ($tokens as $token) {
            if (!is_array($token)) {
                if ($token === '(' && $state === 'start') {
                    $state = 'params';
                } elseif ($token === ')' && $state === 'params') {
                    $state = 'body';
                }
                continue;
            }
            if ($token[0] === T_VARIABLE) {
                $name = substr($token[1], 1);
                if ($state === 'params') {
                    $params[] = $name;
                } elseif ($state === 'body' && $name !== 'this') {
                    $vars[$name] = true;
                }
            }
        }
        // Verify parameter count
        if (parent::get_number_of_parameters() !== count($params)) {
            return false;
        }
        // Verify use/captured variables
        if ($is_short_closure) {
            $actual_vars = array_keys(parent::get_static_variables());
            $found_captures = array_diff(array_keys($vars), $params);
            if (count($found_captures) !== count($actual_vars)) {
                return false;
            }
            if (count(array_diff($found_captures, $actual_vars)) > 0) {
                return false;
            }
        } else {
            $actual_static_variables = array_keys(parent::get_static_variables());
            if (!empty($use) && count(array_diff($use, $actual_static_variables)) > 0) {
                return false;
            }
            if (count($use) !== count(parent::get_static_variables())) {
                return false;
            }
        }
        return true;
    }
}