<?php
/* vim:set shiftwidth=4 tabstop=4 expandtab: */
######################################################################

/*
 * Templatté [pronounced templatte:] is a mini template system.
 * Copyright (C) 2012 Milan Herda <perungrad@perunhq.org>
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 *
 */

######################################################################
if (!defined("__TEMPLATTE_PHP__")) {
define("__TEMPLATTE_PHP__",                       1);

if (!defined('TEMPLATTE_DIR')) {
    define('TEMPLATTE_DIR', 'tpl');
}

if (!defined('TEMPLATTE_EXT')) {
    define('TEMPLATTE_EXT', '.tpl');
}

######################################################################
/**
 * Base class of simple template engine.
 */
class Templatte {
    /**
     * String containing actual state of template.
     * @access protected
     * @var string
     */
    protected $template = '';

    /**
     * Configuration options.
     * @access protected
     * @var array
     */
    protected $options = array();

    /**
     * Security token is appended to all patterns in the template string.
     * It adds some randomnes to the actual form of pattern and protects
     * us from bad guys.
     * @static
     * @access protected
     * @var string
     */
    protected static $security_token = '';

    /**
     * Name of the class used for handling URL patterns.
     * You can set your own by calling @see Templatte::set_url_handler
     * Default: Templatte
     * @static
     * @access protected
     * @var string
     */
    protected static $url_class = 'Templatte';

    /**
     * Name of the method used for handling URL patterns.
     * You can set your own by calling @see Templatte::set_url_handler
     * Default: url
     * @static
     * @access protected
     * @var string
     */
    protected static $url_method = 'url';

    /**
     * Name of the class used for handling L (language) patterns.
     * You can set your own by calling @see Templatte::set_lang_handler
     * Default: Templatte
     * @static
     * @access protected
     * @var string
     */
    protected static $lang_class = 'Templatte';

    /**
     * Name of the method used for handling L (language) patterns.
     * You can set your own by calling @see Templatte::set_lang_handler
     * Default: url
     * @static
     * @access protected
     * @var string
     */
    protected static $lang_method = 'lang';

    ##################################################################
    /**
     * Constructor sets up the engine, read input template, applies security token
     * and process simple patterns.
     *
     * As a first argument, you can pass a string containing whole template for processing.
     * Or you can pass string containg a file name. If you pass a filename (which is default)
     * constructor will find that file, read it and save as a string in $this->template variable.
     *
     * Second argument is an associative array containing options for this single instance of Templatte.
     * These options can be:
     * type   - Specifies type of the first argument. Possible values: 'string', 'file'. Anything else defaults fo 'file'.
     * dir    - directory in which the engine will lookup for files. Default: @see TEMPLATTE_DIR
     * ext    - extension fo template files. Default: @see TEMPLATTE_EXT
     * params - automatically process PARAM_ and PARVAL_ patterns. Possible values: true, false. Default: true. @see Templatte::replace_params
     * tags   - automatically process custom tags (currently only <action>). Default: true
     * langs  - automatically process language strings in L patterns. Default: true
     * urls   - automatically process URL_ and URL: patterns. Default: true
     *
     * @access public
     * @param string $_source Either a template string or a name of the file. Default is file.
     * @param array $_options Instance-specific behavior. Parameter is optional.
     * @return Templatte Instance of the class
     */
    public function __construct($_source, array $_options = array()) {
        $this->template = "";

        $_options['type']        = isset($_options['type'])        ? $_options['type']        : 'file';
        $_options['dir']         = isset($_options['dir'])         ? $_options['dir']         : TEMPLATTE_DIR . '/';
        $_options['ext']         = isset($_options['ext'])         ? $_options['ext']         : TEMPLATTE_EXT;
        $_options['params']      = isset($_options['params'])      ? $_options['params']      : true;
        $_options['tags']        = isset($_options['tags'])        ? $_options['tags']        : true;
        $_options['langs']       = isset($_options['langs'])       ? $_options['langs']       : true;
        $_options['urls']        = isset($_options['urls'])        ? $_options['urls']        : true;

        $this->options = $_options;

        switch ($this->options['type']) {
            case 'string':
                $this->template = $_source;
                break;

            case'file':
            default:
                $file = $this->options['dir'] . $_source . $this->options['ext'];
                if (file_exists($file)) {
                    $this->template = file_get_contents($file);
                }
                else {
                    $this->template = $_source;
                }
                break;
        }

        # <action> tag
        if ($this->options['tags']) {
            $this->replace_tags();
        }

        # {PARAM_*} and {PARVAL_*}
        if ($this->options['params']) {
            $this->replace_params();
        }

        # {L:*}
        if (!empty($this->options['langs'])) {
            $this->replace_langs();
        }

        # {URL_*}
        if (!empty($this->options['urls'])) {
            $this->replace_urls();
        }

        $this->apply_security_token();
    }

    ##################################################################
    /**
     * Binds and immediately replaces given 'if:', 'repeat:' and simple patterns
     * Template string changes after every call to bind and there is no unbind.
     *
     * @acces public
     * @param string $_rule name of the pattern. Can be simple string, 'if:blah' or 'repeat:blah'
     * @param mixed $_value Value for replacement. Simple string, boolean or array.
     * @return Templatte Instance of class so you can do method chaining.
     */
    public function bind($_rule, $_value = "") {
        if (is_array($_rule)) {
            foreach ($_rule as $rule => $rule_val) {
                $this->_bind($rule . self::$security_token, $rule_val);
            }
        }
        else {
            $this->_bind($_rule . self::$security_token, $_value);
        }
        return $this;
    }

    ##################################################################
    /**
     * Shortcut for calling bind('repeat:blah', array(...))
     *
     * @access public
     * @param string $_name Name of the repeat pattern.
     * @param array $_rules Rules for this iteration of repeat.
     * @return Templatte Instance of class so you can do method chaining.
     */
    public function repeat($_name, $_rules) {
        $this->bind('repeat:' . $_name, $_rules);

        return $this;
    }

    ##################################################################
    /**
     * Returns "context aware" value.
     *
     * Every simple rule can contain a hint character on the first position
     * specifying context and which type of escaping is needed.
     * Default context is html. By using hints you can specified plain or javascript context.
     *
     * @TODO: remove hints from PHP and use them in TPL files.
     * @TODO: add more hints :) - tag attributes etc.
     *
     * @access protected
     * @param string Name of the rule. First character can be hint. Possible values: ! - no escaping, $ - json.
     * @param string Value of the rule
     * @return array Name and new value of the rule.
     */
    protected function get_replacement($_rule, $_value) {
        $_rule = (string)$_rule;

        if ($_value === null) {
            $_value = "";
        }

        switch ($_rule[0]) {
            case '!':
                # no escaping
                $_rule = substr($_rule, 1);
                break;

            case '$':
                # javascript
                $_rule = substr($_rule, 1);
                $_value = json_encode($_value);
                break;

            default:
                # xhtml, xml
                $_value = htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', '', $_value), ENT_QUOTES);
                break;
        }
        return array($_rule, $_value);
    }

    ##################################################################
    /**
     * Actual replacing of the patterns with their values.
     *
     * Simple rule is replaced here. We're using str_replace.
     * If the first parameter is NAME, we're looking for {NAME} and replacing every occurence by $_value
     *
     * 'if:' and 'repeat:' rules are further processed by replace_if and replace_repeat method respectively.
     *
     * @access protected
     * @param string $_rule Name of the rule
     * @param mixed $_value Value of the rule. Type depends on the rule.
     */
    protected function _bind($_rule, $_value) {
        if (preg_match('/^if:.+/', $_rule)) {
            $this->replace_if($_rule, (bool)$_value);
        }
        else if (preg_match('/^repeat:.+/', $_rule)) {
            $this->replace_repeat($_rule, $_value);
        }
        else {
            if (!is_array($_rule)) {
                $_rule = array($_rule);
            }
            if (!is_array($_value)) {
                $_value = array($_value);
            }
            $num = count($_rule);
            for ($i = 0; $i < $num; $i++) {
                if (isset($_value[$i])) {
                    list($_rule[$i], $_value[$i]) = $this->get_replacement($_rule[$i], $_value[$i]);
                    $this->template = str_replace('{'.$_rule[$i].'}', $_value[$i], $this->template);
                }
                else {
                    break;
                }
            }
        }
    }

    ##################################################################
    /**
     * Working with the IF rule.
     * IF rule can have two forms: 'if:' and its negative form 'if!:'
     * You can have single or both in your template, but you need only one
     * positive form to bind. Negative form is bind automatically for you.
     * If you do not bind any value in your PHP script to 'IF' patterns,
     * it defaults to false (and negative form to true).
     *
     * This method only prepares proper parameters for the method @see Templatte::_replace_if
     *
     * @access protected
     * @param string $_rule Positive form of the rule. Example: 'if:logged' (plus security token)
     * @param bool $_show   Show or hide part of the document in the 'if:' block.
     * @param string $_str  Part of the template in which does the replacement occur (either whole tempalte, or repeat: block)
     * @return string Template (or its part) with 'IF' rules aplied.
     */
    protected function replace_if($_rule, $_show = true, $_str = false) {
        $str = '';

        $parts = explode(':', $_rule);
        if (count($parts)) {
            $cond = array_shift($parts);

            $not_rule = $cond . '!:' . implode('', $parts);

            $show = (bool)$_show;
            $str = $this->_replace_if($_rule, $show, $_str);
            $str = $this->_replace_if($not_rule, !$show, $_str ? $str : false);
        }

        return $str;
    }

    ##################################################################
    /**
     * Replaces the blocks within <if:rule> and <if!:rule> tags in the
     * template either with their contents, or with an empty string.
     *
     * @access protected
     * @param string $_rule Name of the rule (if tag). Can be positive or negative form. Example: 'if:logged' or if!:logged (both also with security token)
     * @param bool $_show   Show or hide part of the document in the block.
     * @param string $_str  Part of the template in which does the replacement occur (either whole tempalte, or repeat: block)
     * @return string Template (or its part) with the rule aplied.
     */
    protected function _replace_if($_rule, $_show = true, $_str = false) {
        $use_template = false;

        if ($_str === false) {
            $use_template = true;
            $_str = $this->template;
        }

        while ((($start_pos = strpos($_str, '<'.$_rule.'>')) !== false)
        && (($end_pos = strpos($_str, '</'.$_rule.'>')) !== false)
        && ($start_pos < $end_pos)) {
            $tag_len_start = strlen('<' . $_rule . '>');
            $tag_len_end   = strlen('</' . $_rule . '>');

            $content = '';
            if ($_show) {
                $content = substr($_str, $start_pos + $tag_len_start, $end_pos - ($start_pos + $tag_len_start));
            }

            $_str = substr_replace($_str, $content, $start_pos, $end_pos + $tag_len_end - $start_pos);
        }

        if ($use_template) {
            $this->template = $_str;
        }

        return $_str;
    }

    ##################################################################
    /**
     * Finds every block of 'repeat:' for a single rule, then applies
     * all nested rules in $_value and prepends the result before
     * the block.
     *
     * Repeat block specifies single iteration of some loop and can contain
     * iteration-specific variable values. Therefore you can (and should)
     * pass some nested rule to every call of 'repeat'. Nested rules could
     * be only simple or IFs, but no REPEATs.
     *
     * @access protected
     * @param string $_rule Name of the rule. Examples: repeat:articles, repeat:users
     * @param array $_value Nested rules. Example: array('ID' => $id, 'if:has-enough-gold' => false)
     */
    protected function replace_repeat($_rule, $_value) {
        # cursor of our position in the template
        $search_start = 0;

        # there can be more occurences of repeat with this name in the template,
        # we need to find all
        while ((($start_pos = strpos($this->template, '<'.$_rule.'>', $search_start)) !== false)
        && (($end_pos = strpos($this->template, '</'.$_rule.'>', $search_start)) !== false)
        && ($start_pos < $end_pos)) {
            $tag_len_start = strlen('<' . $_rule . '>');
            $tag_len_end   = strlen('</' . $_rule . '>');

            # text between opening and closing repeat tag
            $str = substr($this->template, $start_pos + $tag_len_start, $end_pos - ($start_pos + $tag_len_start));

            # we have to apply every rule from $_value to this single iteration of text
            foreach ($_value as $pattern => $replacement) {
                if (preg_match('/^if:.+/', $pattern)) {
                    $str = $this->replace_if($pattern . self::$security_token, (bool)$replacement, $str);
                }
                else {
                    $pattern.= self::$security_token;
                    list($pattern, $replacement) = $this->get_replacement($pattern, $replacement);
                    $str = str_replace('{'.$pattern.'}', $replacement, $str);
                }
            }

            $this->template = substr_replace($this->template, $str, $start_pos, 0);

            # we then move cursor after this block, so we can find another with this name
            $search_start = strlen($str) + $end_pos + $tag_len_end;
        }
    }

    ##################################################################
    /**
     * Returns template in the to-this-moment processed state.
     * This can be useful, if you need to incorporate template
     * into another (parent) template and you have some rules, you could apply
     * only in the parent template.
     * It is very rare, but can happen.
     *
     * @access public
     * @return string Current state of the template. There can be some non-applied rules
     * and rules still contains security token.
     */
    public function raw() {
        return $this->template;
    }

    ##################################################################
    /**
     * Standard method for obtaining the final state of the template.
     * If there are some repeat blocks, we delete them all (remember: applied itrations
     * are no more within repeat block).
     * We apply false on all stil existing if block (and true to their counterparts).
     * If there are some URL: patterns, we call @see Templatte::create_urls.
     * And finally we remove security token and returns the result.
     *
     * You can no longer apply any rules to the template after calling this method.
     *
     * @access public
     * @return string Final state of the template.
     */
    public function get() {
        $retstr = $this->template;

        $sec_token_len = strlen(self::$security_token);

        $last_offset = 0;

        while (($start_pos = strpos($retstr, '<repeat:', $last_offset)) !== false) {
            $found = false;
            # najdeme najblizsi security token
            $token_pos = strpos($retstr, self::$security_token.'>', $start_pos+8);
            if ($token_pos !== false) {
                $chars = ($token_pos + $sec_token_len + 1) - $start_pos;

                # zapamatame si retazec medzi repeat a sec. tokenom
                $tag_name = substr($retstr, $start_pos+1, $chars-2);
                # v tag name je retazec repeat:blabla-sec-token
                $tag_name_len = strlen($tag_name);

                # zistime, ci medzi repeat a security tokenom nie je >
                if (strpos($tag_name, '>') === false) {
                    # najdeme najblizsi </repeat:blabla-sec-token>

                    $end_pos = strpos($retstr, '</' . $tag_name . '>', $start_pos + $tag_name_len);

                    if ($end_pos !== false) {
                        $found = true;
                        # nasli sme koncovy tag, vymazeme cely repeat
                        $retstr = substr_replace($retstr, "", $start_pos, $end_pos + $tag_name_len + 3 - $start_pos);
                    }
                }
            }
            if ($found) {
                $last_offset = $start_pos;
            }
            else {
                $last_offset = $start_pos + 8;
            }
        }

        if (preg_match_all('/<(if:.*'.self::$security_token.')>/Um', $retstr, $matches)) {
            foreach ($matches[1] as $each_if) {
                $retstr = $this->replace_if($each_if, false, $retstr);
            }
        }

        if (!empty($this->options['urls'])) {
            $retstr = $this->create_urls($retstr);
        }

        $retstr = str_replace(self::$security_token . '}', '}', $retstr);

        return $retstr;
    }

    ##################################################################
    /**
     * You can call get() simply by placing instance in the string context.
     */
    public function __toString() {
        return $this->get();
    }

    ##################################################################
    /**
     * Finds all occurences of patterns beginning with PARAM_ or PARVAL_
     * and tries to find a constant with the same name. If such a constant
     * exists, we replace pattern with constant value.
     *
     * Method is called in constructor, before security token is applied.
     * You can turn off this feature by setting the option 'params' to false.
     *
     * @access protected
     */
    protected function replace_params() {
        $matched = array();

        $matches = array();
        preg_match_all('/{(PARAM_.*|PARVAL_.*)}/Um', $this->template, $matches);
        foreach ($matches[1] as $param_name) {
            if (defined($param_name)
            && !isset($matched[$param_name])) {
                $this->_bind($param_name, constant($param_name));
                $matched[$param_name] = 1;
            }
        }
    }

    ##################################################################
    /**
     * I defined custom "html" tag called <action>.
     * Templatté translates it into a hidden input with name action.
     *
     * You can turn off this feature by setting the option 'tags' to false.
     *
     * @TODO: In the future, I plan to define more tags for form inputs.
     *
     * @access protected
     */
    protected function replace_tags() {
        $action = 'action';
        if (defined('PARAM_ACTION')) {
            $action = PARAM_ACTION;
        }

        $this->template = preg_replace_callback(
            '/<action ([^>]+)>/',
            function ($_matches) use ($action) {
                return '<input type="hidden" name="'.$action.'" value="'.$_matches[1].'">';
            },
            $this->template
        );
    }

    /**
     * This methos is similar to replace_params.
     * It finds all occurences of pattern beginning with URL_ and if
     * a constant with the same name is defined, it replaces pattern with
     * the value of this constant.
     *
     * Method is called in constructor, before security token is applied.
     * You can turn off this feature by setting the option 'urls' to false.
     *
     * @access proteted
     */
    ##################################################################
    protected function replace_urls() {
        $matched = array();

        $matches = array();
        if (preg_match_all('/{(URL_.*)}/Um', $this->template, $matches)) {
            foreach ($matches[1] as $param_name) {
                if (defined($param_name)
                && !isset($matched[$param_name])) {
                    $this->_bind($param_name, constant($param_name));
                    $matched[$param_name] = 1;
                }
            }
        }
    }

    ##################################################################
    /**
     * Pattern URL: is different from URL_. The latter is simple reference
     * to the value of a constant. URL: can require more complex handling.
     * Templatté extracts the part after URL: and passes it to the handler
     * method which you can set by calling @see set_url_handler.
     *
     * Default handler is Templatte::url and does absolutely nothing, just returns
     * the original string.
     *
     * Handler must be method of the class and accept one string parameter.
     *
     * Method is called in constructor, before security token is applied.
     * You can turn off this feature by setting the option 'urls' to false.
     *
     * @access proteted
     */
    protected function create_urls($tpl_str) {
        if (method_exists(self::$url_class, self::$url_method)) {
            $matched = array();

            $matches = array();
            preg_match_all('/{(URL:.*)'.self::$security_token.'}/Um', $this->template, $matches);

            foreach ($matches[1] as $param_name) {
                if (!isset($matched[$param_name])) {
                    $repl = call_user_func_array(
                        array(
                            self::$url_class,
                            self::$url_method
                        ),
                        array(
                            substr($param_name, 4),
                        )
                    );

                    $repl    = htmlspecialchars(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]+/', '', $repl), ENT_QUOTES);
                    $tpl_str = str_replace('{'.$param_name . self::$security_token . '}', $repl, $tpl_str);

                    $matched[$param_name] = 1;
                }
            }
        }

        return $tpl_str;
    }

    ##################################################################
    /**
     * This method translates text string in the templates using your
     * custom language subsystem.
     * Similar to create_urls. Finds all occurences of pattern L: and
     * passed it to the handler method which you can set by calling
     * set_lang_handler
     *
     * Default handler is Templatte::lang and does absolutely nothing, just returns
     * the original string.
     *
     * Handler must be method of the class and accept one string parameter.
     *
     * Method is called in constructor, before security token is applied.
     * You can turn off this feature by setting the option 'langs' to false.
     *
     * @access proteted
     */
    protected function replace_langs() {
        if (method_exists(self::$lang_class, self::$lang_method)) {
            $matched = array();

            $matches = array();
            preg_match_all('/{(L:.*)}/Um', $this->template, $matches);
            foreach ($matches[1] as $param_name) {
                if (!isset($matched[$param_name])) {
                    $this->_bind(
                        $param_name,
                        call_user_func_array(
                            array(
                                self::$lang_class,
                                self::$lang_method
                            ),
                            array(
                                substr($param_name, 2),
                            )
                        )
                    );
                    $matched[$param_name] = 1;
                }
            }
        }
    }

    ##################################################################
    /**
     * Security token for the patterns protects your template from
     * vulnerable input. It just appends some random text to the
     * every bind-able pattern (simple, if and repeat).
     *
     * We consider all patterns in the template file to be secure.
     *
     * Security token is a static member of the class, so every instance of
     * Templatte in the current PHP process will have the same token.
     * So you can use raw() methods and binds partialy processed templates
     * to the other templates.
     *
     * The token is applied in the constructor and removed in get() method.
     *
     * @access protected
     */
    protected function apply_security_token() {
        if (self::$security_token == '') {
            $str = '';
            for ($i = 0; $i < 10; $i++) {
                $str.= chr(rand(97, 122));
            }
            self::$security_token = '-sec-token-' .$str;
        }

        $this->template = preg_replace('/}/U', self::$security_token . '}', $this->template);
        $this->template = preg_replace('/(<\/?)((?:if!?|repeat):.+)>/Um', '\1\2' . self::$security_token . '>', $this->template);
    }

    ##################################################################
    /**
     * Default Dummy url handler for URL: patterns.
     *
     * @see replace_urls
     * @access public
     * @param string $_url Part of the 'URL:' pattern after the double-colon
     * @return string not-modified input parameter
     */
    public static function url($_url) {
        return $_url;
    }

    ##################################################################
    /**
     * Default Dummy lang handler for L: patterns.
     *
     * @see replace_urls
     * @access public
     * @param string $_url Part of the 'L:' pattern after the double-colon
     * @return string not-modified input parameter
     */
    public static function lang($_str) {
        return $_str;
    }

    ##################################################################
    /**
     * You can (and probably want) set your own url handler
     * for the URL: patterns.
     * You need to call this method once (eg. in the config file),
     * new handler will be available in all instances.
     *
     * Example:
     * etc/config.php:
     * Templatte::set_url_handler('Frappe', 'url');
     *
     * @access public
     * @param mixed $_class Name or instance of the class which defines a handler.
     * @param string $_method Name of the method (static or non-static)
     */
    public static function set_url_handler($_class, $_method) {
        self::$url_class  = $_class;
        self::$url_method = $_method;
    }

    ##################################################################
    /**
     * You can (and probably want) set your own url handler
     * for the L: patterns.
     * You need to call this method once (eg. in the config file),
     * new handler will be available in all instances.
     *
     * Example:
     * etc/config.php:
     * Templatte::set_lang_handler('Lang', 'get');
     *
     * @access public
     * @param mixed $_class Name or instance of the class which defines a handler.
     * @param string $_method Name of the method (static or non-static)
     */
    public static function set_lang_handler($_class, $_method) {
        self::$lang_class  = $_class;
        self::$lang_method = $_method;
    }
}

######################################################################
if (!class_exists('tpl')) {
    class tpl extends Templatte {
    }
}

######################################################################
if (!class_exists('peruntpl')) {
    class peruntpl extends Templatte {
    }
}

######################################################################
}

######################################################################

