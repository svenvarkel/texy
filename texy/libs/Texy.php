<?php

/**
 * This file is part of the Texy! formatter (http://texy.info/)
 *
 * @author     David Grudl
 * @copyright  Copyright (c) 2004-2007 David Grudl aka -dgx- (http://www.dgx.cz)
 * @license    GNU GENERAL PUBLIC LICENSE version 2 or 3
 * @version    $Revision$ $Date$
 * @category   Text
 * @package    Texy
 */



/**
 * For PHP 4 backward compatibility
 */
define('TEXY_ALL',  Texy::ALL);
define('TEXY_NONE',  Texy::NONE);
define('TEXY_CONTENT_MARKUP', Texy::CONTENT_MARKUP);
define('TEXY_CONTENT_REPLACED', Texy::CONTENT_REPLACED);
define('TEXY_CONTENT_TEXTUAL', Texy::CONTENT_TEXTUAL);
define('TEXY_CONTENT_BLOCK', Texy::CONTENT_BLOCK);




/**
 * Texy! - Convert plain text to XHTML format using {@link process()}
 *
 * <code>
 *     $texy = new Texy();
 *     $html = $texy->process($text);
 * </code>
 */
class Texy extends TexyBase
{
    // configuration directives
    const ALL = TRUE;
    const NONE = FALSE;

    // Texy version
    const VERSION = TEXY_VERSION;

    // types of protection marks
    const CONTENT_MARKUP = "\x17";
    const CONTENT_REPLACED = "\x16";
    const CONTENT_TEXTUAL = "\x15";
    const CONTENT_BLOCK = "\x14";

    /** @var string  input & output text encoding */
    public $encoding = 'utf-8';

    /** @var array  Texy! syntax configuration */
    public $allowed = array();

     /** @var TRUE|FALSE|array  Allowed HTML tags */
    public $allowedTags;

    /** @var TRUE|FALSE|array  Allowed classes */
    public $allowedClasses = Texy::ALL; // all classes and id are allowed

    /** @var TRUE|FALSE|array  Allowed inline CSS style */
    public $allowedStyles = Texy::ALL;  // all inline styles are allowed

    /** @var int  TAB width (for converting tabs to spaces) */
    public $tabWidth = 8;

    /** @var boolean  Do obfuscate e-mail addresses? */
    public $obfuscateEmail = TRUE;

    /** @var array  regexps to check URL schemes */
    public $urlSchemeFilters = NULL; // disable URL scheme filter

    /** @var array  Parsing summary */
    public $summary = array(
        'images' => array(),
        'links' => array(),
        'preload' => array(),
    );

    /** @var string  Generated stylesheet */
    public $styleSheet = '';

    /** @var bool  use Strict of Transitional DTD? */
    public static $strictDTD = FALSE;

    /** @var bool  use XHTML syntax? */
    public $xhtml = TRUE;

    /** @var bool */
    static public $noticeShowed = FALSE;

    /** @var TexyScriptModule */
    public $scriptModule;

    /** @var TexyParagraphModule */
    public $paragraphModule;

    /** @var TexyHtmlModule */
    public $htmlModule;

    /** @var TexyImageModule */
    public $imageModule;

    /** @var TexyLinkModule */
    public $linkModule;

    /** @var TexyPhraseModule */
    public $phraseModule;

    /** @var TexyEmoticonModule */
    public $emoticonModule;

    /** @var TexyBlockModule */
    public $blockModule;

    /** @var TexyHeadingModule */
    public $headingModule;

    /** @var TexyHorizLineModule */
    public $horizLineModule;

    /** @var TexyQuoteModule */
    public $quoteModule;

    /** @var TexyListModule */
    public $listModule;

    /** @var TexyTableModule */
    public $tableModule;

    /** @var TexyFigureModule */
    public $figureModule;

    /** @var TexyTypographyModule */
    public $typographyModule;

    /** @var TexyLongWordsModule */
    public $longWordsModule;

    /** @var TexyHtmlOutputModule */
    public $htmlOutputModule;

    /** @var TexyHtmlOutputModule DEPRECATED */
    public $cleaner;

    /** @var bool  DEPRECATED */
    public $mergeLines;


    /**
     * Registered regexps and associated handlers for inline parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression)
     */
    private $linePatterns = array();
    private $_linePatterns;

    /**
     * Registered regexps and associated handlers for block parsing
     * @var array of ('handler' => callback
     *                'pattern' => regular expression)
     */
    private $blockPatterns = array();
    private $_blockPatterns;

    /** @var array */
    private $postHandlers = array();

    /** @var TexyDomElement  DOM structure for parsed text */
    private $DOM;

    /** @var array  Texy protect markup table */
    private $marks = array();

    /** @var array  for internal usage */
    public $_classes, $_styles;

    /** @var bool */
    private $processing;

    /** @var array of events and registered handlers */
    private $handlers = array();




    public function __construct()
    {
        // load all modules
        $this->loadModules();

        // DEPRECATED
        $this->cleaner = & $this->htmlOutputModule;
        $this->mergeLines = & $this->paragraphModule->mergeLines;

        // examples of link references ;-)
        $link = new TexyLink('http://texy.info/');
        $link->modifier->title = 'The best text -> HTML converter and formatter';
        $link->label = 'Texy!';
        $this->linkModule->addReference('texy', $link);

        $link = new TexyLink('http://www.google.com/search?q=%s');
        $this->linkModule->addReference('google', $link);

        $link = new TexyLink('http://en.wikipedia.org/wiki/Special:Search?search=%s');
        $this->linkModule->addReference('wikipedia', $link);
    }



    /**
     * Create array of all used modules ($this->modules)
     * This array can be changed by overriding this method (by subclasses)
     */
    protected function loadModules()
    {
        // line parsing
        $this->scriptModule = new TexyScriptModule($this);
        $this->htmlModule = new TexyHtmlModule($this);
        $this->imageModule = new TexyImageModule($this);
        $this->phraseModule = new TexyPhraseModule($this);
        $this->linkModule = new TexyLinkModule($this);
        $this->emoticonModule = new TexyEmoticonModule($this);

        // block parsing
        $this->paragraphModule = new TexyParagraphModule($this);
        $this->blockModule = new TexyBlockModule($this);
        $this->figureModule = new TexyFigureModule($this);
        $this->horizLineModule = new TexyHorizLineModule($this);
        $this->quoteModule = new TexyQuoteModule($this);
        $this->tableModule = new TexyTableModule($this);
        $this->headingModule = new TexyHeadingModule($this);
        $this->listModule = new TexyListModule($this);

        // post process
        $this->typographyModule = new TexyTypographyModule($this);
        $this->longWordsModule = new TexyLongWordsModule($this);
        $this->htmlOutputModule = new TexyHtmlOutputModule($this);
    }



    final public function registerLinePattern($handler, $pattern, $name)
    {
        if (!isset($this->allowed[$name])) $this->allowed[$name] = TRUE;

        $this->linePatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern,
        );
    }



    final public function registerBlockPattern($handler, $pattern, $name)
    {
        // if (!preg_match('#(.)\^.*\$\\1[a-z]*#is', $pattern)) die("Texy: Not a block pattern $name");

        if (!isset($this->allowed[$name])) $this->allowed[$name] = TRUE;

        $this->blockPatterns[$name] = array(
            'handler'     => $handler,
            'pattern'     => $pattern  . 'm',  // force multiline
        );
    }



    final public function registerPostLine($handler, $name)
    {
        if (!isset($this->allowed[$name])) $this->allowed[$name] = TRUE;

        $this->postHandlers[$name] = $handler;
    }



    /**
     * Convert Texy! document in (X)HTML code
     *
     * @param string   input text
     * @param bool     is block or single line?
     * @return string  output html code
     */
    public function process($text, $singleLine = FALSE)
    {
        if ($this->processing) {
            throw new TexyException('Processing is in progress yet.');
        }

        // initialization
        $this->marks = array();
        $this->processing = TRUE;

        // speed-up
        if (is_array($this->allowedClasses)) $this->_classes = array_flip($this->allowedClasses);
        else $this->_classes = $this->allowedClasses;

        if (is_array($this->allowedStyles)) $this->_styles = array_flip($this->allowedStyles);
        else $this->_styles = $this->allowedStyles;

        // convert to UTF-8 (and check source encoding)
        $text = TexyUtf::toUtf($text, $this->encoding);

        // standardize line endings and spaces
        $text = self::normalize($text);

        // replace tabs with spaces
        while (strpos($text, "\t") !== FALSE)
            $text = preg_replace_callback('#^(.*)\t#mU', array($this, 'tabCb'), $text);

        // user before handler
        $this->invokeHandlers('beforeParse', array($this, & $text, $singleLine));

        // select patterns
        $this->_linePatterns = $this->linePatterns;
        $this->_blockPatterns = $this->blockPatterns;
        foreach ($this->_linePatterns as $name => $foo) {
            if (empty($this->allowed[$name])) unset($this->_linePatterns[$name]);
        }
        foreach ($this->_blockPatterns as $name => $foo) {
            if (empty($this->allowed[$name])) unset($this->_blockPatterns[$name]);
        }

        // parse Texy! document into internal DOM structure
        $this->DOM = TexyHtml::el();
        if ($singleLine) {
            $this->DOM->parseLine($this, $text);
        } else {
            $this->DOM->parseBlock($this, $text, TexyBlockParser::TOP);
        }

        // user after handler
        $this->invokeHandlers('afterParse', array($this, $this->DOM, $singleLine));

        // converts internal DOM structure to final HTML code
        $html = $this->DOM->toHtml($this);

        // this notice should remain
        if (!self::$noticeShowed) {
            $html .= "\n<!-- by Texy2! -->";
            self::$noticeShowed = TRUE;
        }

        $this->processing = FALSE;

        return TexyUtf::utf2html($html, $this->encoding);
    }



    /**
     * Makes only typographic corrections
     * @param string   input text
     * @return string  output code (in UTF!)
     */
    public function processTypo($text)
    {
        // convert to UTF-8 (and check source encoding)
        $text = TexyUtf::toUtf($text, $this->encoding);

        // standardize line endings and spaces
        $text = self::normalize($text);

        $this->typographyModule->begin();
        $text = $this->typographyModule->postLine($text);

        return $text;
    }



    /**
     * Converts DOM structure to pure text
     * @return string
     */
    public function toText()
    {
        if (!$this->DOM) {
            throw new TexyException('Call $texy->process() first.');
        }

        return TexyUtf::utfTo($this->DOM->toText($this), $this->encoding);
    }



    /**
     * Converts internal string representation to final HTML code in UTF-8
     * @return string
     */
    final public function stringToHtml($s)
    {
        // decode HTML entities to UTF-8
        $s = self::unescapeHtml($s);

        // line-postprocessing
        $blocks = explode(self::CONTENT_BLOCK, $s);
        foreach ($this->postHandlers as $name => $handler) {
            if (empty($this->allowed[$name])) continue;
            foreach ($blocks as $n => $s) {
                if ($n % 2 === 0 && $s !== '') {
                    $blocks[$n] = call_user_func($handler, $s);
                }
            }
        }
        $s = implode(self::CONTENT_BLOCK, $blocks);

        // encode < > &
        $s = self::escapeHtml($s);

        // replace protected marks
        $s = $this->unProtect($s);

        // wellform and reformat HTML
        $this->invokeHandlers('postProcess', array($this, & $s));

        // unfreeze spaces
        $s = self::unfreezeSpaces($s);

        return $s;
    }



    /**
     * Converts internal string representation to final HTML code in UTF-8
     * @return string
     */
    final public function stringToText($s)
    {
        $save = $this->htmlOutputModule->lineWrap;
        $this->htmlOutputModule->lineWrap = FALSE;
        $s = $this->stringToHtml( $s );
        $this->htmlOutputModule->lineWrap = $save;

        // remove tags
        $s = preg_replace('#<(script|style)(.*)</\\1>#Uis', '', $s);
        $s = strip_tags($s);
        $s = preg_replace('#\n\s*\n\s*\n[\n\s]*\n#', "\n\n", $s);

        // entities -> chars
        $s = Texy::unescapeHtml($s);

        // convert nbsp to normal space and remove shy
        $s = strtr($s, array(
            "\xC2\xAD" => '',  // shy
            "\xC2\xA0" => ' ', // nbsp
        ));

        return $s;
    }



    /**
     * Add new event handler
     *
     * @param string   event name
     * @param callback
     * @return void
     */
    final public function addHandler($event, $callback)
    {
        if (!is_callable($callback)) {
            throw new TexyException("Invalid callback");
        }

        $this->handlers[$event][] = $callback;
    }



    /**
     * Invoke registered around-handlers
     *
     * @param string   event name
     * @param TexyParser  actual parser object
     * @param array    arguments passed into handler
     * @return mixed
     */
    final public function invokeAroundHandlers($event, $parser, $args)
    {
        if (!isset($this->handlers[$event])) return FALSE;

        $invocation = new TexyHandlerInvocation($this->handlers[$event], $parser, $args);
        $res = $invocation->proceed();
        $invocation->free();
        return $res;
    }



    /**
     * Invoke registered after-handlers
     *
     * @param string   event name
     * @param array    arguments passed into handler
     * @return void
     */
    final public function invokeHandlers($event, $args)
    {
        if (!isset($this->handlers[$event])) return;

        foreach ($this->handlers[$event] as $handler) {
            call_user_func_array($handler, $args);
        }
    }



    /**
     * Translate all white spaces (\t \n \r space) to meta-spaces \x01-\x04
     * which are ignored by TexyHtmlOutputModule routine
     * @param string
     * @return string
     */
    final public static function freezeSpaces($s)
    {
        return strtr($s, " \t\r\n", "\x01\x02\x03\x04");
    }



    /**
     * Reverts meta-spaces back to normal spaces
     * @param string
     * @return string
     */
    final public static function unfreezeSpaces($s)
    {
        return strtr($s, "\x01\x02\x03\x04", " \t\r\n");
    }



    /**
     * Removes special controls characters and normalizes line endings and spaces
     * @param string
     * @return string
     */
    final public static function normalize($s)
    {
        // standardize line endings to unix-like
        $s = str_replace("\r\n", "\n", $s); // DOS
        $s = strtr($s, "\r", "\n"); // Mac

        // remove special chars; leave \t + \n
        $s = preg_replace('#[\x00-\x08\x0B-\x1F]+#', '', $s);

        // right trim
        $s = preg_replace("#[\t ]+$#m", '', $s);

        // trailing spaces
        $s = trim($s, "\n");

        return $s;
    }



    /**
     * Converts to web safe characters [a-z0-9-] text
     * @param string
     * @param string
     * @return string
     */
    final public static function webalize($s, $charlist = NULL)
    {
        $s = TexyUtf::utf2ascii($s);
        $s = strtolower($s);
        if ($charlist) $charlist = preg_quote($charlist, '#');
        $s = preg_replace('#[^a-z0-9'.$charlist.']+#', '-', $s);
        $s = trim($s, '-');
        return $s;
    }



    /**
     * Texy! version of htmlSpecialChars (much faster than htmlSpecialChars!)
     * note: &quot; is not encoded!
     * @param string
     * @return string
     */
    final public static function escapeHtml($s)
    {
        return str_replace(array('&', '<', '>'), array('&amp;', '&lt;', '&gt;'), $s);
    }



    /**
     * Texy! version of html_entity_decode (always UTF-8, much faster than original!)
     * @param string
     * @return string
     */
    final public static function unescapeHtml($s)
    {
        if (strpos($s, '&') === FALSE) return $s;
        return html_entity_decode($s, ENT_QUOTES, 'UTF-8');
    }



    /**
     * Generate unique mark - useful for freezing (folding) some substrings
     * @param string   any string to froze
     * @param int      Texy::CONTENT_* constant
     * @return string  internal mark
     */
    final public function protect($child, $contentType = self::CONTENT_BLOCK)
    {
        if ($child==='') return '';

        $key = $contentType
            . strtr(base_convert(count($this->marks), 10, 8), '01234567', "\x18\x19\x1A\x1B\x1C\x1D\x1E\x1F")
            . $contentType;

        $this->marks[$key] = $child;

        return $key;
    }



    final public function unProtect($html)
    {
        return strtr($html, $this->marks);
    }



    /**
     * Filters bad URLs
     * @param string   user URL
     * @param string   type: a-anchor, i-image, c-cite
     * @return bool
     */
    final public function checkURL($URL, $type)
    {
        // absolute URL with scheme? check scheme!
        if (!empty($this->urlSchemeFilters[$type])
            && preg_match('#'.TEXY_URLSCHEME.'#iA', $URL)
            && !preg_match($this->urlSchemeFilters[$type], $URL))
            return FALSE;

        return TRUE;
    }



    /**
     * Is given URL relative?
     * @param string  URL
     * @return bool
     */
    final public static function isRelative($URL)
    {
        // check for scheme, or absolute path, or absolute URL
        return !preg_match('#'.TEXY_URLSCHEME.'|[\#/?]#iA', $URL);
    }



    /**
     * Prepends root to URL, if possible
     * @param string  URL
     * @param string  root
     * @return string
     */
    final public static function prependRoot($URL, $root)
    {
        if ($root == NULL || !self::isRelative($URL)) return $URL;
        return rtrim($root, '/\\') . '/' . $URL;
    }



    final public function getLinePatterns()
    {
        return $this->_linePatterns;
    }



    final public function getBlockPatterns()
    {
        return $this->_blockPatterns;
    }



    final public function getDOM()
    {
        return $this->DOM;
    }



    private function tabCb($m)
    {
        return $m[1] . str_repeat(' ', $this->tabWidth - strlen($m[1]) % $this->tabWidth);
    }



    /**
     * PHP garbage collector helper
     */
    final public function free()
    {
        foreach (array_keys(get_object_vars($this)) as $key)
            $this->$key = NULL;
    }



    final public function __clone()
    {
        throw new TexyException("Clone is not supported");
    }

}
