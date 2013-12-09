<?php

/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Compiler class.
 *
 * This class is responsible for turning a Mustache token parse tree into normal PHP source code.
 */
class Mustache_Compiler
{

    private $sections;
    private $source;
    private $indentNextLine;
    private $customEscape;
    private $entityFlags;
    private $charset;
    private $strictCallables;
    private $pragmas;

    /**
     * Compile a Mustache token parse tree into PHP source code.
     *
     * @param string $source          Mustache Template source code
     * @param string $tree            Parse tree of Mustache tokens
     * @param string $name            Mustache Template class name
     * @param bool   $customEscape    (default: false)
     * @param int    $entityFlags     (default: ENT_COMPAT)
     * @param string $charset         (default: 'UTF-8')
     * @param bool   $strictCallables (default: false)
     *
     * @return string Generated PHP source code
     */
    public function compile($source, array $tree, $name, $customEscape = false, $charset = 'UTF-8', $strictCallables = false, $entityFlags = ENT_COMPAT)
    {
        $this->pragmas         = array();
        $this->sections        = array();
        $this->source          = $source;
        $this->indentNextLine  = true;
        $this->customEscape    = $customEscape;
        $this->entityFlags     = $entityFlags;
        $this->charset         = $charset;
        $this->strictCallables = $strictCallables;

        return $this->writeCode($tree, $name);
    }

    /**
     * Helper function for walking the Mustache token parse tree.
     *
     * @throws Mustache_Exception_SyntaxException upon encountering unknown token types.
     *
     * @param array $tree  Parse tree of Mustache tokens
     * @param int   $level (default: 0)
     *
     * @return string Generated PHP source code
     */
    private function walk(array $tree, $level = 0)
    {
        $code = '';
        $level++;
        foreach ($tree as $node) {
            switch ($node[Mustache_Tokenizer::TYPE]) {
                case Mustache_Tokenizer::T_PRAGMA:
                    $this->pragmas[$node[Mustache_Tokenizer::NAME]] = true;
                    break;

                case Mustache_Tokenizer::T_SECTION:
                    $code .= $this->section(
                        $node[Mustache_Tokenizer::NODES],
                        $node[Mustache_Tokenizer::NAME],
                        $node[Mustache_Tokenizer::INDEX],
                        $node[Mustache_Tokenizer::END],
                        $node[Mustache_Tokenizer::OTAG],
                        $node[Mustache_Tokenizer::CTAG],
                        $level
                    );
                    break;

                case Mustache_Tokenizer::T_INVERTED:
                    $code .= $this->invertedSection(
                        $node[Mustache_Tokenizer::NODES],
                        $node[Mustache_Tokenizer::NAME],
                        $level
                    );
                    break;

                case Mustache_Tokenizer::T_PARTIAL:
                case Mustache_Tokenizer::T_PARTIAL_2:
                    $code .= $this->partial(
                        $node[Mustache_Tokenizer::NAME],
                        isset($node[Mustache_Tokenizer::INDENT]) ? $node[Mustache_Tokenizer::INDENT] : '',
                        $level
                    );
                    break;

                case Mustache_Tokenizer::T_UNESCAPED:
                case Mustache_Tokenizer::T_UNESCAPED_2:
                    $code .= $this->variable($node[Mustache_Tokenizer::NAME], false, $level);
                    break;

                case Mustache_Tokenizer::T_COMMENT:
                    break;

                case Mustache_Tokenizer::T_ESCAPED:
                    $code .= $this->variable($node[Mustache_Tokenizer::NAME], true, $level);
                    break;

                case Mustache_Tokenizer::T_TEXT:
                    $code .= $this->text($node[Mustache_Tokenizer::VALUE], $level);
                    break;

                default:
                    throw new Mustache_Exception_SyntaxException(sprintf('Unknown token type: %s', $node[Mustache_Tokenizer::TYPE]), $node);
            }
        }

        return $code;
    }

    const KLASS = '<?php

        class %s extends Mustache_Template
        {
            private $lambdaHelper;%s

            public function renderInternal(Mustache_Context $context, $indent = \'\')
            {
                $this->lambdaHelper = new Mustache_LambdaHelper($this->mustache, $context);
                $buffer = \'\';
        %s

                return $buffer;
            }
        %s
        }';

    const KLASS_NO_LAMBDAS = '<?php

        class %s extends Mustache_Template
        {%s
            public function renderInternal(Mustache_Context $context, $indent = \'\')
            {
                $buffer = \'\';
        %s

                return $buffer;
            }
        }';

    const STRICT_CALLABLE = 'protected $strictCallables = true;';

    /**
     * Generate Mustache Template class PHP source.
     *
     * @param array  $tree Parse tree of Mustache tokens
     * @param string $name Mustache Template class name
     *
     * @return string Generated PHP source code
     */
    private function writeCode($tree, $name)
    {
        $code     = $this->walk($tree);
        $sections = implode("\n", $this->sections);
        $klass    = empty($this->sections) ? self::KLASS_NO_LAMBDAS : self::KLASS;
        $callable = $this->strictCallables ? $this->prepare(self::STRICT_CALLABLE) : '';

        return sprintf($this->prepare($klass, 0, false, true), $name, $callable, $code, $sections);
    }

    const SECTION_CALL = '
        // %s section
        $buffer .= $this->section%s($context, $indent, $context->%s(%s));
    ';

    const SECTION = '
        private function section%s(Mustache_Context $context, $indent, $value)
        {
            $buffer = \'\';
            if (%s) {
                $source = %s;
                $buffer .= $this->mustache
                    ->loadLambda((string) call_user_func($value, $source, $this->lambdaHelper)%s)
                    ->renderInternal($context);
            } elseif (!empty($value)) {
                $values = $this->isIterable($value) ? $value : array($value);
                foreach ($values as $value) {
                    $context->push($value);%s
                    $context->pop();
                }
            }

            return $buffer;
        }';

    /**
     * Generate Mustache Template section PHP source.
     *
     * @param array  $nodes Array of child tokens
     * @param string $id    Section name
     * @param int    $start Section start offset
     * @param int    $end   Section end offset
     * @param string $otag  Current Mustache opening tag
     * @param string $ctag  Current Mustache closing tag
     * @param int    $level
     *
     * @return string Generated section PHP source code
     */
    private function section($nodes, $id, $start, $end, $otag, $ctag, $level)
    {
        $method   = $this->getFindMethod($id);
        $id       = var_export($id, true);
        $source   = var_export(substr($this->source, $start, $end - $start), true);
        $callable = $this->getCallable();

        if ($otag !== '{{' || $ctag !== '}}') {
            $delims = ', '.var_export(sprintf('{{= %s %s =}}', $otag, $ctag), true);
        } else {
            $delims = '';
        }

        $key    = ucfirst(md5($delims."\n".$source));

        if (!isset($this->sections[$key])) {
            $this->sections[$key] = sprintf($this->prepare(self::SECTION), $key, $callable, $source, $delims, $this->walk($nodes, 2));
        }

        return sprintf($this->prepare(self::SECTION_CALL, $level), $id, $key, $method, $id);
    }

    const INVERTED_SECTION = '
        // %s inverted section
        $value = $context->%s(%s);
        if (empty($value)) {
            %s
        }';

    /**
     * Generate Mustache Template inverted section PHP source.
     *
     * @param array  $nodes Array of child tokens
     * @param string $id    Section name
     * @param int    $level
     *
     * @return string Generated inverted section PHP source code
     */
    private function invertedSection($nodes, $id, $level)
    {
        $method = $this->getFindMethod($id);
        $id     = var_export($id, true);

        return sprintf($this->prepare(self::INVERTED_SECTION, $level), $id, $method, $id, $this->walk($nodes, $level));
    }

    const PARTIAL = '
        if ($partial = $this->mustache->loadPartial(%s)) {
            $buffer .= $partial->renderInternal($context, %s);
        }
    ';

    /**
     * Generate Mustache Template partial call PHP source.
     *
     * @param string $id     Partial name
     * @param string $indent Whitespace indent to apply to partial
     * @param int    $level
     *
     * @return string Generated partial call PHP source code
     */
    private function partial($id, $indent, $level)
    {
        return sprintf(
            $this->prepare(self::PARTIAL, $level),
            var_export($id, true),
            var_export($indent, true)
        );
    }

    const VARIABLE = '
        $value = $this->resolveValue($context->%s(%s), $context, $indent);%s
        $buffer .= %s%s;
    ';

    /**
     * Generate Mustache Template variable interpolation PHP source.
     *
     * @param string  $id     Variable name
     * @param boolean $escape Escape the variable value for output?
     * @param int     $level
     *
     * @return string Generated variable interpolation PHP source
     */
    private function variable($id, $escape, $level)
    {
        $filters = '';

        if (isset($this->pragmas[Mustache_Engine::PRAGMA_FILTERS])) {
            list($id, $filters) = $this->getFilters($id, $level);
        }

        $method = $this->getFindMethod($id);
        $id     = ($method !== 'last') ? var_export($id, true) : '';
        $value  = $escape ? $this->getEscape() : '$value';

        return sprintf($this->prepare(self::VARIABLE, $level), $method, $id, $filters, $this->flushIndent(), $value);
    }

    /**
     * Generate Mustache Template variable filtering PHP source.
     *
     * @param string $id    Variable name
     * @param int    $level
     *
     * @return string Generated variable filtering PHP source
     */
    private function getFilters($id, $level)
    {
        $filters = array_map('trim', explode('|', $id));
        $id      = array_shift($filters);

        return array($id, $this->getFilter($filters, $level));
    }

    const FILTER = '
        $filter = $context->%s(%s);
        if (!(%s)) {
            throw new Mustache_Exception_UnknownFilterException(%s);
        }
        $value = call_user_func($filter, $value);%s
    ';

    /**
     * Generate PHP source for a single filter.
     *
     * @param array $filters
     * @param int   $level
     *
     * @return string Generated filter PHP source
     */
    private function getFilter(array $filters, $level)
    {
        if (empty($filters)) {
            return '';
        }

        $name     = array_shift($filters);
        $method   = $this->getFindMethod($name);
        $filter   = ($method !== 'last') ? var_export($name, true) : '';
        $callable = $this->getCallable('$filter');
        $msg      = var_export($name, true);

        return sprintf($this->prepare(self::FILTER, $level), $method, $filter, $callable, $msg, $this->getFilter($filters, $level));
    }

    const LINE = '$buffer .= "\n";';
    const TEXT = '$buffer .= %s%s;';

    /**
     * Generate Mustache Template output Buffer call PHP source.
     *
     * @param string $text
     * @param int    $level
     *
     * @return string Generated output Buffer call PHP source
     */
    private function text($text, $level)
    {
        $indentNextLine = (substr($text, -1) === "\n");
        $code = sprintf($this->prepare(self::TEXT, $level), $this->flushIndent(), var_export($text, true));
        $this->indentNextLine = $indentNextLine;

        return $code;
    }

    /**
     * Prepare PHP source code snippet for output.
     *
     * @param string  $text
     * @param int     $bonus          Additional indent level (default: 0)
     * @param boolean $prependNewline Prepend a newline to the snippet? (default: true)
     * @param boolean $appendNewline  Append a newline to the snippet? (default: false)
     *
     * @return string PHP source code snippet
     */
    private function prepare($text, $bonus = 0, $prependNewline = true, $appendNewline = false)
    {
        $text = ($prependNewline ? "\n" : '').trim($text);
        if ($prependNewline) {
            $bonus++;
        }
        if ($appendNewline) {
            $text .= "\n";
        }

        return preg_replace("/\n( {8})?/", "\n".str_repeat(" ", $bonus * 4), $text);
    }

    const DEFAULT_ESCAPE = 'htmlspecialchars(%s, %s, %s)';
    const CUSTOM_ESCAPE  = 'call_user_func($this->mustache->getEscape(), %s)';

    /**
     * Get the current escaper.
     *
     * @param string $value (default: '$value')
     *
     * @return string Either a custom callback, or an inline call to `htmlspecialchars`
     */
    private function getEscape($value = '$value')
    {
        if ($this->customEscape) {
            return sprintf(self::CUSTOM_ESCAPE, $value);
        } else {
            return sprintf(self::DEFAULT_ESCAPE, $value, var_export($this->entityFlags, true), var_export($this->charset, true));
        }
    }

    /**
     * Select the appropriate Context `find` method for a given $id.
     *
     * The return value will be one of `find`, `findDot` or `last`.
     *
     * @see Mustache_Context::find
     * @see Mustache_Context::findDot
     * @see Mustache_Context::last
     *
     * @param string $id Variable name
     *
     * @return string `find` method name
     */
    private function getFindMethod($id)
    {
        if ($id === '.') {
            return 'last';
        } elseif (strpos($id, '.') === false) {
            return 'find';
        } else {
            return 'findDot';
        }
    }

    const IS_CALLABLE        = '!is_string(%s) && is_callable(%s)';
    const STRICT_IS_CALLABLE = 'is_object(%s) && is_callable(%s)';

    private function getCallable($variable = '$value')
    {
        $tpl = $this->strictCallables ? self::STRICT_IS_CALLABLE : self::IS_CALLABLE;

        return sprintf($tpl, $variable, $variable);
    }

    const LINE_INDENT = '$indent . ';

    /**
     * Get the current $indent prefix to write to the buffer.
     *
     * @return string "$indent . " or ""
     */
    private function flushIndent()
    {
        if (!$this->indentNextLine) {
            return '';
        }

        $this->indentNextLine = false;

        return self::LINE_INDENT;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Template rendering Context.
 */
class Mustache_Context
{
    private $stack = array();

    /**
     * Mustache rendering Context constructor.
     *
     * @param mixed $context Default rendering context (default: null)
     */
    public function __construct($context = null)
    {
        if ($context !== null) {
            $this->stack = array($context);
        }
    }

    /**
     * Push a new Context frame onto the stack.
     *
     * @param mixed $value Object or array to use for context
     */
    public function push($value)
    {
        array_push($this->stack, $value);
    }

    /**
     * Pop the last Context frame from the stack.
     *
     * @return mixed Last Context frame (object or array)
     */
    public function pop()
    {
        return array_pop($this->stack);
    }

    /**
     * Get the last Context frame.
     *
     * @return mixed Last Context frame (object or array)
     */
    public function last()
    {
        return end($this->stack);
    }

    /**
     * Find a variable in the Context stack.
     *
     * Starting with the last Context frame (the context of the innermost section), and working back to the top-level
     * rendering context, look for a variable with the given name:
     *
     *  * If the Context frame is an associative array which contains the key $id, returns the value of that element.
     *  * If the Context frame is an object, this will check first for a public method, then a public property named
     *    $id. Failing both of these, it will try `__isset` and `__get` magic methods.
     *  * If a value named $id is not found in any Context frame, returns an empty string.
     *
     * @param string $id Variable name
     *
     * @return mixed Variable value, or '' if not found
     */
    public function find($id)
    {
        return $this->findVariableInStack($id, $this->stack);
    }

    /**
     * Find a 'dot notation' variable in the Context stack.
     *
     * Note that dot notation traversal bubbles through scope differently than the regular find method. After finding
     * the initial chunk of the dotted name, each subsequent chunk is searched for only within the value of the previous
     * result. For example, given the following context stack:
     *
     *     $data = array(
     *         'name' => 'Fred',
     *         'child' => array(
     *             'name' => 'Bob'
     *         ),
     *     );
     *
     * ... and the Mustache following template:
     *
     *     {{ child.name }}
     *
     * ... the `name` value is only searched for within the `child` value of the global Context, not within parent
     * Context frames.
     *
     * @param string $id Dotted variable selector
     *
     * @return mixed Variable value, or '' if not found
     */
    public function findDot($id)
    {
        $chunks = explode('.', $id);
        $first  = array_shift($chunks);
        $value  = $this->findVariableInStack($first, $this->stack);

        foreach ($chunks as $chunk) {
            if ($value === '') {
                return $value;
            }

            $value = $this->findVariableInStack($chunk, array($value));
        }

        return $value;
    }

    /**
     * Helper function to find a variable in the Context stack.
     *
     * @see Mustache_Context::find
     *
     * @param string $id    Variable name
     * @param array  $stack Context stack
     *
     * @return mixed Variable value, or '' if not found
     */
    private function findVariableInStack($id, array $stack)
    {
        for ($i = count($stack) - 1; $i >= 0; $i--) {
            if (is_object($stack[$i]) && !$stack[$i] instanceof Closure) {
                if (method_exists($stack[$i], $id)) {
                    return $stack[$i]->$id();
                } elseif (isset($stack[$i]->$id)) {
                    return $stack[$i]->$id;
                }
            } elseif (is_array($stack[$i]) && array_key_exists($id, $stack[$i])) {
                return $stack[$i][$id];
            }
        }

        return '';
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A Mustache implementation in PHP.
 *
 * {@link http://defunkt.github.com/mustache}
 *
 * Mustache is a framework-agnostic logic-less templating language. It enforces separation of view
 * logic from template files. In fact, it is not even possible to embed logic in the template.
 *
 * This is very, very rad.
 *
 * @author Justin Hileman {@link http://justinhileman.com}
 */
class Mustache_Engine
{
    const VERSION        = '2.4.1';
    const SPEC_VERSION   = '1.1.2';

    const PRAGMA_FILTERS = 'FILTERS';

    // Template cache
    private $templates = array();

    // Environment
    private $templateClassPrefix = '__Mustache_';
    private $cache = null;
    private $cacheFileMode = null;
    private $loader;
    private $partialsLoader;
    private $helpers;
    private $escape;
    private $entityFlags = ENT_COMPAT;
    private $charset = 'UTF-8';
    private $logger;
    private $strictCallables = false;

    /**
     * Mustache class constructor.
     *
     * Passing an $options array allows overriding certain Mustache options during instantiation:
     *
     *     $options = array(
     *         // The class prefix for compiled templates. Defaults to '__Mustache_'.
     *         'template_class_prefix' => '__MyTemplates_',
     *
     *         // A cache directory for compiled templates. Mustache will not cache templates unless this is set
     *         'cache' => dirname(__FILE__).'/tmp/cache/mustache',
     *
     *         // Override default permissions for cache files. Defaults to using the system-defined umask. It is
     *         // *strongly* recommended that you configure your umask properly rather than overriding permissions here.
     *         'cache_file_mode' => 0666,
     *
     *         // A Mustache template loader instance. Uses a StringLoader if not specified.
     *         'loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views'),
     *
     *         // A Mustache loader instance for partials.
     *         'partials_loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views/partials'),
     *
     *         // An array of Mustache partials. Useful for quick-and-dirty string template loading, but not as
     *         // efficient or lazy as a Filesystem (or database) loader.
     *         'partials' => array('foo' => file_get_contents(dirname(__FILE__).'/views/partials/foo.mustache')),
     *
     *         // An array of 'helpers'. Helpers can be global variables or objects, closures (e.g. for higher order
     *         // sections), or any other valid Mustache context value. They will be prepended to the context stack,
     *         // so they will be available in any template loaded by this Mustache instance.
     *         'helpers' => array('i18n' => function($text) {
     *             // do something translatey here...
     *         }),
     *
     *         // An 'escape' callback, responsible for escaping double-mustache variables.
     *         'escape' => function($value) {
     *             return htmlspecialchars($buffer, ENT_COMPAT, 'UTF-8');
     *         },
     *
     *         // Type argument for `htmlspecialchars`.  Defaults to ENT_COMPAT.  You may prefer ENT_QUOTES.
     *         'entity_flags' => ENT_QUOTES,
     *
     *         // Character set for `htmlspecialchars`. Defaults to 'UTF-8'. Use 'UTF-8'.
     *         'charset' => 'ISO-8859-1',
     *
     *         // A Mustache Logger instance. No logging will occur unless this is set. Using a PSR-3 compatible
     *         // logging library -- such as Monolog -- is highly recommended. A simple stream logger implementation is
     *         // available as well:
     *         'logger' => new Mustache_Logger_StreamLogger('php://stderr'),
     *
     *         // Only treat Closure instances and invokable classes as callable. If true, values like
     *         // `array('ClassName', 'methodName')` and `array($classInstance, 'methodName')`, which are traditionally
     *         // "callable" in PHP, are not called to resolve variables for interpolation or section contexts. This
     *         // helps protect against arbitrary code execution when user input is passed directly into the template.
     *         // This currently defaults to false, but will default to true in v3.0.
     *         'strict_callables' => true,
     *     );
     *
     * @throws Mustache_Exception_InvalidArgumentException If `escape` option is not callable.
     *
     * @param array $options (default: array())
     */
    public function __construct(array $options = array())
    {
        if (isset($options['template_class_prefix'])) {
            $this->templateClassPrefix = $options['template_class_prefix'];
        }

        if (isset($options['cache'])) {
            $this->cache = $options['cache'];
        }

        if (isset($options['cache_file_mode'])) {
            $this->cacheFileMode = $options['cache_file_mode'];
        }

        if (isset($options['loader'])) {
            $this->setLoader($options['loader']);
        }

        if (isset($options['partials_loader'])) {
            $this->setPartialsLoader($options['partials_loader']);
        }

        if (isset($options['partials'])) {
            $this->setPartials($options['partials']);
        }

        if (isset($options['helpers'])) {
            $this->setHelpers($options['helpers']);
        }

        if (isset($options['escape'])) {
            if (!is_callable($options['escape'])) {
                throw new Mustache_Exception_InvalidArgumentException('Mustache Constructor "escape" option must be callable');
            }

            $this->escape = $options['escape'];
        }

        if (isset($options['entity_flags'])) {
          $this->entityFlags = $options['entity_flags'];
        }

        if (isset($options['charset'])) {
            $this->charset = $options['charset'];
        }

        if (isset($options['logger'])) {
            $this->setLogger($options['logger']);
        }

        if (isset($options['strict_callables'])) {
            $this->strictCallables = $options['strict_callables'];
        }
    }

    /**
     * Shortcut 'render' invocation.
     *
     * Equivalent to calling `$mustache->loadTemplate($template)->render($context);`
     *
     * @see Mustache_Engine::loadTemplate
     * @see Mustache_Template::render
     *
     * @param string $template
     * @param mixed  $context (default: array())
     *
     * @return string Rendered template
     */
    public function render($template, $context = array())
    {
        return $this->loadTemplate($template)->render($context);
    }

    /**
     * Get the current Mustache escape callback.
     *
     * @return mixed Callable or null
     */
    public function getEscape()
    {
        return $this->escape;
    }

    /**
     * Get the current Mustache entitity type to escape.
     *
     * @return int
     */
    public function getEntityFlags()
    {
      return $this->entityFlags;
    }

    /**
     * Get the current Mustache character set.
     *
     * @return string
     */
    public function getCharset()
    {
        return $this->charset;
    }

    /**
     * Set the Mustache template Loader instance.
     *
     * @param Mustache_Loader $loader
     */
    public function setLoader(Mustache_Loader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Get the current Mustache template Loader instance.
     *
     * If no Loader instance has been explicitly specified, this method will instantiate and return
     * a StringLoader instance.
     *
     * @return Mustache_Loader
     */
    public function getLoader()
    {
        if (!isset($this->loader)) {
            $this->loader = new Mustache_Loader_StringLoader;
        }

        return $this->loader;
    }

    /**
     * Set the Mustache partials Loader instance.
     *
     * @param Mustache_Loader $partialsLoader
     */
    public function setPartialsLoader(Mustache_Loader $partialsLoader)
    {
        $this->partialsLoader = $partialsLoader;
    }

    /**
     * Get the current Mustache partials Loader instance.
     *
     * If no Loader instance has been explicitly specified, this method will instantiate and return
     * an ArrayLoader instance.
     *
     * @return Mustache_Loader
     */
    public function getPartialsLoader()
    {
        if (!isset($this->partialsLoader)) {
            $this->partialsLoader = new Mustache_Loader_ArrayLoader;
        }

        return $this->partialsLoader;
    }

    /**
     * Set partials for the current partials Loader instance.
     *
     * @throws Mustache_Exception_RuntimeException If the current Loader instance is immutable
     *
     * @param array $partials (default: array())
     */
    public function setPartials(array $partials = array())
    {
        if (!isset($this->partialsLoader)) {
            $this->partialsLoader = new Mustache_Loader_ArrayLoader;
        }

        if (!$this->partialsLoader instanceof Mustache_Loader_MutableLoader) {
            throw new Mustache_Exception_RuntimeException('Unable to set partials on an immutable Mustache Loader instance');
        }

        $this->partialsLoader->setTemplates($partials);
    }

    /**
     * Set an array of Mustache helpers.
     *
     * An array of 'helpers'. Helpers can be global variables or objects, closures (e.g. for higher order sections), or
     * any other valid Mustache context value. They will be prepended to the context stack, so they will be available in
     * any template loaded by this Mustache instance.
     *
     * @throws Mustache_Exception_InvalidArgumentException if $helpers is not an array or Traversable
     *
     * @param array|Traversable $helpers
     */
    public function setHelpers($helpers)
    {
        if (!is_array($helpers) && !$helpers instanceof Traversable) {
            throw new Mustache_Exception_InvalidArgumentException('setHelpers expects an array of helpers');
        }

        $this->getHelpers()->clear();

        foreach ($helpers as $name => $helper) {
            $this->addHelper($name, $helper);
        }
    }

    /**
     * Get the current set of Mustache helpers.
     *
     * @see Mustache_Engine::setHelpers
     *
     * @return Mustache_HelperCollection
     */
    public function getHelpers()
    {
        if (!isset($this->helpers)) {
            $this->helpers = new Mustache_HelperCollection;
        }

        return $this->helpers;
    }

    /**
     * Add a new Mustache helper.
     *
     * @see Mustache_Engine::setHelpers
     *
     * @param string $name
     * @param mixed  $helper
     */
    public function addHelper($name, $helper)
    {
        $this->getHelpers()->add($name, $helper);
    }

    /**
     * Get a Mustache helper by name.
     *
     * @see Mustache_Engine::setHelpers
     *
     * @param string $name
     *
     * @return mixed Helper
     */
    public function getHelper($name)
    {
        return $this->getHelpers()->get($name);
    }

    /**
     * Check whether this Mustache instance has a helper.
     *
     * @see Mustache_Engine::setHelpers
     *
     * @param string $name
     *
     * @return boolean True if the helper is present
     */
    public function hasHelper($name)
    {
        return $this->getHelpers()->has($name);
    }

    /**
     * Remove a helper by name.
     *
     * @see Mustache_Engine::setHelpers
     *
     * @param string $name
     */
    public function removeHelper($name)
    {
        $this->getHelpers()->remove($name);
    }

    /**
     * Set the Mustache Logger instance.
     *
     * @throws Mustache_Exception_InvalidArgumentException If logger is not an instance of Mustache_Logger or Psr\Log\LoggerInterface.
     *
     * @param Mustache_Logger|Psr\Log\LoggerInterface $logger
     */
    public function setLogger($logger = null)
    {
        if ($logger !== null && !($logger instanceof Mustache_Logger || is_a($logger, 'Psr\\Log\\LoggerInterface'))) {
            throw new Mustache_Exception_InvalidArgumentException('Expected an instance of Mustache_Logger or Psr\\Log\\LoggerInterface.');
        }

        $this->logger = $logger;
    }

    /**
     * Get the current Mustache Logger instance.
     *
     * @return Mustache_Logger|Psr\Log\LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Set the Mustache Tokenizer instance.
     *
     * @param Mustache_Tokenizer $tokenizer
     */
    public function setTokenizer(Mustache_Tokenizer $tokenizer)
    {
        $this->tokenizer = $tokenizer;
    }

    /**
     * Get the current Mustache Tokenizer instance.
     *
     * If no Tokenizer instance has been explicitly specified, this method will instantiate and return a new one.
     *
     * @return Mustache_Tokenizer
     */
    public function getTokenizer()
    {
        if (!isset($this->tokenizer)) {
            $this->tokenizer = new Mustache_Tokenizer;
        }

        return $this->tokenizer;
    }

    /**
     * Set the Mustache Parser instance.
     *
     * @param Mustache_Parser $parser
     */
    public function setParser(Mustache_Parser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Get the current Mustache Parser instance.
     *
     * If no Parser instance has been explicitly specified, this method will instantiate and return a new one.
     *
     * @return Mustache_Parser
     */
    public function getParser()
    {
        if (!isset($this->parser)) {
            $this->parser = new Mustache_Parser;
        }

        return $this->parser;
    }

    /**
     * Set the Mustache Compiler instance.
     *
     * @param Mustache_Compiler $compiler
     */
    public function setCompiler(Mustache_Compiler $compiler)
    {
        $this->compiler = $compiler;
    }

    /**
     * Get the current Mustache Compiler instance.
     *
     * If no Compiler instance has been explicitly specified, this method will instantiate and return a new one.
     *
     * @return Mustache_Compiler
     */
    public function getCompiler()
    {
        if (!isset($this->compiler)) {
            $this->compiler = new Mustache_Compiler;
        }

        return $this->compiler;
    }

    /**
     * Helper method to generate a Mustache template class.
     *
     * @param string $source
     *
     * @return string Mustache Template class name
     */
    public function getTemplateClassName($source)
    {
        return $this->templateClassPrefix . md5(sprintf(
            'version:%s,escape:%s,entity_flags:%i,charset:%s,strict_callables:%s,source:%s',
            self::VERSION,
            isset($this->escape) ? 'custom' : 'default',
            $this->entityFlags,
            $this->charset,
            $this->strictCallables ? 'true' : 'false',
            $source
        ));
    }

    /**
     * Load a Mustache Template by name.
     *
     * @param string $name
     *
     * @return Mustache_Template
     */
    public function loadTemplate($name)
    {
        return $this->loadSource($this->getLoader()->load($name));
    }

    /**
     * Load a Mustache partial Template by name.
     *
     * This is a helper method used internally by Template instances for loading partial templates. You can most likely
     * ignore it completely.
     *
     * @param string $name
     *
     * @return Mustache_Template
     */
    public function loadPartial($name)
    {
        try {
            if (isset($this->partialsLoader)) {
                $loader = $this->partialsLoader;
            } elseif (isset($this->loader) && !$this->loader instanceof Mustache_Loader_StringLoader) {
                $loader = $this->loader;
            } else {
                throw new Mustache_Exception_UnknownTemplateException($name);
            }

            return $this->loadSource($loader->load($name));
        } catch (Mustache_Exception_UnknownTemplateException $e) {
            // If the named partial cannot be found, log then return null.
            $this->log(
                Mustache_Logger::WARNING,
                'Partial not found: "{name}"',
                array('name' => $e->getTemplateName())
            );
        }
    }

    /**
     * Load a Mustache lambda Template by source.
     *
     * This is a helper method used by Template instances to generate subtemplates for Lambda sections. You can most
     * likely ignore it completely.
     *
     * @param string $source
     * @param string $delims (default: null)
     *
     * @return Mustache_Template
     */
    public function loadLambda($source, $delims = null)
    {
        if ($delims !== null) {
            $source = $delims . "\n" . $source;
        }

        return $this->loadSource($source);
    }

    /**
     * Instantiate and return a Mustache Template instance by source.
     *
     * @see Mustache_Engine::loadTemplate
     * @see Mustache_Engine::loadPartial
     * @see Mustache_Engine::loadLambda
     *
     * @param string $source
     *
     * @return Mustache_Template
     */
    private function loadSource($source)
    {
        $className = $this->getTemplateClassName($source);

        if (!isset($this->templates[$className])) {
            if (!class_exists($className, false)) {
                if ($fileName = $this->getCacheFilename($source)) {
                    if (!is_file($fileName)) {
                        $this->log(
                            Mustache_Logger::DEBUG,
                            'Writing "{className}" class to template cache: "{fileName}"',
                            array('className' => $className, 'fileName' => $fileName)
                        );

                        $this->writeCacheFile($fileName, $this->compile($source));
                    }

                    require_once $fileName;
                } else {
                    $this->log(
                        Mustache_Logger::WARNING,
                        'Template cache disabled, evaluating "{className}" class at runtime',
                        array('className' => $className)
                    );

                    eval('?>'.$this->compile($source));
                }
            }

            $this->log(
                Mustache_Logger::DEBUG,
                'Instantiating template: "{className}"',
                array('className' => $className)
            );

            $this->templates[$className] = new $className($this);
        }

        return $this->templates[$className];
    }

    /**
     * Helper method to tokenize a Mustache template.
     *
     * @see Mustache_Tokenizer::scan
     *
     * @param string $source
     *
     * @return array Tokens
     */
    private function tokenize($source)
    {
        return $this->getTokenizer()->scan($source);
    }

    /**
     * Helper method to parse a Mustache template.
     *
     * @see Mustache_Parser::parse
     *
     * @param string $source
     *
     * @return array Token tree
     */
    private function parse($source)
    {
        return $this->getParser()->parse($this->tokenize($source));
    }

    /**
     * Helper method to compile a Mustache template.
     *
     * @see Mustache_Compiler::compile
     *
     * @param string $source
     *
     * @return string generated Mustache template class code
     */
    private function compile($source)
    {
        $tree = $this->parse($source);
        $name = $this->getTemplateClassName($source);

        $this->log(
            Mustache_Logger::INFO,
            'Compiling template to "{className}" class',
            array('className' => $name)
        );

        return $this->getCompiler()->compile($source, $tree, $name, isset($this->escape), $this->charset, $this->strictCallables, $this->entityFlags);
    }

    /**
     * Helper method to generate a Mustache Template class cache filename.
     *
     * @param string $source
     *
     * @return string Mustache Template class cache filename
     */
    private function getCacheFilename($source)
    {
        if ($this->cache) {
            return sprintf('%s/%s.php', $this->cache, $this->getTemplateClassName($source));
        }
    }

    /**
     * Helper method to dump a generated Mustache Template subclass to the file cache.
     *
     * @throws Mustache_Exception_RuntimeException if unable to create the cache directory or write to $fileName.
     *
     * @param string $fileName
     * @param string $source
     *
     * @codeCoverageIgnore
     */
    private function writeCacheFile($fileName, $source)
    {
        $dirName = dirname($fileName);
        if (!is_dir($dirName)) {
            $this->log(
                Mustache_Logger::INFO,
                'Creating Mustache template cache directory: "{dirName}"',
                array('dirName' => $dirName)
            );

            @mkdir($dirName, 0777, true);
            if (!is_dir($dirName)) {
                throw new Mustache_Exception_RuntimeException(sprintf('Failed to create cache directory "%s".', $dirName));
            }

        }

        $this->log(
            Mustache_Logger::DEBUG,
            'Caching compiled template to "{fileName}"',
            array('fileName' => $fileName)
        );

        $tempFile = tempnam($dirName, basename($fileName));
        if (false !== @file_put_contents($tempFile, $source)) {
            if (@rename($tempFile, $fileName)) {
                $mode = isset($this->cacheFileMode) ? $this->cacheFileMode : (0666 & ~umask());
                @chmod($fileName, $mode);

                return;
            }

            $this->log(
                Mustache_Logger::ERROR,
                'Unable to rename Mustache temp cache file: "{tempName}" -> "{fileName}"',
                array('tempName' => $tempFile, 'fileName' => $fileName)
            );
        }

        throw new Mustache_Exception_RuntimeException(sprintf('Failed to write cache file "%s".', $fileName));
    }

    /**
     * Add a log record if logging is enabled.
     *
     * @param  integer $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     */
    private function log($level, $message, array $context = array())
    {
        if (isset($this->logger)) {
            $this->logger->log($level, $message, $context);
        }
    }
}

/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A Mustache Exception interface.
 */
interface Mustache_Exception
{
    // This space intentionally left blank.
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A collection of helpers for a Mustache instance.
 */
class Mustache_HelperCollection
{
    private $helpers = array();

    /**
     * Helper Collection constructor.
     *
     * Optionally accepts an array (or Traversable) of `$name => $helper` pairs.
     *
     * @throws Mustache_Exception_InvalidArgumentException if the $helpers argument isn't an array or Traversable
     *
     * @param array|Traversable $helpers (default: null)
     */
    public function __construct($helpers = null)
    {
        if ($helpers !== null) {
            if (!is_array($helpers) && !$helpers instanceof Traversable) {
                throw new Mustache_Exception_InvalidArgumentException('HelperCollection constructor expects an array of helpers');
            }

            foreach ($helpers as $name => $helper) {
                $this->add($name, $helper);
            }
        }
    }

    /**
     * Magic mutator.
     *
     * @see Mustache_HelperCollection::add
     *
     * @param string $name
     * @param mixed  $helper
     */
    public function __set($name, $helper)
    {
        $this->add($name, $helper);
    }

    /**
     * Add a helper to this collection.
     *
     * @param string $name
     * @param mixed  $helper
     */
    public function add($name, $helper)
    {
        $this->helpers[$name] = $helper;
    }

    /**
     * Magic accessor.
     *
     * @see Mustache_HelperCollection::get
     *
     * @param string $name
     *
     * @return mixed Helper
     */
    public function __get($name)
    {
        return $this->get($name);
    }

    /**
     * Get a helper by name.
     *
     * @throws Mustache_Exception_UnknownHelperException If helper does not exist.
     *
     * @param string $name
     *
     * @return mixed Helper
     */
    public function get($name)
    {
        if (!$this->has($name)) {
            throw new Mustache_Exception_UnknownHelperException($name);
        }

        return $this->helpers[$name];
    }

    /**
     * Magic isset().
     *
     * @see Mustache_HelperCollection::has
     *
     * @param string $name
     *
     * @return boolean True if helper is present
     */
    public function __isset($name)
    {
        return $this->has($name);
    }

    /**
     * Check whether a given helper is present in the collection.
     *
     * @param string $name
     *
     * @return boolean True if helper is present
     */
    public function has($name)
    {
        return array_key_exists($name, $this->helpers);
    }

    /**
     * Magic unset().
     *
     * @see Mustache_HelperCollection::remove
     *
     * @param string $name
     */
    public function __unset($name)
    {
        $this->remove($name);
    }

    /**
     * Check whether a given helper is present in the collection.
     *
     * @throws Mustache_Exception_UnknownHelperException if the requested helper is not present.
     *
     * @param string $name
     */
    public function remove($name)
    {
        if (!$this->has($name)) {
            throw new Mustache_Exception_UnknownHelperException($name);
        }

        unset($this->helpers[$name]);
    }

    /**
     * Clear the helper collection.
     *
     * Removes all helpers from this collection
     */
    public function clear()
    {
        $this->helpers = array();
    }

    /**
     * Check whether the helper collection is empty.
     *
     * @return boolean True if the collection is empty
     */
    public function isEmpty()
    {
        return empty($this->helpers);
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Lambda Helper.
 *
 * Passed as the second argument to section lambdas (higher order sections),
 * giving them access to a `render` method for rendering a string with the
 * current context.
 */
class Mustache_LambdaHelper
{
    private $mustache;
    private $context;

    /**
     * Mustache Lambda Helper constructor.
     *
     * @param Mustache_Engine  $mustache Mustache engine instance.
     * @param Mustache_Context $context  Rendering context.
     */
    public function __construct(Mustache_Engine $mustache, Mustache_Context $context)
    {
        $this->mustache = $mustache;
        $this->context  = $context;
    }

    /**
     * Render a string as a Mustache template with the current rendering context.
     *
     * @param string $string
     *
     * @return Rendered template.
     */
    public function render($string)
    {
        return $this->mustache
            ->loadLambda((string) $string)
            ->renderInternal($this->context);
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Template Loader interface.
 */
interface Mustache_Loader
{

    /**
     * Load a Template by name.
     *
     * @throws Mustache_Exception_UnknownTemplateException If a template file is not found.
     *
     * @param string $name
     *
     * @return string Mustache Template source
     */
    public function load($name);
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Describes a Mustache logger instance
 *
 * This is identical to the Psr\Log\LoggerInterface.
 *
 * The message MUST be a string or object implementing __toString().
 *
 * The message MAY contain placeholders in the form: {foo} where foo
 * will be replaced by the context data in key "foo".
 *
 * The context array can contain arbitrary data, the only assumption that
 * can be made by implementors is that if an Exception instance is given
 * to produce a stack trace, it MUST be in a key named "exception".
 *
 * See https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-3-logger-interface.md
 * for the full interface specification.
 */
interface Mustache_Logger
{
    /**
     * Psr\Log compatible log levels
     */
    const EMERGENCY = 'emergency';
    const ALERT     = 'alert';
    const CRITICAL  = 'critical';
    const ERROR     = 'error';
    const WARNING   = 'warning';
    const NOTICE    = 'notice';
    const INFO      = 'info';
    const DEBUG     = 'debug';

    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function emergency($message, array $context = array());

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function alert($message, array $context = array());

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function critical($message, array $context = array());

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function error($message, array $context = array());

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function warning($message, array $context = array());

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function notice($message, array $context = array());

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function info($message, array $context = array());

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     * @return null
     */
    public function debug($message, array $context = array());

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     * @return null
     */
    public function log($level, $message, array $context = array());
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Parser class.
 *
 * This class is responsible for turning a set of Mustache tokens into a parse tree.
 */
class Mustache_Parser
{
    private $lineNum;
    private $lineTokens;

    /**
     * Process an array of Mustache tokens and convert them into a parse tree.
     *
     * @param array $tokens Set of Mustache tokens
     *
     * @return array Mustache token parse tree
     */
    public function parse(array $tokens = array())
    {
        $this->lineNum    = -1;
        $this->lineTokens = 0;

        return $this->buildTree($tokens);
    }

    /**
     * Helper method for recursively building a parse tree.
     *
     * @throws Mustache_Exception_SyntaxException when nesting errors or mismatched section tags are encountered.
     *
     * @param array &$tokens Set of Mustache tokens
     * @param array  $parent Parent token (default: null)
     *
     * @return array Mustache Token parse tree
     */
    private function buildTree(array &$tokens, array $parent = null)
    {
        $nodes = array();

        while (!empty($tokens)) {
            $token = array_shift($tokens);

            if ($token[Mustache_Tokenizer::LINE] === $this->lineNum) {
                $this->lineTokens++;
            } else {
                $this->lineNum    = $token[Mustache_Tokenizer::LINE];
                $this->lineTokens = 0;
            }

            switch ($token[Mustache_Tokenizer::TYPE]) {
                case Mustache_Tokenizer::T_DELIM_CHANGE:
                    $this->clearStandaloneLines($nodes, $tokens);
                    break;

                case Mustache_Tokenizer::T_SECTION:
                case Mustache_Tokenizer::T_INVERTED:
                    $this->clearStandaloneLines($nodes, $tokens);
                    $nodes[] = $this->buildTree($tokens, $token);
                    break;

                case Mustache_Tokenizer::T_END_SECTION:
                    if (!isset($parent)) {
                        $msg = sprintf('Unexpected closing tag: /%s', $token[Mustache_Tokenizer::NAME]);
                        throw new Mustache_Exception_SyntaxException($msg, $token);
                    }

                    if ($token[Mustache_Tokenizer::NAME] !== $parent[Mustache_Tokenizer::NAME]) {
                        $msg = sprintf('Nesting error: %s vs. %s', $parent[Mustache_Tokenizer::NAME], $token[Mustache_Tokenizer::NAME]);
                        throw new Mustache_Exception_SyntaxException($msg, $token);
                    }

                    $this->clearStandaloneLines($nodes, $tokens);
                    $parent[Mustache_Tokenizer::END]   = $token[Mustache_Tokenizer::INDEX];
                    $parent[Mustache_Tokenizer::NODES] = $nodes;

                    return $parent;
                    break;

                case Mustache_Tokenizer::T_PARTIAL:
                case Mustache_Tokenizer::T_PARTIAL_2:
                    // store the whitespace prefix for laters!
                    if ($indent = $this->clearStandaloneLines($nodes, $tokens)) {
                        $token[Mustache_Tokenizer::INDENT] = $indent[Mustache_Tokenizer::VALUE];
                    }
                    $nodes[] = $token;
                    break;

                case Mustache_Tokenizer::T_PRAGMA:
                case Mustache_Tokenizer::T_COMMENT:
                    $this->clearStandaloneLines($nodes, $tokens);
                    $nodes[] = $token;
                    break;

                default:
                    $nodes[] = $token;
                    break;
            }
        }

        if (isset($parent)) {
            $msg = sprintf('Missing closing tag: %s', $parent[Mustache_Tokenizer::NAME]);
            throw new Mustache_Exception_SyntaxException($msg, $parent);
        }

        return $nodes;
    }

    /**
     * Clear standalone line tokens.
     *
     * Returns a whitespace token for indenting partials, if applicable.
     *
     * @param array  $nodes  Parsed nodes.
     * @param array  $tokens Tokens to be parsed.
     *
     * @return array Resulting indent token, if any.
     */
    private function clearStandaloneLines(array &$nodes, array &$tokens)
    {
        if ($this->lineTokens > 1) {
            // this is the third or later node on this line, so it can't be standalone
            return;
        }

        $prev = null;
        if ($this->lineTokens === 1) {
            // this is the second node on this line, so it can't be standalone
            // unless the previous node is whitespace.
            if ($prev = end($nodes)) {
                if (!$this->tokenIsWhitespace($prev)) {
                    return;
                }
            }
        }

        $next = null;
        if ($next = reset($tokens)) {
            // If we're on a new line, bail.
            if ($next[Mustache_Tokenizer::LINE] !== $this->lineNum) {
                return;
            }

            // If the next token isn't whitespace, bail.
            if (!$this->tokenIsWhitespace($next)) {
                return;
            }

            if (count($tokens) !== 1) {
                // Unless it's the last token in the template, the next token
                // must end in newline for this to be standalone.
                if (substr($next[Mustache_Tokenizer::VALUE], -1) !== "\n") {
                    return;
                }
            }

            // Discard the whitespace suffix
            array_shift($tokens);
        }

        if ($prev) {
            // Return the whitespace prefix, if any
            return array_pop($nodes);
        }
    }

    /**
     * Check whether token is a whitespace token.
     *
     * True if token type is T_TEXT and value is all whitespace characters.
     *
     * @param array $token
     *
     * @return boolean True if token is a whitespace token
     */
    private function tokenIsWhitespace(array $token)
    {
        if ($token[Mustache_Tokenizer::TYPE] == Mustache_Tokenizer::T_TEXT) {
            return preg_match('/^\s*$/', $token[Mustache_Tokenizer::VALUE]);
        }

        return false;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Abstract Mustache Template class.
 *
 * @abstract
 */
abstract class Mustache_Template
{

    /**
     * @var Mustache_Engine
     */
    protected $mustache;

    /**
     * @var boolean
     */
    protected $strictCallables = false;

    /**
     * Mustache Template constructor.
     *
     * @param Mustache_Engine $mustache
     */
    public function __construct(Mustache_Engine $mustache)
    {
        $this->mustache = $mustache;
    }

    /**
     * Mustache Template instances can be treated as a function and rendered by simply calling them:
     *
     *     $m = new Mustache_Engine;
     *     $tpl = $m->loadTemplate('Hello, {{ name }}!');
     *     echo $tpl(array('name' => 'World')); // "Hello, World!"
     *
     * @see Mustache_Template::render
     *
     * @param mixed $context Array or object rendering context (default: array())
     *
     * @return string Rendered template
     */
    public function __invoke($context = array())
    {
        return $this->render($context);
    }

    /**
     * Render this template given the rendering context.
     *
     * @param mixed $context Array or object rendering context (default: array())
     *
     * @return string Rendered template
     */
    public function render($context = array())
    {
        return $this->renderInternal($this->prepareContextStack($context));
    }

    /**
     * Internal rendering method implemented by Mustache Template concrete subclasses.
     *
     * This is where the magic happens :)
     *
     * NOTE: This method is not part of the Mustache.php public API.
     *
     * @param Mustache_Context $context
     * @param string           $indent  (default: '')
     *
     * @return string Rendered template
     */
    abstract public function renderInternal(Mustache_Context $context, $indent = '');

    /**
     * Tests whether a value should be iterated over (e.g. in a section context).
     *
     * In most languages there are two distinct array types: list and hash (or whatever you want to call them). Lists
     * should be iterated, hashes should be treated as objects. Mustache follows this paradigm for Ruby, Javascript,
     * Java, Python, etc.
     *
     * PHP, however, treats lists and hashes as one primitive type: array. So Mustache.php needs a way to distinguish
     * between between a list of things (numeric, normalized array) and a set of variables to be used as section context
     * (associative array). In other words, this will be iterated over:
     *
     *     $items = array(
     *         array('name' => 'foo'),
     *         array('name' => 'bar'),
     *         array('name' => 'baz'),
     *     );
     *
     * ... but this will be used as a section context block:
     *
     *     $items = array(
     *         1        => array('name' => 'foo'),
     *         'banana' => array('name' => 'bar'),
     *         42       => array('name' => 'baz'),
     *     );
     *
     * @param mixed $value
     *
     * @return boolean True if the value is 'iterable'
     */
    protected function isIterable($value)
    {
        if (is_object($value)) {
            return $value instanceof Traversable;
        } elseif (is_array($value)) {
            $i = 0;
            foreach ($value as $k => $v) {
                if ($k !== $i++) {
                    return false;
                }
            }

            return true;
        } else {
            return false;
        }
    }

    /**
     * Helper method to prepare the Context stack.
     *
     * Adds the Mustache HelperCollection to the stack's top context frame if helpers are present.
     *
     * @param mixed $context Optional first context frame (default: null)
     *
     * @return Mustache_Context
     */
    protected function prepareContextStack($context = null)
    {
        $stack = new Mustache_Context;

        $helpers = $this->mustache->getHelpers();
        if (!$helpers->isEmpty()) {
            $stack->push($helpers);
        }

        if (!empty($context)) {
            $stack->push($context);
        }

        return $stack;
    }

    /**
     * Resolve a context value.
     *
     * Invoke the value if it is callable, otherwise return the value.
     *
     * @param  mixed            $value
     * @param  Mustache_Context $context
     * @param  string           $indent
     *
     * @return string
     */
    protected function resolveValue($value, Mustache_Context $context, $indent = '')
    {
        if (($this->strictCallables ? is_object($value) : !is_string($value)) && is_callable($value)) {
            return $this->mustache
                ->loadLambda((string) call_user_func($value))
                ->renderInternal($context, $indent);
        }

        return $value;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Tokenizer class.
 *
 * This class is responsible for turning raw template source into a set of Mustache tokens.
 */
class Mustache_Tokenizer
{

    // Finite state machine states
    const IN_TEXT     = 0;
    const IN_TAG_TYPE = 1;
    const IN_TAG      = 2;

    // Token types
    const T_SECTION      = '#';
    const T_INVERTED     = '^';
    const T_END_SECTION  = '/';
    const T_COMMENT      = '!';
    const T_PARTIAL      = '>';
    const T_PARTIAL_2    = '<';
    const T_DELIM_CHANGE = '=';
    const T_ESCAPED      = '_v';
    const T_UNESCAPED    = '{';
    const T_UNESCAPED_2  = '&';
    const T_TEXT         = '_t';
    const T_PRAGMA       = '%';

    // Valid token types
    private static $tagTypes = array(
        self::T_SECTION      => true,
        self::T_INVERTED     => true,
        self::T_END_SECTION  => true,
        self::T_COMMENT      => true,
        self::T_PARTIAL      => true,
        self::T_PARTIAL_2    => true,
        self::T_DELIM_CHANGE => true,
        self::T_ESCAPED      => true,
        self::T_UNESCAPED    => true,
        self::T_UNESCAPED_2  => true,
        self::T_PRAGMA       => true,
    );

    // Interpolated tags
    private static $interpolatedTags = array(
        self::T_ESCAPED      => true,
        self::T_UNESCAPED    => true,
        self::T_UNESCAPED_2  => true,
    );

    // Token properties
    const TYPE   = 'type';
    const NAME   = 'name';
    const OTAG   = 'otag';
    const CTAG   = 'ctag';
    const LINE   = 'line';
    const INDEX  = 'index';
    const END    = 'end';
    const INDENT = 'indent';
    const NODES  = 'nodes';
    const VALUE  = 'value';

    private $state;
    private $tagType;
    private $tag;
    private $buffer;
    private $tokens;
    private $seenTag;
    private $line;
    private $otag;
    private $ctag;

    /**
     * Scan and tokenize template source.
     *
     * @param string $text       Mustache template source to tokenize
     * @param string $delimiters Optionally, pass initial opening and closing delimiters (default: null)
     *
     * @return array Set of Mustache tokens
     */
    public function scan($text, $delimiters = null)
    {
        $this->reset();

        if ($delimiters = trim($delimiters)) {
            list($otag, $ctag) = explode(' ', $delimiters);
            $this->otag = $otag;
            $this->ctag = $ctag;
        }

        $len = strlen($text);
        for ($i = 0; $i < $len; $i++) {
            switch ($this->state) {
                case self::IN_TEXT:
                    if ($this->tagChange($this->otag, $text, $i)) {
                        $i--;
                        $this->flushBuffer();
                        $this->state = self::IN_TAG_TYPE;
                    } else {
                        $char = substr($text, $i, 1);
                        $this->buffer .= $char;
                        if ($char == "\n") {
                            $this->flushBuffer();
                            $this->line++;
                        }
                    }
                    break;

                case self::IN_TAG_TYPE:
                    $i += strlen($this->otag) - 1;
                    $char = substr($text, $i + 1, 1);
                    if (isset(self::$tagTypes[$char])) {
                        $tag = $char;
                        $this->tagType = $tag;
                    } else {
                        $tag = null;
                        $this->tagType = self::T_ESCAPED;
                    }

                    if ($this->tagType === self::T_DELIM_CHANGE) {
                        $i = $this->changeDelimiters($text, $i);
                        $this->state = self::IN_TEXT;
                    } elseif ($this->tagType === self::T_PRAGMA) {
                        $i = $this->addPragma($text, $i);
                        $this->state = self::IN_TEXT;
                    } else {
                        if ($tag !== null) {
                            $i++;
                        }
                        $this->state = self::IN_TAG;
                    }
                    $this->seenTag = $i;
                    break;

                default:
                    if ($this->tagChange($this->ctag, $text, $i)) {
                        $this->tokens[] = array(
                            self::TYPE  => $this->tagType,
                            self::NAME  => trim($this->buffer),
                            self::OTAG  => $this->otag,
                            self::CTAG  => $this->ctag,
                            self::LINE  => $this->line,
                            self::INDEX => ($this->tagType == self::T_END_SECTION) ? $this->seenTag - strlen($this->otag) : $i + strlen($this->ctag)
                        );

                        $this->buffer = '';
                        $i += strlen($this->ctag) - 1;
                        $this->state = self::IN_TEXT;
                        if ($this->tagType == self::T_UNESCAPED) {
                            if ($this->ctag == '}}') {
                                $i++;
                            } else {
                                // Clean up `{{{ tripleStache }}}` style tokens.
                                $lastName = $this->tokens[count($this->tokens) - 1][self::NAME];
                                if (substr($lastName, -1) === '}') {
                                    $this->tokens[count($this->tokens) - 1][self::NAME] = trim(substr($lastName, 0, -1));
                                }
                            }
                        }
                    } else {
                        $this->buffer .= substr($text, $i, 1);
                    }
                    break;
            }
        }

        $this->flushBuffer();

        return $this->tokens;
    }

    /**
     * Helper function to reset tokenizer internal state.
     */
    private function reset()
    {
        $this->state     = self::IN_TEXT;
        $this->tagType   = null;
        $this->tag       = null;
        $this->buffer    = '';
        $this->tokens    = array();
        $this->seenTag   = false;
        $this->line      = 0;
        $this->otag      = '{{';
        $this->ctag      = '}}';
    }

    /**
     * Flush the current buffer to a token.
     */
    private function flushBuffer()
    {
        if (!empty($this->buffer)) {
            $this->tokens[] = array(
                self::TYPE  => self::T_TEXT,
                self::LINE  => $this->line,
                self::VALUE => $this->buffer
            );
            $this->buffer   = '';
        }
    }

    /**
     * Change the current Mustache delimiters. Set new `otag` and `ctag` values.
     *
     * @param string $text  Mustache template source
     * @param int    $index Current tokenizer index
     *
     * @return int New index value
     */
    private function changeDelimiters($text, $index)
    {
        $startIndex = strpos($text, '=', $index) + 1;
        $close      = '='.$this->ctag;
        $closeIndex = strpos($text, $close, $index);

        list($otag, $ctag) = explode(' ', trim(substr($text, $startIndex, $closeIndex - $startIndex)));
        $this->otag = $otag;
        $this->ctag = $ctag;

        $this->tokens[] = array(
            self::TYPE => self::T_DELIM_CHANGE,
            self::LINE => $this->line,
        );

        return $closeIndex + strlen($close) - 1;
    }

    /**
     * Add pragma token.
     *
     * Pragmas are hoisted to the front of the template, so all pragma tokens
     * will appear at the front of the token list.
     *
     * @param string $text
     * @param int    $index
     *
     * @return int New index value
     */
    private function addPragma($text, $index)
    {
        $end    = strpos($text, $this->ctag, $index);
        $pragma = trim(substr($text, $index + 2, $end - $index - 2));

        // Pragmas are hoisted to the front of the template.
        array_unshift($this->tokens, array(
            self::TYPE => self::T_PRAGMA,
            self::NAME => $pragma,
            self::LINE => 0,
        ));

        return $end + strlen($this->ctag) - 1;
    }

    /**
     * Test whether it's time to change tags.
     *
     * @param string $tag   Current tag name
     * @param string $text  Mustache template source
     * @param int    $index Current tokenizer index
     *
     * @return boolean True if this is a closing section tag
     */
    private function tagChange($tag, $text, $index)
    {
        return substr($text, $index, strlen($tag)) === $tag;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Invalid argument exception.
 */
class Mustache_Exception_InvalidArgumentException extends InvalidArgumentException implements Mustache_Exception
{
    // This space intentionally left blank.
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Logic exception.
 */
class Mustache_Exception_LogicException extends LogicException implements Mustache_Exception
{
    // This space intentionally left blank.
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Runtime exception.
 */
class Mustache_Exception_RuntimeException extends RuntimeException implements Mustache_Exception
{
    // This space intentionally left blank.
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache syntax exception.
 */
class Mustache_Exception_SyntaxException extends LogicException implements Mustache_Exception
{
    protected $token;

    public function __construct($msg, array $token)
    {
        $this->token = $token;
        parent::__construct($msg);
    }

    public function getToken()
    {
        return $this->token;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Unknown filter exception.
 */
class Mustache_Exception_UnknownFilterException extends UnexpectedValueException implements Mustache_Exception
{
    protected $filterName;

    public function __construct($filterName)
    {
        $this->filterName = $filterName;
        parent::__construct(sprintf('Unknown filter: %s', $filterName));
    }

    public function getFilterName()
    {
        return $this->filterName;
    }
}



/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Unknown helper exception.
 */
class Mustache_Exception_UnknownHelperException extends InvalidArgumentException implements Mustache_Exception
{
    protected $helperName;

    public function __construct($helperName)
    {
        $this->helperName = $helperName;
        parent::__construct(sprintf('Unknown helper: %s', $helperName));
    }

    public function getHelperName()
    {
        return $this->helperName;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Unknown template exception.
 */
class Mustache_Exception_UnknownTemplateException extends InvalidArgumentException implements Mustache_Exception
{
    protected $templateName;

    public function __construct($templateName)
    {
        $this->templateName = $templateName;
        parent::__construct(sprintf('Unknown template: %s', $templateName));
    }

    public function getTemplateName()
    {
        return $this->templateName;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Template array Loader implementation.
 *
 * An ArrayLoader instance loads Mustache Template source by name from an initial array:
 *
 *     $loader = new ArrayLoader(
 *         'foo' => '{{ bar }}',
 *         'baz' => 'Hey {{ qux }}!'
 *     );
 *
 *     $tpl = $loader->load('foo'); // '{{ bar }}'
 *
 * The ArrayLoader is used internally as a partials loader by Mustache_Engine instance when an array of partials
 * is set. It can also be used as a quick-and-dirty Template loader.
 */
class Mustache_Loader_ArrayLoader implements Mustache_Loader, Mustache_Loader_MutableLoader
{

    /**
     * ArrayLoader constructor.
     *
     * @param array $templates Associative array of Template source (default: array())
     */
    public function __construct(array $templates = array())
    {
        $this->templates = $templates;
    }

    /**
     * Load a Template.
     *
     * @throws Mustache_Exception_UnknownTemplateException If a template file is not found.
     *
     * @param string $name
     *
     * @return string Mustache Template source
     */
    public function load($name)
    {
        if (!isset($this->templates[$name])) {
            throw new Mustache_Exception_UnknownTemplateException($name);
        }

        return $this->templates[$name];
    }

    /**
     * Set an associative array of Template sources for this loader.
     *
     * @param array $templates
     */
    public function setTemplates(array $templates)
    {
        $this->templates = $templates;
    }

    /**
     * Set a Template source by name.
     *
     * @param string $name
     * @param string $template Mustache Template source
     */
    public function setTemplate($name, $template)
    {
        $this->templates[$name] = $template;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A Mustache Template cascading loader implementation, which delegates to other
 * Loader instances.
 */
class Mustache_Loader_CascadingLoader implements Mustache_Loader
{
    private $loaders;

    /**
     * Construct a CascadingLoader with an array of loaders:
     *
     *     $loader = new Mustache_Loader_CascadingLoader(array(
     *         new Mustache_Loader_InlineLoader(__FILE__, __COMPILER_HALT_OFFSET__),
     *         new Mustache_Loader_FilesystemLoader(__DIR__.'/templates')
     *     ));
     *
     * @param array $loaders An array of Mustache Loader instances
     */
    public function __construct(array $loaders = array())
    {
        $this->loaders = array();
        foreach ($loaders as $loader) {
            $this->addLoader($loader);
        }
    }

    /**
     * Add a Loader instance.
     *
     * @param Mustache_Loader $loader A Mustache Loader instance
     */
    public function addLoader(Mustache_Loader $loader)
    {
        $this->loaders[] = $loader;
    }

    /**
     * Load a Template by name.
     *
     * @throws Mustache_Exception_UnknownTemplateException If a template file is not found.
     *
     * @param string $name
     *
     * @return string Mustache Template source
     */
    public function load($name)
    {
        foreach ($this->loaders as $loader) {
            try {
                return $loader->load($name);
            } catch (Mustache_Exception_UnknownTemplateException $e) {
                // do nothing, check the next loader.
            }
        }

        throw new Mustache_Exception_UnknownTemplateException($name);
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Template filesystem Loader implementation.
 *
 * A FilesystemLoader instance loads Mustache Template source from the filesystem by name:
 *
 *     $loader = new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views');
 *     $tpl = $loader->load('foo'); // equivalent to `file_get_contents(dirname(__FILE__).'/views/foo.mustache');
 *
 * This is probably the most useful Mustache Loader implementation. It can be used for partials and normal Templates:
 *
 *     $m = new Mustache(array(
 *          'loader'          => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views'),
 *          'partials_loader' => new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views/partials'),
 *     ));
 */
class Mustache_Loader_FilesystemLoader implements Mustache_Loader
{
    private $baseDir;
    private $extension = '.mustache';
    private $templates = array();

    /**
     * Mustache filesystem Loader constructor.
     *
     * Passing an $options array allows overriding certain Loader options during instantiation:
     *
     *     $options = array(
     *         // The filename extension used for Mustache templates. Defaults to '.mustache'
     *         'extension' => '.ms',
     *     );
     *
     * @throws Mustache_Exception_RuntimeException if $baseDir does not exist.
     *
     * @param string $baseDir Base directory containing Mustache template files.
     * @param array  $options Array of Loader options (default: array())
     */
    public function __construct($baseDir, array $options = array())
    {
        $this->baseDir = $baseDir;

        if (strpos($this->baseDir, '://') === -1) {
            $this->baseDir = realpath($this->baseDir);
        }

        if (!is_dir($this->baseDir)) {
            throw new Mustache_Exception_RuntimeException(sprintf('FilesystemLoader baseDir must be a directory: %s', $baseDir));
        }

        if (array_key_exists('extension', $options)) {
            if (empty($options['extension'])) {
                $this->extension = '';
            } else {
                $this->extension = '.' . ltrim($options['extension'], '.');
            }
        }
    }

    /**
     * Load a Template by name.
     *
     *     $loader = new Mustache_Loader_FilesystemLoader(dirname(__FILE__).'/views');
     *     $loader->load('admin/dashboard'); // loads "./views/admin/dashboard.mustache";
     *
     * @param string $name
     *
     * @return string Mustache Template source
     */
    public function load($name)
    {
        if (!isset($this->templates[$name])) {
            $this->templates[$name] = $this->loadFile($name);
        }

        return $this->templates[$name];
    }

    /**
     * Helper function for loading a Mustache file by name.
     *
     * @throws Mustache_Exception_UnknownTemplateException If a template file is not found.
     *
     * @param string $name
     *
     * @return string Mustache Template source
     */
    protected function loadFile($name)
    {
        $fileName = $this->getFileName($name);

        if (!file_exists($fileName)) {
            throw new Mustache_Exception_UnknownTemplateException($name);
        }

        return file_get_contents($fileName);
    }

    /**
     * Helper function for getting a Mustache template file name.
     *
     * @param string $name
     *
     * @return string Template file name
     */
    protected function getFileName($name)
    {
        $fileName = $this->baseDir . '/' . $name;
        if (substr($fileName, 0 - strlen($this->extension)) !== $this->extension) {
            $fileName .= $this->extension;
        }

        return $fileName;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2013 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A Mustache Template loader for inline templates.
 *
 * With the InlineLoader, templates can be defined at the end of any PHP source
 * file:
 *
 *     $loader  = new Mustache_Loader_InlineLoader(__FILE__, __COMPILER_HALT_OFFSET__);
 *     $hello   = $loader->load('hello');
 *     $goodbye = $loader->load('goodbye');
 *
 *     __halt_compiler();
 *
 *     @@ hello
 *     Hello, {{ planet }}!
 *
 *     @@ goodbye
 *     Goodbye, cruel {{ planet }}
 *
 * Templates are deliniated by lines containing only `@@ name`.
 *
 * The InlineLoader is well-suited to micro-frameworks such as Silex:
 *
 *     $app->register(new MustacheServiceProvider, array(
 *         'mustache.loader' => new Mustache_Loader_InlineLoader(__FILE__, __COMPILER_HALT_OFFSET__)
 *     ));
 *
 *     $app->get('/{name}', function($name) use ($app) {
 *         return $app['mustache']->render('hello', compact('name'));
 *     })
 *     ->value('name', 'world');
 *
 *     // ...
 *
 *     __halt_compiler();
 *
 *     @@ hello
 *     Hello, {{ name }}!
 *
 */
class Mustache_Loader_InlineLoader implements Mustache_Loader
{
    protected $fileName;
    protected $offset;
    protected $templates;

    /**
     * The InlineLoader requires a filename and offset to process templates.
     * The magic constants `__FILE__` and `__COMPILER_HALT_OFFSET__` are usually
     * perfectly suited to the job:
     *
     *     $loader = new Mustache_Loader_InlineLoader(__FILE__, __COMPILER_HALT_OFFSET__);
     *
     * Note that this only works if the loader is instantiated inside the same
     * file as the inline templates. If the templates are located in another
     * file, it would be necessary to manually specify the filename and offset.
     *
     * @param string $fileName The file to parse for inline templates
     * @param int    $offset   A string offset for the start of the templates.
     *                         This usually coincides with the `__halt_compiler`
     *                         call, and the `__COMPILER_HALT_OFFSET__`.
     */
    public function __construct($fileName, $offset)
    {
        if (!is_file($fileName)) {
            throw new Mustache_Exception_InvalidArgumentException('InlineLoader expects a valid filename.');
        }

        if (!is_int($offset) || $offset < 0) {
            throw new Mustache_Exception_InvalidArgumentException('InlineLoader expects a valid file offset.');
        }

        $this->fileName = $fileName;
        $this->offset   = $offset;
    }

    /**
     * Load a Template by name.
     *
     * @throws Mustache_Exception_UnknownTemplateException If a template file is not found.
     *
     * @param string $name
     *
     * @return string Mustache Template source
     */
    public function load($name)
    {
        $this->loadTemplates();

        if (!array_key_exists($name, $this->templates)) {
            throw new Mustache_Exception_UnknownTemplateException($name);
        }

        return $this->templates[$name];
    }

    /**
     * Parse and load templates from the end of a source file.
     */
    protected function loadTemplates()
    {
        if ($this->templates === null) {
            $this->templates = array();
            $data = file_get_contents($this->fileName, false, null, $this->offset);
            foreach (preg_split("/^@@(?= [\w\d\.]+$)/m", $data, -1) as $chunk) {
                if (trim($chunk)) {
                    list($name, $content)         = explode("\n", $chunk, 2);
                    $this->templates[trim($name)] = trim($content);
                }
            }
        }
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Template mutable Loader interface.
 */
interface Mustache_Loader_MutableLoader
{

    /**
     * Set an associative array of Template sources for this loader.
     *
     * @param array $templates
     */
    public function setTemplates(array $templates);

    /**
     * Set a Template source by name.
     *
     * @param string $name
     * @param string $template Mustache Template source
     */
    public function setTemplate($name, $template);
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * Mustache Template string Loader implementation.
 *
 * A StringLoader instance is essentially a noop. It simply passes the 'name' argument straight through:
 *
 *     $loader = new StringLoader;
 *     $tpl = $loader->load('{{ foo }}'); // '{{ foo }}'
 *
 * This is the default Template Loader instance used by Mustache:
 *
 *     $m = new Mustache;
 *     $tpl = $m->loadTemplate('{{ foo }}');
 *     echo $tpl->render(array('foo' => 'bar')); // "bar"
 */
class Mustache_Loader_StringLoader implements Mustache_Loader
{

    /**
     * Load a Template by source.
     *
     * @param string $name Mustache Template source
     *
     * @return string Mustache Template source
     */
    public function load($name)
    {
        return $name;
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * This is a simple Logger implementation that other Loggers can inherit from.
 *
 * This is identical to the Psr\Log\AbstractLogger.
 *
 * It simply delegates all log-level-specific methods to the `log` method to
 * reduce boilerplate code that a simple Logger that does the same thing with
 * messages regardless of the error level has to implement.
 */
abstract class Mustache_Logger_AbstractLogger implements Mustache_Logger
{
    /**
     * System is unusable.
     *
     * @param string $message
     * @param array $context
     */
    public function emergency($message, array $context = array())
    {
        $this->log(Mustache_Logger::EMERGENCY, $message, $context);
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param string $message
     * @param array $context
     */
    public function alert($message, array $context = array())
    {
        $this->log(Mustache_Logger::ALERT, $message, $context);
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param string $message
     * @param array $context
     */
    public function critical($message, array $context = array())
    {
        $this->log(Mustache_Logger::CRITICAL, $message, $context);
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param string $message
     * @param array $context
     */
    public function error($message, array $context = array())
    {
        $this->log(Mustache_Logger::ERROR, $message, $context);
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string $message
     * @param array $context
     */
    public function warning($message, array $context = array())
    {
        $this->log(Mustache_Logger::WARNING, $message, $context);
    }

    /**
     * Normal but significant events.
     *
     * @param string $message
     * @param array $context
     */
    public function notice($message, array $context = array())
    {
        $this->log(Mustache_Logger::NOTICE, $message, $context);
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param string $message
     * @param array $context
     */
    public function info($message, array $context = array())
    {
        $this->log(Mustache_Logger::INFO, $message, $context);
    }

    /**
     * Detailed debug information.
     *
     * @param string $message
     * @param array $context
     */
    public function debug($message, array $context = array())
    {
        $this->log(Mustache_Logger::DEBUG, $message, $context);
    }
}


/*
 * This file is part of Mustache.php.
 *
 * (c) 2012 Justin Hileman
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

/**
 * A Mustache Stream Logger.
 *
 * The Stream Logger wraps a file resource instance (such as a stream) or a
 * stream URL. All log messages over the threshold level will be appended to
 * this stream.
 *
 * Hint: Try `php://stderr` for your stream URL.
 */
class Mustache_Logger_StreamLogger extends Mustache_Logger_AbstractLogger
{
    protected static $levels = array(
        self::DEBUG     => 100,
        self::INFO      => 200,
        self::NOTICE    => 250,
        self::WARNING   => 300,
        self::ERROR     => 400,
        self::CRITICAL  => 500,
        self::ALERT     => 550,
        self::EMERGENCY => 600,
    );

    protected $stream = null;
    protected $url    = null;

    /**
     * @throws InvalidArgumentException if the logging level is unknown.
     *
     * @param string  $stream Resource instance or URL
     * @param integer $level  The minimum logging level at which this handler will be triggered
     */
    public function __construct($stream, $level = Mustache_Logger::ERROR)
    {
        $this->setLevel($level);

        if (is_resource($stream)) {
            $this->stream = $stream;
        } else {
            $this->url = $stream;
        }
    }

    /**
     * Close stream resources.
     */
    public function __destruct()
    {
        if (is_resource($this->stream)) {
            fclose($this->stream);
        }
    }

    /**
     * Set the minimum logging level.
     *
     * @throws Mustache_Exception_InvalidArgumentException if the logging level is unknown.
     *
     * @param  integer $level The minimum logging level which will be written
     */
    public function setLevel($level)
    {
        if (!array_key_exists($level, self::$levels)) {
            throw new Mustache_Exception_InvalidArgumentException(sprintf('Unexpected logging level: %s', $level));
        }

        $this->level = $level;
    }

    /**
     * Get the current minimum logging level.
     *
     * @return integer
     */
    public function getLevel()
    {
        return $this->level;
    }

    /**
     * Logs with an arbitrary level.
     *
     * @throws Mustache_Exception_InvalidArgumentException if the logging level is unknown.
     *
     * @param mixed $level
     * @param string $message
     * @param array $context
     */
    public function log($level, $message, array $context = array())
    {
        if (!array_key_exists($level, self::$levels)) {
            throw new Mustache_Exception_InvalidArgumentException(sprintf('Unexpected logging level: %s', $level));
        }

        if (self::$levels[$level] >= self::$levels[$this->level]) {
            $this->writeLog($level, $message, $context);
        }
    }

    /**
     * Write a record to the log.
     *
     * @throws Mustache_Exception_LogicException   If neither a stream resource nor url is present.
     * @throws Mustache_Exception_RuntimeException If the stream url cannot be opened.
     *
     * @param  integer $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     */
    protected function writeLog($level, $message, array $context = array())
    {
        if (!is_resource($this->stream)) {
            if (!isset($this->url)) {
                throw new Mustache_Exception_LogicException('Missing stream url, the stream can not be opened. This may be caused by a premature call to close().');
            }

            $this->stream = fopen($this->url, 'a');
            if (!is_resource($this->stream)) {
                // @codeCoverageIgnoreStart
                throw new Mustache_Exception_RuntimeException(sprintf('The stream or file "%s" could not be opened.', $this->url));
                // @codeCoverageIgnoreEnd
            }
        }

        fwrite($this->stream, self::formatLine($level, $message, $context));
    }

    /**
     * Gets the name of the logging level.
     *
     * @throws InvalidArgumentException if the logging level is unknown.
     *
     * @param  integer $level
     *
     * @return string
     */
    protected static function getLevelName($level)
    {
        return strtoupper($level);
    }

    /**
     * Format a log line for output.
     *
     * @param  integer $level   The logging level
     * @param  string  $message The log message
     * @param  array   $context The log context
     *
     * @return string
     */
    protected static function formatLine($level, $message, array $context = array())
    {
        return sprintf(
            "%s: %s\n",
            self::getLevelName($level),
            self::interpolateMessage($message, $context)
        );
    }

    /**
     * Interpolate context values into the message placeholders.
     *
     * @param  string $message
     * @param  array  $context
     *
     * @return string
     */
    protected static function interpolateMessage($message, array $context = array())
    {
        if (strpos($message, '{') === false) {
            return $message;
        }

        // build a replacement array with braces around the context keys
        $replace = array();
        foreach ($context as $key => $val) {
            $replace['{' . $key . '}'] = $val;
        }

        // interpolate replacement values into the the message and return
        return strtr($message, $replace);
    }
}
