<?php
    // $Id$
    
    if (!defined("SIMPLE_TEST")) {
        define("SIMPLE_TEST", "simpletest/");
    }
    define("LEXER_ENTER", 1);
    define("LEXER_MATCHED", 2);
    define("LEXER_UNMATCHED", 3);
    define("LEXER_EXIT", 4);
    define("LEXER_SPECIAL", 5);
    
    /**
     *    Compounded regular expression. Any of
     *    the contained patterns could match and
     *    when one does it's label is returned.
     */
    class ParallelRegex {
        var $_patterns;
        var $_labels;
        var $_regex;
        var $_case;
        
        /**
         *    Constructor. Starts with no patterns.
         *    @param $case    True for case sensitive, false
         *                    for insensitive.
         *    @public
         */
        function ParallelRegex($case) {
            $this->_case = $case;
            $this->_patterns = array();
            $this->_labels = array();
            $this->_regex = null;
        }
        
        /**
         *    Adds a pattern with an optional label.
         *    @param $pattern      Perl style regex, but ( and )
         *                         lose the usual meaning.
         *    @param $label        Label of regex to be returned
         *                         on a match.
         *    @public
         */
        function addPattern($pattern, $label = true) {
            $count = count($this->_patterns);
            $this->_patterns[$count] = $pattern;
            $this->_labels[$count] = $label;
            $this->_regex = null;
        }
        
        /**
         *    Attempts to match all patterns at once against
         *    a string.
         *    @param $subject      String to match against.
         *    @param $match        First matched portion of
         *                         subject.
         *    @return              True on success.
         *    @public
         */
        function match($subject, &$match) {
            if (count($this->_patterns) == 0) {
                return false;
            }
            if (!preg_match($this->_getCompoundedRegex(), $subject, $matches)) {
                $match = "";
                return false;
            }
            $match = $matches[0];
            for ($i = 1; $i < count($matches); $i++) {
                if ($matches[$i]) {
                    return $this->_labels[$i - 1];
                }
            }
            return true;
        }
        
        /**
         *    Compounds the patterns into a single
         *    regular expression separated with the
         *    "or" operator. Caches the regex.
         *    Will automatically escape (, ) and / tokens.
         *    @param $patterns    List of patterns in order.
         *    @private
         */
        function _getCompoundedRegex() {
            if ($this->_regex == null) {
                for ($i = 0; $i < count($this->_patterns); $i++) {
                    $this->_patterns[$i] = '(' . str_replace(
                            array('/', '(', ')'),
                            array('\/', '\(', '\)'),
                            $this->_patterns[$i]) . ')';
                }
                $this->_regex = "/" . implode("|", $this->_patterns) . "/" . $this->_getPerlMatchingFlags();
            }
            return $this->_regex;
        }
        
        /**
         *    Accessor for perl regex mode flags to use.
         *    @return        Flags as string.
         *    @private
         */
        function _getPerlMatchingFlags() {
            return ($this->_case ? "msS" : "msSi");
        }
    }
    
    /**
     *    States for a stack machine.
     */
    class StateStack {
        var $_stack;
        
        /**
         *    Constructor. Starts in named state.
         *    @param $start        Starting state name.
         *    @public
         */
        function StateStack($start) {
            $this->_stack = array($start);
        }
        
        /**
         *    Accessor for current state.
         *    @return        State as string.
         *    @public
         */
        function getCurrent() {
            return $this->_stack[count($this->_stack) - 1];
        }
        
        /**
         *    Adds a state to the stack and sets it
         *    to be the current state.
         *    @param $state        New state.
         *    @public
         */
        function enter($state) {
            array_push($this->_stack, $state);
        }
        
        /**
         *    Leaves the current state and reverts
         *    to the previous one.
         *    @return     False if we drop off
         *                the bottom of the list.
         *    @public
         */
        function leave() {
            if (count($this->_stack) == 1) {
                return false;
            }
            array_pop($this->_stack);
            return true;
        }
    }
    
    /**
     *    Accepts text and breaks it into tokens.
     *    Some optimisation to make the sure the
     *    content is only scanned by the PHP regex
     *    parser once. Lexer modes must not start
     *    with leading underscores.
     */
    class SimpleLexer {
        var $_regexes;
        var $_parser;
        var $_mode;
        var $_mode_handlers;
        var $_case;
        
        /**
         *    Sets up the lexer in case insensitive matching
         *    by default.
         *    @param $parser     Handling strategy by
         *                       reference.
         *    @param $start      Starting handler.
         *    @param $case       True for case sensitive.
         *    @public
         */
        function SimpleLexer(&$parser, $start = "accept", $case = false) {
            $this->_case = $case;
            $this->_regexes = array();
            $this->_parser = &$parser;
            $this->_mode = new StateStack($start);
            $this->_mode_handlers = array();
        }
        
        /**
         *    Adds a token search pattern for a particular
         *    parsing mode. The pattern does not change the
         *    current mode.
         *    @param $pattern      Perl style regex, but ( and )
         *                         lose the usual meaning.
         *    @param $mode         Should only apply this
         *                         pattern when dealing with
         *                         this type of input.
         *    @public
         */
        function addPattern($pattern, $mode = "accept") {
            if (!isset($this->_regexes[$mode])) {
                $this->_regexes[$mode] = new ParallelRegex($this->_case);
            }
            $this->_regexes[$mode]->addPattern($pattern);
        }
        
        /**
         *    Adds a pattern that will enter a new parsing
         *    mode. Useful for entering parenthesis, strings,
         *    tags, etc.
         *    @param $pattern      Perl style regex, but ( and )
         *                         lose the usual meaning.
         *    @param $mode         Should only apply this
         *                         pattern when dealing with
         *                         this type of input.
         *    @param $new_mode     Change parsing to this new
         *                         nested mode.
         *    @public
         */
        function addEntryPattern($pattern, $mode, $new_mode) {
            if (!isset($this->_regexes[$mode])) {
                $this->_regexes[$mode] = new ParallelRegex($this->_case);
            }
            $this->_regexes[$mode]->addPattern($pattern, $new_mode);
        }
        
        /**
         *    Adds a pattern that will exit the current mode
         *    and re-enter the previous one.
         *    @param $pattern      Perl style regex, but ( and )
         *                         lose the usual meaning.
         *    @param $mode         Mode to leave.
         *    @public
         */
        function addExitPattern($pattern, $mode) {
            if (!isset($this->_regexes[$mode])) {
                $this->_regexes[$mode] = new ParallelRegex($this->_case);
            }
            $this->_regexes[$mode]->addPattern($pattern, "__exit");
        }
        
        /**
         *    Adds a pattern that has a special mode.
         *    Acts as an entry and exit pattern in one go.
         *    @param $pattern      Perl style regex, but ( and )
         *                         lose the usual meaning.
         *    @param $mode         Should only apply this
         *                         pattern when dealing with
         *                         this type of input.
         *    @param $special      Use this mode for this one token.
         *    @public
         */
        function addSpecialPattern($pattern, $mode, $special) {
            if (!isset($this->_regexes[$mode])) {
                $this->_regexes[$mode] = new ParallelRegex($this->_case);
            }
            $this->_regexes[$mode]->addPattern($pattern, "_$special");
        }
        
        /**
         *    Adds a mapping from a mode to another handler.
         *    @param $mode        Mode to be remapped.
         *    @param $handler     New target handler.
         *    @public
         */
        function mapHandler($mode, $handler) {
            $this->_mode_handlers[$mode] = $handler;
        }
        
        /**
         *    Splits the page text into tokens. Will fail
         *    if the handlers report an error or if no
         *    content is consumed. If successful then each
         *    unparsed and parsed token invokes a call to the
         *    held listener.
         *    @param $raw        Raw HTML text.
         *    @return            True on success, else false.
         *    @public
         */
        function parse($raw) {
            if (!isset($this->_parser)) {
                return false;
            }
            $length = strlen($raw);
            while (is_array($parsed = $this->_reduce($raw))) {
                list($unmatched, $matched, $mode) = $parsed;
                if (!$this->_dispatchTokens($unmatched, $matched, $mode)) {
                    return false;
                }
                if (strlen($raw) == $length) {
                    return false;
                }
                $length = strlen($raw);
            }
            if (!$parsed) {
                return false;
            }
            return $this->_invokeParser($raw, LEXER_UNMATCHED);
        }
        
        /**
         *    Sends the matched token and any leading unmatched
         *    text to the parser changing the lexer to a new
         *    mode if one is listed.
         *    @param $unmatched    Unmatched leading portion.
         *    @param $matched      Actual token match.
         *    @param $mode         Mode after match. The "_exit"
         *                         mode causes a stack pop. An
         *                         false mode causes no change.
         *    @return              False if there was any error
         *                         from the parser.
         *    @private
         */
        function _dispatchTokens($unmatched, $matched, $mode = false) {
            if (!$this->_invokeParser($unmatched, LEXER_UNMATCHED)) {
                return false;
            }
            if ($mode === "__exit") {
                if (!$this->_invokeParser($matched, LEXER_EXIT)) {
                    return false;
                }
                return $this->_mode->leave();
            }
            if (strncmp($mode, "_", 1) == 0) {
                $mode = substr($mode, 1);
                $this->_mode->enter($mode);
                if (!$this->_invokeParser($matched, LEXER_SPECIAL)) {
                    return false;
                }
                return $this->_mode->leave();
            }
            if (is_string($mode)) {
                $this->_mode->enter($mode);
                return $this->_invokeParser($matched, LEXER_ENTER);
            }
            return $this->_invokeParser($matched, LEXER_MATCHED);
        }
        
        /**
         *    Calls the parser method named after the current
         *    mode. Empty content will be ignored.
         *    @param $content        Text parsed.
         *    @param $is_match       Token is recognised rather
         *                           than unparsed data.
         *    @private
         */
        function _invokeParser($content, $is_match) {
            if (($content === "") || ($content === false)) {
                return true;
            }
            $handler = $this->_mode->getCurrent();
            if (isset($this->_mode_handlers[$handler])) {
                $handler = $this->_mode_handlers[$handler];
            }
            return $this->_parser->$handler($content, $is_match);
        }
        
        /**
         *    Tries to match a chunk of text and if successful
         *    removes the recognised chunk and any leading
         *    unparsed data. Empty strings will not be matched.
         *    @param $raw         The subject to parse. This is the
         *                        content that will be eaten.
         *    @return             Three item list of unparsed
         *                        content followed by the
         *                        recognised token and finally the
         *                        action the parser is to take.
         *                        True if no match, false if there
         *                        is a parsing error.
         *    @private
         */
        function _reduce(&$raw) {
            if (!isset($this->_regexes[$this->_mode->getCurrent()])) {
                return false;
            }
            if ($raw === "") {
                return true;
            }
            if ($action = $this->_regexes[$this->_mode->getCurrent()]->match($raw, $match)) {
                $count = strpos($raw, $match);
                $unparsed = substr($raw, 0, $count);
                $raw = substr($raw, $count + strlen($match));
                return array($unparsed, $match, $action);
            }
            return true;
        }
    }
    
    /**
     *    Converts HTML tokens into selected SAX events.
     */
    class SimpleSaxParser {
        var $_lexer;
        var $_listener;
        var $_tag;
        var $_attributes;
        var $_current_attribute;
        
        /**
         *    Sets the listener.
         *    @param $listener SAX event handler.
         *    @public
         */
        function SimpleSaxParser(&$listener) {
            $this->_listener = &$listener;
            $this->_lexer = &$this->createLexer($this);
            $this->_tag = "";
            $this->_attributes = array();
            $this->_current_attribute = "";
        }
        
        /**
         *    Sets up the matching lexer.
         *    @param $parser    Event generator, usually $self.
         *    @return           Lexer suitable for this parser.
         *    @public
         *    @static
         */
        function &createLexer(&$parser) {
            $lexer = &new SimpleLexer($parser, 'text');
            $lexer->mapHandler('text', 'acceptTextToken');
            SimpleSaxParser::_addSkipping($lexer);
            SimpleSaxParser::_addTag($lexer, "a");
            SimpleSaxParser::_addTag($lexer, "title");
            SimpleSaxParser::_addTag($lexer, "form");
            SimpleSaxParser::_addTag($lexer, "input");
            SimpleSaxParser::_addTag($lexer, "textarea");
            SimpleSaxParser::_addInTagTokens($lexer);
            return $lexer;
        }
        
        /**
         *    The lexer has to skip certain sections such
         *    as server code, client code and styles.
         *    @param $lexer        Lexer to add patterns to.
         *    @private
         *    @static
         */
        function _addSkipping(&$lexer) {
            $lexer->mapHandler('css', 'ignore');
            $lexer->addEntryPattern('<style', 'text', 'css');
            $lexer->addExitPattern('</style>', 'css');
            $lexer->mapHandler('js', 'ignore');
            $lexer->addEntryPattern('<script', 'text', 'js');
            $lexer->addExitPattern('</script>', 'js');
            $lexer->mapHandler('comment', 'ignore');
            $lexer->addEntryPattern('<!--', 'text', 'comment');
            $lexer->addExitPattern('-->', 'comment');
        }
        
        /**
         *    Pattern matches to start and end a tag.
         *    @param $lexer        Lexer to add patterns to.
         *    @param $tag          Name of tag to scan for.
         *    @private
         *    @static
         */
        function _addTag(&$lexer, $tag) {
            $lexer->addSpecialPattern("</$tag>", 'text', 'acceptEndToken');
            $lexer->addEntryPattern("<$tag", 'text', 'tag');
        }
        
        /**
         *    Pattern matches to parse the inside of a tag
         *    including the attributes and their quoting.
         *    @param $lexer        Lexer to add patterns to.
         *    @private
         *    @static
         */
        function _addInTagTokens(&$lexer) {
            $lexer->mapHandler('tag', 'acceptStartToken');
            $lexer->addSpecialPattern('\s+', 'tag', 'ignore');
            SimpleSaxParser::_addAttributeTokens($lexer);
            $lexer->addExitPattern('>', 'tag');
        }
        
        /**
         *    Matches attributes that are either single quoted,
         *    double quoted or unquoted.
         *    @param $lexer        Lexer to add patterns to.
         *    @private
         *    @static
         */
        function _addAttributeTokens(&$lexer) {
            $lexer->mapHandler('dq_attribute', 'acceptAttributeToken');
            $lexer->addEntryPattern('=\s*"', 'tag', 'dq_attribute');
            $lexer->addPattern("\\\\\"", 'dq_attribute');
            $lexer->addExitPattern('"', 'dq_attribute');
            $lexer->mapHandler('sq_attribute', 'acceptAttributeToken');
            $lexer->addEntryPattern("=\s*'", 'tag', 'sq_attribute');
            $lexer->addPattern("\\\\'", 'sq_attribute');
            $lexer->addExitPattern("'", 'sq_attribute');
            $lexer->mapHandler('uq_attribute', 'acceptAttributeToken');
            $lexer->addSpecialPattern('=\s*[^>\s]*', 'tag', 'uq_attribute');
        }
        
        /**
         *    Runs the content through the lexer which
         *    should call back to the acceptors.
         *    @param $raw      Page text to parse.
         *    @return          False if parse error.
         *    @public
         */
        function parse($raw) {
            return $this->_lexer->parse($raw);
        }
        
        /**
         *    Accepts a token from the tag mode. If the
         *    starting element completes then the element
         *    is dispatched and the current attributes
         *    set back to empty. The element or attribute
         *    name is converted to lower case.
         *    @param $token    Incoming characters.
         *    @param $event    Lexer event type.
         *    @return          False if parse error.
         *    @public
         */
        function acceptStartToken($token, $event) {
            if ($event == LEXER_ENTER) {
                $this->_tag = strtolower(substr($token, 1));
                return true;
            }
            if ($event == LEXER_EXIT) {
                $success = $this->_listener->startElement(
                        $this->_tag,
                        $this->_attributes);
                $this->_tag = "";
                $this->_attributes = array();
                return $success;
            }
            if ($token != "=") {
                $this->_current_attribute = strtolower($token);
                $this->_attributes[$this->_current_attribute] = "";
            }
            return true;
        }
        
        /**
         *    Accepts a token from the end tag mode.
         *    The element name is converted to lower case.
         *    @param $token    Incoming characters.
         *    @param $event    Lexer event type.
         *    @return          False if parse error.
         *    @public
         */
        function acceptEndToken($token, $event) {
            if (!preg_match('/<\/(.*)>/', $token, $matches)) {
                return false;
            }
            return $this->_listener->endElement(strtolower($matches[1]));
        }
        
        /**
         *    Part of the tag data.
         *    @param $token    Incoming characters.
         *    @param $event    Lexer event type.
         *    @return          False if parse error.
         *    @public
         */
        function acceptAttributeToken($token, $event) {
            if ($event == LEXER_UNMATCHED) {
                $this->_attributes[$this->_current_attribute] .= $token;
            }
            if ($event == LEXER_SPECIAL) {
                $this->_attributes[$this->_current_attribute] .=
                        preg_replace('/^=\s*/' , '', $token);
            }
            return true;
        }
        
        /**
         *    A character entity.
         *    @param $token    Incoming characters.
         *    @param $event    Lexer event type.
         *    @return          False if parse error.
         *    @public
         */
        function acceptEntityToken($token, $event) {
        }
        
        /**
         *    Character data between tags regarded as
         *    important.
         *    @param $token    Incoming characters.
         *    @param $event    Lexer event type.
         *    @return          False if parse error.
         *    @public
         */
        function acceptTextToken($token, $event) {
            return $this->_listener->addContent($token);
        }
        
        /**
         *    Incoming data to be ignored.
         *    @param $token    Incoming characters.
         *    @param $event    Lexer event type.
         *    @return          False if parse error.
         *    @public
         */
        function ignore($token, $event) {
            return true;
        }
    }
    
    /**
     *    SAX event handler.
     *    @abstract
     */
    class SimpleSaxListener {
        
        /**
         *    Sets the document to write to.
         *    @public
         */
        function SimpleSaxListener() {
        }
        
        /**
         *    Start of element event.
         *    @param $name        Element name.
         *    @param $attributes  Hash of name value pairs.
         *                        Attributes without content
         *                        are marked as true.
         *    @return             False on parse error.
         *    @public
         */
        function startElement($name, $attributes) {
        }
        
        /**
         *    End of element event.
         *    @param $name        Element name.
         *    @return             False on parse error.
         *    @public
         */
        function endElement($name) {
        }
        
        /**
         *    Unparsed, but relevant data.
         *    @param $text        May include unparsed tags.
         *    @return             False on parse error.
         *    @public
         */
        function addContent($text) {
        }
    }
?>