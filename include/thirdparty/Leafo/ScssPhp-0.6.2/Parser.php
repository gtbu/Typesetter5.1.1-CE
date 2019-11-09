<?php
/**
 * SCSSPHP
 *
 * @copyright 2012-2015 Leaf Corcoran
 *
 * @license http://opensource.org/licenses/MIT MIT
 *
 * @link http://leafo.github.io/scssphp
 */

namespace Leafo\ScssPhp;

use Leafo\ScssPhp\Block;
use Leafo\ScssPhp\Compiler;
use Leafo\ScssPhp\Exception\ParserException;
use Leafo\ScssPhp\Node;
use Leafo\ScssPhp\Type;

/**
 * Parser
 *
 * @author Leaf Corcoran <leafot@gmail.com>
 */
class Parser
{
    const SOURCE_INDEX  = -1;
    const SOURCE_LINE   = -2;
    const SOURCE_COLUMN = -3;

    /**
     * @var array
     */
    protected static $precedence = array(
        '='   => 0,
        'or'  => 1,
        'and' => 2,
        '=='  => 3,
        '!='  => 3,
        '<=>' => 3,
        '<='  => 4,
        '>='  => 4,
        '<'   => 4,
        '>'   => 4,
        '+'   => 5,
        '-'   => 5,
        '*'   => 6,
        '/'   => 6,
        '%'   => 6,
    );

    protected static $commentPattern;
    protected static $mCommentPattern;
    protected static $operatorPattern;


    private $sourceName;
    private $sourceIndex;
    private $sourcePositions;
    private $charset;
    private $count;
    private $env;
    private $inParens;
    private $eatWhiteDefault;
    private $buffer;
    private $utf8;
    private $encoding;
    private $patternModifiers = 'Ais';
    private $patternKeywords;
    private $patternPlaceholder;


    /**
     * Constructor
     *
     * @api
     *
     * @param string  $sourceName
     * @param integer $sourceIndex
     * @param string  $encoding
     */
    public function __construct($sourceName, $sourceIndex = 0, $encoding = 'utf-8')
    {
        $this->sourceName  = $sourceName ?: '(stdin)';
        $this->sourceIndex = $sourceIndex;
        $this->charset     = null;
        $this->utf8        = ! $encoding || strtolower($encoding) === 'utf-8';

        if ($this->utf8) {
            $this->patternModifiers = 'Aisu';
            $this->patternKeywords = '(([\pL\w_\-\*!"\']|[\\\\].)([\pL\w\-_"\']|[\\\\].)*)';
			$this->patternPlaceholder = '([\pL\w\-_]+|#[{][$][\pL\w\-_]+[}])';
        }else{
			$this->patternKeywords = '(([\w_\-\*!"\']|[\\\\].)([\w\-_"\']|[\\\\].)*)';
			$this->patternPlaceholder = '([\w\-_]+|#[{][$][\w\-_]+[}])';
		}

        if (empty(self::$operatorPattern)) {
            self::$operatorPattern = '([*\/%+-]|[!=]\=|\>\=?|\<\=\>|\<\=?|and|or)';

            $commentSingle      = '\/\/';
            $commentMultiLeft   = '\/\*';
            $commentMultiRight  = '\*\/';

            self::$commentPattern = $commentMultiLeft . '.*?' . $commentMultiRight;
            self::$mCommentPattern = '/'.self::$commentPattern.'/'.$this->patternModifiers.'S';
        }
    }

    /**
     * Get source file name
     *
     * @api
     *
     * @return string
     */
    public function getSourceName()
    {
        return $this->sourceName;
    }

    /**
     * Throw parser error
     *
     * @api
     *
     * @param string $msg
     *
     * @throws \Leafo\ScssPhp\Exception\ParserException
     */
    public function throwParseError($msg = 'parse error')
    {
        list($line, /* $column */) = $this->getSourcePosition($this->count);

        $loc = empty($this->sourceName) ? "line: $line" : "$this->sourceName on line $line";

        if ($this->peek("(.*?)(\n|$)", $m, $this->count)) {
            throw new ParserException("$msg: failed at `$m[1]` $loc");
        }

        throw new ParserException("$msg: $loc");
    }

    /**
     * Parser buffer
     *
     * @api
     *
     * @param string $buffer
     *
     * @return \Leafo\ScssPhp\Block
     */
    public function parse($buffer){

        $this->count           = 0;
        $this->env             = null;
        $this->inParens        = false;
        $this->eatWhiteDefault = true;
        $this->buffer          = rtrim($buffer, "\x00..\x1f");
        $this->buffer_len      = strlen($this->buffer);

        $this->saveEncoding();
        $this->extractLineNumbers($buffer);

        $this->pushBlock(null); // root block
        $this->whitespace();
        $this->pushBlock(null);
        $this->popBlock();

        while ($this->parseChunk()) {
            ;
        }

        if( $this->count !== $this->buffer_len ){
            $this->throwParseError();
        }

        if (! empty($this->env->parent)) {
            $this->throwParseError('unclosed block');
        }

        if ($this->charset) {
            array_unshift($this->env->children, $this->charset);
        }

        $this->env->isRoot    = true;

        $this->restoreEncoding();

        return $this->env;
    }

    /**
     * Parse a value or value list
     *
     * @api
     *
     * @param string $buffer
     * @param string $out
     *
     * @return boolean
     */
    public function parseValue($buffer, &$out)
    {
        $this->count           = 0;
        $this->env             = null;
        $this->inParens        = false;
        $this->eatWhiteDefault = true;
        $this->buffer          = (string) $buffer;

        $this->saveEncoding();

        $list = $this->valueList($out);

        $this->restoreEncoding();

        return $list;
    }

    /**
     * Parse a selector or selector list
     *
     * @api
     *
     * @param string $buffer
     * @param string $out
     *
     * @return boolean
     */
    public function parseSelector($buffer, &$out)
    {
        $this->count           = 0;
        $this->env             = null;
        $this->inParens        = false;
        $this->eatWhiteDefault = true;
        $this->buffer          = (string) $buffer;
        $this->buffer_len      = strlen($this->buffer);

        $this->saveEncoding();

        $selector = $this->selectors($out);

        $this->restoreEncoding();

        return $selector;
    }

    /**
     * Parse a single chunk off the head of the buffer and append it to the
     * current parse environment.
     *
     * Returns false when the buffer is empty, or when there is an error.
     *
     * This function is called repeatedly until the entire document is
     * parsed.
     *
     * This parser is most similar to a recursive descent parser. Single
     * functions represent discrete grammatical rules for the language, and
     * they are able to capture the text that represents those rules.
     *
     * Consider the function Compiler::keyword(). (All parse functions are
     * structured the same.)
     *
     * The function takes a single reference argument. When calling the
     * function it will attempt to match a keyword on the head of the buffer.
     * If it is successful, it will place the keyword in the referenced
     * argument, advance the position in the buffer, and return true. If it
     * fails then it won't advance the buffer and it will return false.
     *
     * All of these parse functions are powered by Compiler::match(), which behaves
     * the same way, but takes a literal regular expression. Sometimes it is
     * more convenient to use match instead of creating a new function.
     *
     * Because of the format of the functions, to parse an entire string of
     * grammatical rules, you can chain them together using &&.
     *
     * But, if some of the rules in the chain succeed before one fails, then
     * the buffer position will be left at an invalid state. In order to
     * avoid this, Compiler::seek() is used to remember and set buffer positions.
     *
     * Before parsing a chain, use $s = $this->count to remember the current
     * position into $s. Then if a chain fails, use $this->seek($s) to
     * go back where we started.
     *
     * @return boolean
     */
    protected function parseChunk(){

		if( !isset($this->buffer[$this->count]) ){
			return false;
		}

        $s = $this->count;
        $char = $this->buffer[$this->count];

		//directives
        if( $char === '@') {
			return $this->parseDirective();
		}


        // misc
        if( $char === '-' && $this->literal('-->',3) ){
            return true;
        }

        if( $char === '<' && $this->literal('<!--',4) ){
            return true;
		}

        // extra stuff
        if( $char === ';' ){
			$this->count++;
			$this->whitespace();
			return true;
        }


        // closing a block
        if( $char === '}' ){
            $this->count++;
            $this->whitespace();
            $block = $this->popBlock();

            if (isset($block->type) && $block->type === Type::T_INCLUDE) {
                $include = $block->child;
                unset($block->child);
                $include[3] = $block;
                $this->append($include, $s);
            } elseif (empty($block->dontAppend)) {
                $type = isset($block->type) ? $block->type : Type::T_BLOCK;
                $this->append(array($type, $block), $s);
            }

            return true;
        }

        // variable assigns
        if( $char === '$' && $this->variable($name) && $this->matchChar(':') && $this->valueList($value) && $this->end() ){
            // check for '!flag'
            $assignmentFlag = $this->stripAssignmentFlag($value);
            $this->append(array(Type::T_ASSIGN, $name, $value, $assignmentFlag), $s);
            return true;
        }

        $this->seek($s);


		// opening css block
        if( $this->ExpectSelector() ){
			if( $this->selectors($selectors) && $this->matchChar('{') ){
				$this->pushBlock($selectors, $s);
				return true;
			}

			$this->seek($s);
		}


        // property shortcut
        if( $this->keywordChar($char,$name, false) && $this->matchChar(':') && $this->valueList($value) && $this->end() ){
            $this->append(array(Type::T_ASSIGN, $name, $value), $s);
            return true;
        }

        $this->seek($s);


        // property assign, or nested assign
        if( $this->propertyName($name) && $this->matchChar(':') ){
            $foundSomething = false;

            if( $this->valueList($value) ){
                $this->append(array(Type::T_ASSIGN, $name, $value), $s);
                $foundSomething = true;
            }

            if( $this->matchChar('{') ){
                $propBlock = $this->pushSpecialBlock(Type::T_NESTED_PROPERTY, $s);
                $propBlock->prefix = $name;
                $foundSomething = true;
            }elseif( $foundSomething ){
                $foundSomething = $this->end();
            }

            if( $foundSomething ){
                return true;
            }
        }

        $this->seek($s);

        return false;
    }


    /**
     * Quickly check to see if we need to try to parse selectors
     *
     */
    protected function ExpectSelector(){

		$next_close = strpos($this->buffer,'}',$this->count);

		//block opening
		if( strpos($this->buffer,'{',$this->count) < $next_close ){
			return true;
		}

		// check for comments
		// ex: /* } */
		if( strpos($this->buffer,'/*',$this->count) < $next_close ){
			return true;
		}

		return false;
    }


    /**
     * Parse Directive
     *
     */
    protected function parseDirective(){

		$s = $this->count;

		//get directive name
		$pattern = '@[a-zA-Z\-]+';
		$this->match($pattern, $directive);
		$directive = strtolower($directive[0]);
		$ss = $this->count;


		// @at-root
		if ($directive === '@at-root' && ($this->selectors($selector) || true) && ($this->map($with) || true) &&	$this->matchChar('{')) {
			$atRoot = $this->pushSpecialBlock(Type::T_AT_ROOT, $s);
			$atRoot->selector = $selector;
			$atRoot->with = $with;

			return true;
		}

		$this->seek($ss);


		// @media
		if ($directive === '@media' && $this->mediaQueryList($mediaQueryList) && $this->matchChar('{')) {
			$media = $this->pushSpecialBlock(Type::T_MEDIA, $s);
			$media->queryList = $mediaQueryList[2];
			return true;
		}

		$this->seek($ss);


		// @mixin
		if ($directive === '@mixin' && $this->keyword($mixinName) && ($this->argumentDef($args) || true) &&	$this->matchChar('{')) {
			$mixin = $this->pushSpecialBlock(Type::T_MIXIN, $s);
			$mixin->name = $mixinName;
			$mixin->args = $args;
			return true;
		}

		$this->seek($ss);


		// @include
		if ( $directive === '@include' &&
			$this->keyword($mixinName) &&
			($this->matchChar('(') &&
				($this->argValues($argValues) || true) &&
				$this->matchChar(')') || true) &&
			($this->end() ||
				$this->matchChar('{') && $hasBlock = true)
		) {
			$child = array(Type::T_INCLUDE, $mixinName, isset($argValues) ? $argValues : null, null);

			if (! empty($hasBlock)) {
				$include = $this->pushSpecialBlock(Type::T_INCLUDE, $s);
				$include->child = $child;
			} else {
				$this->append($child, $s);
			}

			return true;
		}

		$this->seek($ss);


		// @scssphp-import-once
		if ($directive === '@scssphp-import-once' && $this->valueList($importPath) && $this->end()) {
			$this->append(array(Type::T_SCSSPHP_IMPORT_ONCE, $importPath), $s);
			return true;
		}

		$this->seek($ss);


		// @import
		if ($directive === '@import' && $this->valueList($importPath) && $this->end()) {
			$this->append(array(Type::T_IMPORT, $importPath), $s);
			return true;
		}

		$this->seek($ss);


		// @import
		if ($directive === '@import' && $this->url($importPath) && $this->end()) {
			$this->append(array(Type::T_IMPORT, $importPath), $s);
			return true;
		}

		$this->seek($ss);


		// @extend
		if ($directive === '@extend' && $this->selectors($selectors) && $this->end()) {
			// check for '!flag'
			$optional = $this->stripOptionalFlag($selectors);
			$this->append(array(Type::T_EXTEND, $selectors, $optional), $s);
			return true;
		}

		$this->seek($ss);


		// @function
		if ($directive === '@function' && $this->keyword($fnName) && $this->argumentDef($args) && $this->matchChar('{')) {
			$func = $this->pushSpecialBlock(Type::T_FUNCTION, $s);
			$func->name = $fnName;
			$func->args = $args;
			return true;
		}

		$this->seek($ss);


		// @break
		if ($directive === '@break' && $this->end()) {
			$this->append(array(Type::T_BREAK), $s);
			return true;
		}

		$this->seek($ss);

		// @continue
		if ($directive === '@continue' && $this->end()) {
			$this->append(array(Type::T_CONTINUE), $s);
			return true;
		}

		$this->seek($ss);


		// @return
		if ($directive === '@return' && ($this->valueList($retVal) || true) && $this->end()) {
			$this->append(array(Type::T_RETURN, isset($retVal) ? $retVal : array(Type::T_NULL)), $s);
			return true;
		}

		$this->seek($ss);


		// @each
		if ($directive === '@each' &&
			$this->genericList($varNames, 'variable', ',', false) &&
			$this->literal('in',2) &&
			$this->valueList($list) &&
			$this->matchChar('{')
		) {
			$each = $this->pushSpecialBlock(Type::T_EACH, $s);

			foreach ($varNames[2] as $varName) {
				$each->vars[] = $varName[1];
			}

			$each->list = $list;

			return true;
		}

		$this->seek($ss);

		// @while
		if ($directive === '@while' && $this->expression($cond) && $this->matchChar('{')) {
			$while = $this->pushSpecialBlock(Type::T_WHILE, $s);
			$while->cond = $cond;
			return true;
		}

		$this->seek($ss);


		// @for
		if ($directive === '@for' &&
			$this->variable($varName) &&
			$this->literal('from',4) &&
			$this->expression($start) &&
			($this->literal('through',7) ||
				($forUntil = true && $this->literal('to',2))) &&
			$this->expression($end) &&
			$this->matchChar('{')
		) {
			$for = $this->pushSpecialBlock(Type::T_FOR, $s);
			$for->var = $varName[1];
			$for->start = $start;
			$for->end = $end;
			$for->until = isset($forUntil);

			return true;
		}

		$this->seek($ss);



		// @if
		if ($directive === '@if' && $this->valueList($cond) && $this->matchChar('{')) {
			$if = $this->pushSpecialBlock(Type::T_IF, $s);
			$if->cond = $cond;
			$if->cases = array();
			return true;
		}

		$this->seek($ss);


		// @debug
		if ($directive === '@debug' && $this->valueList($value) && $this->end() ) {
			$this->append(array(Type::T_DEBUG, $value), $s);
			return true;
		}

		$this->seek($ss);


		// @warn
		if ($directive === '@warn' && $this->valueList($value) && $this->end()) {
			$this->append(array(Type::T_WARN, $value), $s);
			return true;
		}

		$this->seek($ss);


		// @error
		if ( $directive === '@error' && $this->valueList($value) && $this->end()) {
			$this->append(array(Type::T_ERROR, $value), $s);
			return true;
		}

		$this->seek($ss);


		// @content
		if ( $directive === '@content' && $this->end()) {
			$this->append(array(Type::T_MIXIN_CONTENT), $s);
			return true;
		}

		$this->seek($ss);



		// @else, @elseif, @else if
		$last = $this->last();
		if (isset($last) && $last[0] === Type::T_IF) {
			list(, $if) = $last;

			if( $directive === '@else' && $this->matchChar('{') ){
				$else = $this->pushSpecialBlock(Type::T_ELSE, $s);

			}elseif( ($directive === '@elseif' || $this->literal('if',2))
				&& $this->valueList($cond)
				&& $this->matchChar('{')) {
					$else = $this->pushSpecialBlock(Type::T_ELSEIF, $s);
					$else->cond = $cond;
			}

			if (isset($else)) {
				$else->dontAppend = true;
				$if->cases[] = $else;

				return true;
			}

			$this->seek($ss);
		}


		// only retain the first @charset directive encountered
		if ( $directive == '@charset' && $this->valueList($charset) && $this->end()	) {
			if (! isset($this->charset)) {
				$statement = array(Type::T_CHARSET, $charset);

				list($line, $column) = $this->getSourcePosition($s);

				$statement[self::SOURCE_LINE]   = $line;
				$statement[self::SOURCE_COLUMN] = $column;
				$statement[self::SOURCE_INDEX]  = $this->sourceIndex;

				$this->charset = $statement;
			}

			return true;
		}


		$this->seek($s);

		// doesn't match built in directive, do generic one
		if ($this->matchChar('@', false) &&
			$this->keyword($dirName) &&
			($this->variable($dirValue) || $this->openString('{', $dirValue) || true) &&
			$this->matchChar('{')
		) {
			if ($dirName === 'media') {
				$directive = $this->pushSpecialBlock(Type::T_MEDIA, $s);
			} else {
				$directive = $this->pushSpecialBlock(Type::T_DIRECTIVE, $s);
				$directive->name = $dirName;
			}

			if (isset($dirValue)) {
				$directive->value = $dirValue;
			}

			return true;
		}

		$this->seek($s);

		return false;

	}

    /**
     * Push block onto parse tree
     *
     * @param array   $selectors
     * @param integer $pos
     *
     * @return \Leafo\ScssPhp\Block
     */
    protected function pushBlock($selectors, $pos = 0)
    {
        list($line, $column) = $this->getSourcePosition($pos);

        $b = new Block;
        $b->sourceLine   = $line;
        $b->sourceColumn = $column;
        $b->sourceIndex  = $this->sourceIndex;
        $b->selectors    = $selectors;
        $b->comments     = array();
        $b->parent       = $this->env;

        if (! $this->env) {
            $b->children = array();
        } elseif (empty($this->env->children)) {
            $this->env->children = $this->env->comments;
            $b->children = array();
            $this->env->comments = array();
        } else {
            $b->children = $this->env->comments;
            $this->env->comments = array();
        }

        $this->env = $b;

        return $b;
    }

    /**
     * Push special (named) block onto parse tree
     *
     * @param string  $type
     * @param integer $pos
     *
     * @return \Leafo\ScssPhp\Block
     */
    protected function pushSpecialBlock($type, $pos)
    {
        $block = $this->pushBlock(null, $pos);
        $block->type = $type;

        return $block;
    }

    /**
     * Pop scope and return last block
     *
     * @return \Leafo\ScssPhp\Block
     *
     * @throws \Exception
     */
    protected function popBlock()
    {
        $block = $this->env;

        if (empty($block->parent)) {
            $this->throwParseError('unexpected }');
        }

        $this->env = $block->parent;
        unset($block->parent);

        $comments = $block->comments;
        if ( $comments ) {
            $this->env->comments = $comments;
            unset($block->comments);
        }

        return $block;
    }

    /**
     * Peek input stream
     *
     * @param string  $regex
     * @param array   $out
     * @param integer $from
     *
     * @return integer
     */
    protected function peek($regex, &$out, $from = null)
    {
        if (! isset($from)) {
            $from = $this->count;
        }

        $r = '/' . $regex . '/'.$this->patternModifiers;
        $result = preg_match($r, $this->buffer, $out, null, $from);

        return $result;
    }

    /**
     * Seek to position in input stream (or return current position in input stream)
     *
     * @param integer $where
     *
     * @return integer
     */
    protected function seek($where)
    {
        $this->count = $where;
    }

    /**
     * Match string looking for either ending delim, escape, or string interpolation
     *
     * {@internal This is a workaround for preg_match's 250K string match limit. }}
     *
     * @param array  $m     Matches (passed by reference)
     * @param string $delim Delimeter
     *
     * @return boolean True if match; false otherwise
     */
    protected function matchString(&$m, $delim)
    {
        $token = null;

        $end = $this->buffer_len;

        // look for either ending delim, escape, or string interpolation
        foreach (array('#{', '\\', $delim) as $lookahead) {
            $pos = strpos($this->buffer, $lookahead, $this->count);

            if ($pos !== false && $pos < $end) {
                $end = $pos;
                $token = $lookahead;
            }
        }

        if (! isset($token)) {
            return false;
        }

        $match = substr($this->buffer, $this->count, $end - $this->count);
        $m = array(
            $match . $token,
            $match,
            $token
        );
        $this->count = $end + strlen($token);

        return true;
    }

    /**
     * Try to match something on head of buffer
     *
     * @param string  $regex
     * @param array   $out
     * @param boolean $eatWhitespace
     *
     * @return boolean
     */
    protected function match($regex, &$out, $eatWhitespace = null)
    {

        $r = '/' . $regex . '/'.$this->patternModifiers;

        if (!preg_match($r, $this->buffer, $out, null, $this->count)) {
			return false;
		}

		$this->count += strlen($out[0]);

        if (! isset($eatWhitespace)) {
            $eatWhitespace = $this->eatWhiteDefault;
        }

		if ($eatWhitespace) {
			$this->whitespace();
		}

		return true;
    }


    /**
     * Match a single string
     *
     * @param string  $char
     * @param boolean $eatWhitespace
     *
     * @return boolean
     */
    protected function matchChar($char, $eatWhitespace = null)
    {

        if( !isset($this->buffer[$this->count]) || $this->buffer[$this->count] !== $char) {
			return false;
		}

        $this->count++;

        if ( !isset($eatWhitespace) ) {
            $eatWhitespace = $this->eatWhiteDefault;
        }

		if ($eatWhitespace) {
			$this->whitespace();
		}
		return true;
    }


    /**
     * Match literal string
     *
     * @param string  $what
     * @param integer $len
     * @param boolean $eatWhitespace
     *
     * @return boolean
     */
    protected function literal($what, $len, $eatWhitespace = null)
    {

        if( substr($this->buffer,$this->count,$len) !== $what ){
			return false;
		}

		$this->count += $len;

        if (! isset($eatWhitespace)) {
            $eatWhitespace = $this->eatWhiteDefault;
        }

		if ($eatWhitespace) {
			$this->whitespace();
		}
		return true;
    }



    /**
     * Match some whitespace
     *
     * @return boolean
     */
    protected function whitespace()
    {
        $gotWhite = false;

        for(;;){
			if( !isset($this->buffer[$this->count]) ){
				break;
			}
			$char = $this->buffer[$this->count];


			//comment
			if( $char === '/' ){
				$char2 = $this->buffer[$this->count+1];


				if( $char2 === '/' && preg_match('/\/\/.*/', $this->buffer, $m, null, $this->count) ){
					$this->count += strlen($m[0]);
					$gotWhite = true;
					continue;
				}


				if( $char2 === '*' && preg_match(self::$mCommentPattern, $this->buffer, $m, null, $this->count) ){
					$this->appendComment($this->count,array(Type::T_COMMENT, $m[0]));
					$this->count += strlen($m[0]);
					$gotWhite = true;
					continue;
				}

				break;
			}


			if( ($char !== "\n") && ($char !== "\r") && ($char !== "\t") && ($char !== ' ') ){
				break;
			}
			$this->count++;
			$gotWhite = true;
		}


        return $gotWhite;
    }

    /**
     * Append comment to current block
     *
     * @param array $comment
     */
    protected function appendComment($position, $comment)
    {
        $comment[1] = substr(preg_replace(array('/^\s+/m', '/^(.)/m'), array('', ' \1'), $comment[1]), 1);

        $this->env->comments[$position] = $comment;
    }

    /**
     * Append statement to current block
     *
     * @param array   $statement
     * @param integer $pos
     */
    protected function append($statement, $pos = null)
    {
        if ($pos !== null) {
            list($line, $column) = $this->getSourcePosition($pos);

            $statement[self::SOURCE_LINE]   = $line;
            $statement[self::SOURCE_COLUMN] = $column;
            $statement[self::SOURCE_INDEX]  = $this->sourceIndex;
        }

        $this->env->children[] = $statement;

        $comments = $this->env->comments;

        if ( $comments ) {
            $this->env->children = array_merge($this->env->children, $comments);
            $this->env->comments = array();
        }
    }

    /**
     * Returns last child was appended
     *
     * @return array|null
     */
    protected function last()
    {
        $i = count($this->env->children) - 1;

        if (isset($this->env->children[$i])) {
            return $this->env->children[$i];
        }
    }

    /**
     * Parse media query list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mediaQueryList(&$out)
    {
        return $this->genericList($out, 'mediaQuery', ',', false);
    }

    /**
     * Parse media query
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mediaQuery(&$out)
    {
        $expressions = null;
        $parts = array();

        if (($this->literal('only',4) && ($only = true) || $this->literal('not',3) && ($not = true) || true) &&
            $this->mixedKeyword($mediaType)
        ) {
            $prop = array(Type::T_MEDIA_TYPE);

            if (isset($only)) {
                $prop[] = 'only'; //array(Type::T_KEYWORD, 'only');
            }

            if (isset($not)) {
                $prop[] = 'not'; //array(Type::T_KEYWORD, 'not');
            }

            $media = array(Type::T_LIST, '', array());

            foreach ((array) $mediaType as $type) {
                if (is_array($type)) {
                    $media[2][] = $type;
                } else {
                    $media[2][] = $type; //array(Type::T_KEYWORD, $type);
                }
            }

            $prop[]  = $media;
            $parts[] = $prop;
        }

        if (empty($parts) || $this->literal('and',3)) {
            $this->genericList($expressions, 'mediaExpression', 'and', false);

            if (is_array($expressions)) {
                $parts = array_merge($parts, $expressions[2]);
            }
        }

        $out = $parts;

        return true;
    }

    /**
     * Parse media expression
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mediaExpression(&$out)
    {
        $s = $this->count;
        $value = null;

        if ($this->matchChar('(') &&
            $this->expression($feature) &&
            ($this->matchChar(':') && $this->expression($value) || true) &&
            $this->matchChar(')')
        ) {
            $out = array(Type::T_MEDIA_EXPRESSION, $feature);

            if ($value) {
                $out[] = $value;
            }

            return true;
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse argument values
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argValues(&$out)
    {
        if ($this->genericList($list, 'argValue', ',', false)) {
            $out = $list[2];

            return true;
        }

        return false;
    }

    /**
     * Parse argument value
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argValue(&$out)
    {
        $s = $this->count;

        $keyword = null;

        if (! $this->variable($keyword) || ! $this->matchChar(':')) {
            $this->seek($s);
            $keyword = null;
        }

        if ($this->genericList($value, 'expression')) {
            $out = array($keyword, $value, false);
            $s = $this->count;

            if ($this->literal('...',3)) {
                $out[2] = true;
            } else {
                $this->seek($s);
            }

            return true;
        }

        return false;
    }

    /**
     * Parse comma separated value list
     *
     * @param string $out
     *
     * @return boolean
     */
    protected function valueList(&$out)
    {
        return $this->genericList($out, 'spaceList', ',');
    }

    /**
     * Parse space separated value list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function spaceList(&$out)
    {
        return $this->genericList($out, 'expression');
    }

    /**
     * Parse generic list
     *
     * @param array    $out
     * @param callable $parseItem
     * @param string   $delim
     * @param boolean  $flatten
     *
     * @return boolean
     */
    protected function genericList(&$out, $parseItem, $delim = '', $flatten = true)
    {
        $s = $this->count;
        $items = array();

        while ($this->$parseItem($value)) {
            $items[] = $value;

            if ($delim) {
                if (! $this->literal($delim, strlen($delim))) {
                    break;
                }
            }
        }

        if ( !$items ) {
            $this->seek($s);

            return false;
        }

        if ($flatten && count($items) === 1) {
            $out = $items[0];
        } else {
            $out = array(Type::T_LIST, $delim, $items);
        }

        return true;
    }

    /**
     * Parse expression
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function expression(&$out)
    {
        $s = $this->count;

        if ($this->matchChar('(')) {
            if ($this->matchChar(')')) {
                $out = array(Type::T_LIST, '', array());

                return true;
            }

            if ($this->valueList($out) && $this->matchChar(')') && $out[0] === Type::T_LIST) {
                return true;
            }

            $this->seek($s);

            if ($this->map($out)) {
                return true;
            }

            $this->seek($s);
        }

        if ($this->value($lhs)) {
            $out = $this->expHelper($lhs, 0);

            return true;
        }

        return false;
    }

    /**
     * Parse left-hand side of subexpression
     *
     * @param array   $lhs
     * @param integer $minP
     *
     * @return array
     */
    protected function expHelper($lhs, $minP)
    {

        $ss = $this->count;
        $whiteBefore = isset($this->buffer[$this->count - 1]) && ctype_space($this->buffer[$this->count - 1]);


        while ($this->match(self::$operatorPattern, $m, false) && self::$precedence[$m[1]] >= $minP) {
            $whiteAfter = isset($this->buffer[$this->count]) &&
                ctype_space($this->buffer[$this->count]);
            $varAfter = isset($this->buffer[$this->count]) &&
                $this->buffer[$this->count] === '$';

            $this->whitespace();

            $op = $m[1];

            // don't turn negative numbers into expressions
            if ($op === '-' && $whiteBefore && ! $whiteAfter && ! $varAfter) {
                break;
            }

            if (! $this->value($rhs)) {
                break;
            }

            // peek and see if rhs belongs to next operator
            if ($this->peek(self::$operatorPattern, $next) && self::$precedence[$next[1]] > self::$precedence[$op]) {
                $rhs = $this->expHelper($rhs, self::$precedence[$next[1]]);
            }

            $lhs = array(Type::T_EXPRESSION, $op, $lhs, $rhs, $this->inParens, $whiteBefore, $whiteAfter);
            $ss = $this->count;
            $whiteBefore = isset($this->buffer[$this->count - 1]) &&
                ctype_space($this->buffer[$this->count - 1]);
        }

        $this->seek($ss);

        return $lhs;
    }

    /**
     * Parse value
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function value(&$out)
    {

        if( !isset($this->buffer[$this->count]) ){
			return false;
		}

        $s = $this->count;
        $char = $this->buffer[$this->count];

		// not
        if( $char === 'n' && $this->literal('not', 3, false) ){

			if( $this->whitespace() && $this->value($inner)) {
				$out = array(Type::T_UNARY, 'not', $inner, $this->inParens);
				return true;
			}

			$this->seek($s);

			if ( $this->parenValue($inner)) {
				$out = array(Type::T_UNARY, 'not', $inner, $this->inParens);
				return true;
			}

			$this->seek($s);
        }

		// addition
        if ( $char === '+' ){
			$this->count++;
			if( $this->value($inner)) {
				$out = array(Type::T_UNARY, '+', $inner, $this->inParens);
				return true;
			}
			$this->count--;
			return false;
        }


        // negation
        if( $char === '-' ){
			$this->count++;
			if( $this->variable($inner) || $this->unit('1', $inner) || $this->parenValue($inner) ){
				$out = array(Type::T_UNARY, '-', $inner, $this->inParens);
				return true;
			}
			$this->count--;
		}

		// paren
		if( $char === '(' && $this->parenValue($out) ){
			return true;
		}

		if( $char === '#' ){

			if( $this->interpolation($out) || $this->color($out) ){
				return true;
			}
		}

		if( $char === '$' && $this->variable($out) ){
			return true;
		}

		if( $char === 'p' && $this->progid($out) ){
			return true;
		}

		if( ($char === '"' || $char === "'") && $this->string($out) ){
			return true;
		}


        if ( $this->unit($char, $out) ) {
            return true;
        }

        if ($this->keywordChar($char, $keyword, false)) {

			if( $this->func($keyword, $out) ){
				return true;
			}

			$this->whitespace();

            if ($keyword === 'null') {
                $out = array(Type::T_NULL);
            } else {
                $out = $keyword; //array(Type::T_KEYWORD, $keyword);
            }

            return true;
        }

        return false;
    }

    /**
     * Parse parenthesized value
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function parenValue(&$out)
    {
        $s = $this->count;

        $inParens = $this->inParens;

        if ($this->matchChar('(')) {
            if ($this->matchChar(')')) {
                $out = array(Type::T_LIST, '', array());

                return true;
            }

            $this->inParens = true;

            if ($this->expression($exp) && $this->matchChar(')')) {
                $out = $exp;
                $this->inParens = $inParens;

                return true;
            }
        }

        $this->inParens = $inParens;
        $this->seek($s);

        return false;
    }

    /**
     * Parse "progid:"
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function progid(&$out)
    {
        $s = $this->count;

        if ($this->literal('progid:', 7, false) &&
            $this->openString('(', $fn) &&
            $this->matchChar('(')
        ) {
            $this->openString(')', $args, '(');

            if ($this->matchChar(')')) {
                $out = array(Type::T_STRING, '', array(
                    'progid:', $fn, '(', $args, ')'
                ));

                return true;
            }
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse function call
     *
     * @param string $name
     * @param array $out
     *
     * @return boolean
     */
    protected function func($name, &$func)
    {
        $s = $this->count;

        if ( $this->matchChar('(') ) {

            if ($name === 'alpha' && $this->argumentList($args)) {
                $func = array(Type::T_FUNCTION, $name, array(Type::T_STRING, '', $args));

                return true;
            }

            if ($name !== 'expression' && ! preg_match('/^(-[a-z]+-)?calc$/', $name)) {
                $ss = $this->count;

                if ($this->argValues($args) && $this->matchChar(')')) {
                    $func = array(Type::T_FUNCTION_CALL, $name, $args);

                    return true;
                }

                $this->seek($ss);
            }

            if (($this->openString(')', $str, '(') || true) &&
                $this->matchChar(')')
            ) {
                $args = array();

                if (! empty($str)) {
                    $args[] = array(null, array(Type::T_STRING, '', array($str)));
                }

                $func = array(Type::T_FUNCTION_CALL, $name, $args);

                return true;
            }
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse function call argument list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argumentList(&$out)
    {
        $s = $this->count;
        $this->matchChar('(');

        $args = array();

        while ($this->keyword($var)) {
            if ($this->matchChar('=') && $this->expression($exp)) {
                $args[] = array(Type::T_STRING, '', array($var . '='));
                $arg = $exp;
            } else {
                break;
            }

            $args[] = $arg;

            if (! $this->matchChar(',')) {
                break;
            }

            $args[] = array(Type::T_STRING, '', array(', '));
        }

        if (! $this->matchChar(')') || !$args) {
            $this->seek($s);

            return false;
        }

        $out = $args;

        return true;
    }

    /**
     * Parse mixin/function definition  argument list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function argumentDef(&$out)
    {
        $s = $this->count;
        $this->matchChar('(');

        $args = array();

        while ($this->variable($var)) {
            $arg = array($var[1], null, false);

            $ss = $this->count;

            if ($this->matchChar(':') && $this->genericList($defaultVal, 'expression')) {
                $arg[1] = $defaultVal;
            } else {
                $this->seek($ss);
            }

            $ss = $this->count;

            if ($this->literal('...',3)) {
                $sss = $this->count;

                if (! $this->matchChar(')')) {
                    $this->throwParseError('... has to be after the final argument');
                }

                $arg[2] = true;
                $this->seek($sss);
            } else {
                $this->seek($ss);
            }

            $args[] = $arg;

            if (! $this->matchChar(',')) {
                break;
            }
        }

        if (! $this->matchChar(')')) {
            $this->seek($s);

            return false;
        }

        $out = $args;

        return true;
    }

    /**
     * Parse map
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function map(&$out)
    {
        $s = $this->count;

        if (! $this->matchChar('(')) {
            return false;
        }

        $keys = array();
        $values = array();

        while ($this->genericList($key, 'expression') && $this->matchChar(':') &&
            $this->genericList($value, 'expression')
        ) {
            $keys[] = $key;
            $values[] = $value;

            if (! $this->matchChar(',')) {
                break;
            }
        }

        if (!$keys || ! $this->matchChar(')')) {
            $this->seek($s);

            return false;
        }

        $out = array(Type::T_MAP, $keys, $values);

        return true;
    }

    /**
     * Parse color
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function color(&$out)
    {
        $color = array(Type::T_COLOR);

        if ($this->match('(#([0-9a-f]{6})|#([0-9a-f]{3}))', $m)) {
            if (isset($m[3])) {
                $num = hexdec($m[3]);

                foreach (array(3, 2, 1) as $i) {
                    $t = $num & 0xf;
                    $color[$i] = $t << 4 | $t;
                    $num >>= 4;
                }
            } else {
                $num = hexdec($m[2]);

                foreach (array(3, 2, 1) as $i) {
                    $color[$i] = $num & 0xff;
                    $num >>= 8;
                }
            }

            $out = $color;

            return true;
        }

        return false;
    }

    /**
     * Parse number with unit
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function unit($char, &$unit){

        if( !ctype_digit($char) && $char != '.' ){
			return false;
		}

        if ($this->match('([0-9]*(\.)?[0-9]+)([%a-zA-Z]+)?', $m)) {
            $unit = new Node\Number($m[1], empty($m[3]) ? '' : $m[3]);

            return true;
        }

        return false;
    }

    /**
     * Parse string
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function string(&$out)
    {
        $s = $this->count;

        if ($this->matchChar('"', false)) {
            $delim = '"';
        } elseif ($this->matchChar("'", false)) {
            $delim = "'";
        } else {
            return false;
        }

        $content = array();
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;
        $hasInterpolation = false;

        while ($this->matchString($m, $delim)) {
            if ($m[1] !== '') {
                $content[] = $m[1];
            }

            if ($m[2] === '#{') {
                $this->count -= strlen($m[2]);

                if ($this->interpolation($inter, false)) {
                    $content[] = $inter;
                    $hasInterpolation = true;
                } else {
                    $this->count += strlen($m[2]);
                    $content[] = '#{'; // ignore it
                }
            } elseif ($m[2] === '\\') {
                if ($this->matchChar('"', false)) {
                    $content[] = $m[2] . '"';
                } elseif ($this->matchChar("'", false)) {
                    $content[] = $m[2] . "'";
                } else {
                    $content[] = $m[2];
                }
            } else {
                $this->count -= strlen($delim);
                break; // delim
            }
        }

        $this->eatWhiteDefault = $oldWhite;

        if ($this->matchChar($delim)) {
            if ($hasInterpolation) {
                $delim = '"';

                foreach ($content as &$string) {
                    if ($string === "\\'") {
                        $string = "'";
                    } elseif ($string === '\\"') {
                        $string = '"';
                    }
                }
            }

            $out = array(Type::T_STRING, $delim, $content);

            return true;
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse keyword or interpolation
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function mixedKeyword(&$out)
    {
        $parts = array();

        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        for (;;) {
			$char = $this->buffer[$this->count];
            if ($this->keywordChar($char,$key)) {
                $parts[] = $key;
                continue;
            }

            if ($this->interpolation($inter)) {
                $parts[] = $inter;
                continue;
            }

            break;
        }

        $this->eatWhiteDefault = $oldWhite;

        if ( !$parts ) {
            return false;
        }

        if ($this->eatWhiteDefault) {
            $this->whitespace();
        }

        $out = $parts;

        return true;
    }

    /**
     * Parse an unbounded string stopped by $end
     *
     * @param string $end
     * @param array  $out
     * @param string $nestingOpen
     *
     * @return boolean
     */
    protected function openString($end, &$out, $nestingOpen = null)
    {
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        $patt = '(.*?)([\'"]|#\{|' . $this->pregQuote($end) . '|' . self::$commentPattern . ')';

        $nestingLevel = 0;

        $content = array();

        while ($this->match($patt, $m, false)) {
            if (isset($m[1]) && $m[1] !== '') {
                $content[] = $m[1];

                if ($nestingOpen) {
                    $nestingLevel += substr_count($m[1], $nestingOpen);
                }
            }

            $tok = $m[2];

            $this->count-= strlen($tok);

            if ($tok === $end && ! $nestingLevel--) {
                break;
            }

            if (($tok === "'" || $tok === '"') && $this->string($str)) {
                $content[] = $str;
                continue;
            }

            if ($tok === '#{' && $this->interpolation($inter)) {
                $content[] = $inter;
                continue;
            }

            $content[] = $tok;
            $this->count+= strlen($tok);
        }

        $this->eatWhiteDefault = $oldWhite;

        if ( !$content ) {
            return false;
        }

        // trim the end
        if (is_string(end($content))) {
            $content[count($content) - 1] = rtrim(end($content));
        }

        $out = array(Type::T_STRING, '', $content);

        return true;
    }

    /**
     * Parser interpolation
     *
     * @param array   $out
     * @param boolean $lookWhite save information about whitespace before and after
     *
     * @return boolean
     */
    protected function interpolation(&$out, $lookWhite = true){
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = true;

        $s = $this->count;

        if( $this->literal('#{',2) && $this->valueList($value) && $this->matchChar('}', false)) {
            if ($lookWhite) {
                $left = preg_match('/\s/', $this->buffer[$s - 1]) ? ' ' : '';
                $right = preg_match('/\s/', $this->buffer[$this->count]) ? ' ': '';
            } else {
                $left = $right = false;
            }

            $out = array(Type::T_INTERPOLATE, $value, $left, $right);
            $this->eatWhiteDefault = $oldWhite;

            if ($this->eatWhiteDefault) {
                $this->whitespace();
            }

            return true;
        }

        $this->seek($s);
        $this->eatWhiteDefault = $oldWhite;

        return false;
    }

    /**
     * Parse property name (as an array of parts or a string)
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function propertyName(&$out)
    {
        $parts = array();

        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        for (;;) {
            if ($this->interpolation($inter)) {
                $parts[] = $inter;
                continue;
            }

            if ($this->keyword($text)) {
                $parts[] = $text;
                continue;
            }

            if ( !$parts && $this->match('[:.#]', $m, false)) {
                // css hacks
                $parts[] = $m[0];
                continue;
            }

            break;
        }

        $this->eatWhiteDefault = $oldWhite;

        if ( !$parts ) {
            return false;
        }

        // match comment hack
        if( preg_match(self::$mCommentPattern,$this->buffer, $m, null, $this->count) ){
            if (! empty($m[0])) {
                $parts[] = $m[0];
                $this->count += strlen($m[0]);
            }
        }

        $this->whitespace(); // get any extra whitespace

        $out = array(Type::T_STRING, '', $parts);

        return true;
    }

    /**
     * Parse comma separated selector list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function selectors(&$selectors){

        $selectors = array();

        while( $this->selector($sel) ){
            $selectors[] = $sel;

            if( !$this->matchChar(',') ){
                break;
            }

            while( $this->matchChar(',') ){
                ; // ignore extra
            }
        }

        if( !$selectors ){
            return false;
        }

        return true;
    }

    /**
     * Parse whitespace separated selector list
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function selector(&$out)
    {
        $selector = array();

        for (;;) {

			if( !isset($this->buffer[$this->count]) ){
				break;
			}

			$char = $this->buffer[$this->count];
            if( $char === '>' && $this->buffer[$this->count+1] === '>' ){
                $selector[] = array('>>');
                $this->count += 2;
                $this->whitespace();
                continue;
            }

			if( $char === '>' || $char === '+' || $char === '~' ){
                $selector[] = array($char);
                $this->count++;
                $this->whitespace();
                continue;
            }

            if ($this->selectorSingle($part)) {
                $selector[] = $part;
                $this->match('\s+', $m);
                continue;
            }

            if ($char === '/' && $this->match('\/[^\/]+\/', $m)) {
                $selector[] = array($m[0]);
                continue;
            }

            break;
        }

        if ( !$selector ) {
            return false;
        }

        $out = $selector;
        return true;
    }

    /**
     * Parse the parts that make up a selector
     *
     * {@internal
     *     div[yes=no]#something.hello.world:nth-child(-2n+1)%placeholder
     * }}
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function selectorSingle(&$out){
        $oldWhite = $this->eatWhiteDefault;
        $this->eatWhiteDefault = false;

        $parts = array();

        if ($this->matchChar('*', false)) {
            $parts[] = '*';
        }

        for (;;) {

            if( !isset($this->buffer[$this->count]) ){
                break;
            }

            $s = $this->count;
            $char = $this->buffer[$this->count];

            // see if we can stop early
            if( $char === '{' || $char === ',' || $char === ';' || $char === '}' || $char === '@' ){
                break;
			}


            //self
            if( $char === '&' ){
				$parts[] = Compiler::$selfSelector;
				$this->count++;
				continue;
			}

			if( $char === '|' ){
				$parts[] = '|';
				$this->count++;
				continue;
            }


			//classes
            if( $char === '.' ){
				$this->count++;
				if( $this->keyword($name) ){
					$parts[] = '.'.$name;
					continue;
				}

                $parts[] = '.';
				continue;
			}


            if ($char === '\\' && $this->match('\\\\\S', $m)) {
                $parts[] = $m[0];
                continue;
            }


            if ($char === '%') {
                $this->count++;
                if( $this->placeholder($placeholder)) {
                    $parts[] = '%';
                    $parts[] = $placeholder;
                    continue;
                }
                break;
            }


			//id or interpolation
            if ($char === '#' ) {

				if ($this->interpolation($inter)) {
					$parts[] = $inter;
					continue;
				}

                $this->count++;

				if( $this->keyword($name) ){
					$parts[] = '#'.$name;
					continue;
				}

                $parts[] = '#';
                continue;
            }


            // a pseudo selector
            if( $char === ':' ){

				if( $this->buffer[$this->count+1] === ':' ){
					$this->count += 2;
					$part = '::';
				}else{
					$this->count++;
					$part = ':';
				}
				if( $this->mixedKeyword($nameParts) ){
					$parts[] = $part;

					foreach ($nameParts as $sub) {
						$parts[] = $sub;
					}

					$ss = $this->count;

					if ($this->matchChar('(') && ($this->openString(')', $str, '(') || true) && $this->matchChar(')')) {
						$parts[] = '(';

						if (! empty($str)) {
							$parts[] = $str;
						}

						$parts[] = ')';
					} else {
						$this->seek($ss);
					}

					continue;
				}

			}


            $this->seek($s);


            // attribute selector
            if ($char === '[' && $this->matchChar('[') && ($this->openString(']', $str, '[') || true) && $this->matchChar(']') ) {
                $parts[] = '[';

                if (! empty($str)) {
                    $parts[] = $str;
                }

                $parts[] = ']';

                continue;
            }

            $this->seek($s);


            // for keyframes
            if ($this->unit($char, $unit)) {
                $parts[] = $unit;
                continue;
            }

			if( $this->keywordChar($char, $name) ){
				$parts[] = $name;
				continue;
			}

            break;
        }

        $this->eatWhiteDefault = $oldWhite;

        if ( !$parts ) {
            return false;
        }


        $out = $parts;

        return true;
    }

    /**
     * Parse a variable
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function variable(&$out)
    {
        $s = $this->count;

        if ($this->matchChar('$', false) && $this->keyword($name)) {
            $out = array(Type::T_VARIABLE, $name);

            return true;
        }

        $this->seek($s);

        return false;
    }

    /**
     * Parse a keyword
     *
     * @param string  $word
     * @param boolean $eatWhitespace
     *
     * @return boolean
     */
    protected function keyword(&$word, $eatWhitespace = null)
    {
        if ($this->match($this->patternKeywords, $m, $eatWhitespace )) {
            $word = $m[1];
            return true;
        }

        return false;
    }

    protected function keywordChar($char, &$word, $eatWhitespace = null){
		if( $char == ':' || $char == '#' || $char == '>' || $char == ' ' || $char == ';' || $char == '(' || $char == ')'
			|| $char == ',' || $char == '{' || $char == '}' || $char == '.' || $char == '$' || $char == '&' || $char == '%' ){
			return false;
		}

        if ($this->match($this->patternKeywords, $m, $eatWhitespace )) {
            $word = $m[1];
            return true;
        }

        return false;
	}

    /**
     * Parse a placeholder
     *
     * @param string $placeholder
     *
     * @return boolean
     */
    protected function placeholder(&$placeholder)
    {
        if ($this->match($this->patternPlaceholder, $m)) {
            $placeholder = $m[1];

            return true;
        }

        return false;
    }

    /**
     * Parse a url
     *
     * @param array $out
     *
     * @return boolean
     */
    protected function url(&$out)
    {
        if ($this->match('(url\(\s*(["\']?)([^)]+)\2\s*\))', $m)) {
            $out = array(Type::T_STRING, '', array('url(' . $m[2] . $m[3] . $m[2] . ')'));

            return true;
        }

        return false;
    }

    /**
     * Consume an end of statement delimiter
     *
     * @return boolean
     */
    protected function end(){

		//end of the buffer
		if( $this->count === $this->buffer_len ){
			return true;
		}

        if( $this->buffer[$this->count] === '}' ){
            return true;
        }

        if( $this->matchChar(';') ){
            return true;
        }

        return false;
    }

    /**
     * Strip assignment flag from the list
     *
     * @param array $value
     *
     * @return string
     */
    protected function stripAssignmentFlag(&$value)
    {
        $token = &$value;

        for ($token = &$value; $token[0] === Type::T_LIST && ($s = count($token[2])); $token = &$lastNode) {
            $lastNode = &$token[2][$s - 1];


            if( is_string($lastNode) && in_array($lastNode,array('!default', '!global'))) {
            //if ($lastNode[0] === Type::T_KEYWORD && in_array($lastNode[1],array('!default', '!global'))) {
                array_pop($token[2]);

                $token = $this->flattenList($token);

                return $lastNode;
            }
        }

        return false;
    }

    /**
     * Strip optional flag from selector list
     *
     * @param array $selectors
     *
     * @return string
     */
    protected function stripOptionalFlag(&$selectors)
    {
        $optional = false;

        $selector = end($selectors);
        $part = end($selector);

        if ($part === array('!optional')) {
            array_pop($selectors[count($selectors) - 1]);

            $optional = true;
        }

        return $optional;
    }

    /**
     * Turn list of length 1 into value type
     *
     * @param array $value
     *
     * @return array
     */
    protected function flattenList($value)
    {
        if ($value[0] === Type::T_LIST && count($value[2]) === 1) {
            return $this->flattenList($value[2][0]);
        }

        return $value;
    }

    /**
     * @deprecated
     *
     * {@internal
     *     advance counter to next occurrence of $what
     *     $until - don't include $what in advance
     *     $allowNewline, if string, will be used as valid char set
     * }}
     */
    protected function to($what, &$out, $until = false, $allowNewline = false)
    {
        if (is_string($allowNewline)) {
            $validChars = $allowNewline;
        } else {
            $validChars = $allowNewline ? '.' : "[^\n]";
        }

        if (! $this->match('(' . $validChars . '*?)' . $this->pregQuote($what), $m, ! $until)) {
            return false;
        }

        if ($until) {
            $this->count -= strlen($what); // give back $what
        }

        $out = $m[1];

        return true;
    }

    /**
     * @deprecated
     */
    protected function show()
    {
        if ($this->peek("(.*?)(\n|$)", $m, $this->count)) {
            return $m[1];
        }

        return '';
    }

    /**
     * Quote regular expression
     *
     * @param string $what
     *
     * @return string
     */
    private function pregQuote($what)
    {
        return preg_quote($what, '/');
    }

    /**
     * Extract line numbers from buffer
     *
     * @param string $buffer
     */
    private function extractLineNumbers($buffer)
    {
        $this->sourcePositions = array(0 => 0);
        $prev = 0;

        while (($pos = strpos($buffer, "\n", $prev)) !== false) {
            $this->sourcePositions[] = $pos;
            $prev = $pos + 1;
        }

        $this->sourcePositions[] = strlen($buffer);

        if (substr($buffer, -1) !== "\n") {
            $this->sourcePositions[] = strlen($buffer) + 1;
        }
    }

    /**
     * Get source line number and column (given character position in the buffer)
     *
     * @param integer $pos
     *
     * @return integer
     */
    private function getSourcePosition($pos)
    {
        $low = 0;
        $high = count($this->sourcePositions);

        while ($low < $high) {
            $mid = (int) (($high + $low) / 2);

            if ($pos < $this->sourcePositions[$mid]) {
                $high = $mid - 1;
                continue;
            }

            if ($pos >= $this->sourcePositions[$mid + 1]) {
                $low = $mid + 1;
                continue;
            }

            return array($mid + 1, $pos - $this->sourcePositions[$mid]);
        }

        return array($low + 1, $pos - $this->sourcePositions[$low]);
    }

    /**
     * Save internal encoding
     */
    private function saveEncoding()
    {
        if (ini_get('mbstring.func_overload') & 2) {
            $this->encoding = mb_internal_encoding();

            mb_internal_encoding('iso-8859-1');
        }
    }

    /**
     * Restore internal encoding
     */
    private function restoreEncoding()
    {
        if ($this->encoding) {
            mb_internal_encoding($this->encoding);
        }
    }
}
