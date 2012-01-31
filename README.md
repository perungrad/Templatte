Templatté
==========

Templatté (pronounced [templatte:]) is yet another PHP templating engine.
It uses template files with simple replace patterns, supports conditions, loops
and some neat tricks.

Installation
------------

Just copy the file somewhere in your PHP include path or your project
directory.

Templatté in default configuration looks for template files in the `tpl` directory
and with file extension `.tpl`.
You can change this behavior either globally by setting the constants `TEMPLATTE_DIR`
and `TEMPLATTE_EXT`, or for each instance by settings options to the constructor.

Usage examples
--------------

### Types of patterns ###

Templates uses a few types of patterns. This is just a list:

  1. simple patterns
    * `{NAME}`
  2. conditional patterns
    * `<if:logged>...</if:logged>`
    * `<if!:logged>...</if!:logged>`
  3. loops
    * `<repeat:articles>`
  4. `L:` or language pattern
    * `{L:Hello}`
  5. `PARAM_` pattern
    * `{PARAM_USR_ID}`
    * `{PARVAL_ACTION_LOGIN}`)
  6. `URL_` pattern
    * `{URL_PROFILE}`
  7. `URL:` pattern
    * `{URL:/article/detail/5-reasons-to-use-templatte}`

### Some rules: ###

  * You can call bind (and repeat) method multiple times
  * Once you call get() or use a instance in the string context,
    the template is considered as final and you can not bind more values
    to this instance
  * Order matters. You can not redefine previously binded values.
  * If you have a pattern inside `repeat:` block and you bind value to it by calling `bind`
    instead of `repeat`, you've set the value for this pattern in all the iterations (order matters, remember).
  * There is no unbind
  * Replacing happens immediately after binding.

### Simple Patterns ###


#### Template File `tpl/simple.tpl`: ####

    <html>
        <title>{TITLE}</title>
        <body>
            Hello {WORLD}. You have {NUM} new messages.
        </body>
    </html>

#### PHP File: ####

    <?php

    require_once 'include/templatte.php';

    # there is no need to specify directory and extension. Just the name of the file.
    $tpl = new Templatte('simple');

    # you can call bind by one array paramater and pass multiple rules
    $tpl->bind(array(
        'TITLE', => 'Hello World',
        'NUM'    => 5,
    ));

    # or you can call it with two parameters and pass only one rule
    $tpl->bind('WORLD', 'Jonathan');

    # you can bind multiple times

    echo $tpl;

#### Result: ####

    <html>
        <title>Hello World</title>
        <body>
            Hello Jonathan. You have 5 new messages.
        </body>
    </html>

### Conditional patterns ###

#### Template File `tpl/html/header.tpl`: ####

    <section>
        <if:logged>
            Hello {NAME}.
            <if:unread>
                You have {NUM} new messages.
            </if:unread>

            <if!:unread>
                You have no new messages.
            </if!:unread>
        </if:logged>
    </section>

#### PHP File: ####

    <?php

    require_once 'include/templatte.php';

    $tpl = new Templatte('html/header');

    $tpl->bind(array(
        'if:logged' => true,
        'NAME'      => 'Jonathan',
    ));

    echo $tpl;

#### Result: ####

`if:` pattern accepts single parameter treated as a boolean. If it's `true`,
the block of text between the positive form of pattern tags stays in the result
and whole negative forms goes away. There can be more positive and negative blocks.

If you do not bind any value to some `if:` patterns, it is same as binding `false`.

    <section>

            Hello Jonathan.

                You have no new messages.

    </section>

### Loops ###

#### Template File `tpl/html/paginator.tpl` ####

    <section>
        <repeat:pages>
            <if!:current><a href='/articles/listing/page:{PAGE_NUM}'></if!:current>
                {PAGE_NUM}
            <if!:current></a></if!:current>
        </repeat:pages>
    </section>

#### PHP File: ####

    <?php

    $tpl = new Templatte('html/paginator');

    $pages        = array(1, 2, 3, 4, 5);
    $page_current = 4;

    foreach ($pages as $each_page) {
        $tpl->repeat('pages', array(
            'PAGE_NUM'   => $each_page,
            'if:current' => $each_page == $page_current,
        ):
    }

    echo $tpl;

#### Result: ####

    <section>
            <a href='/articles/listing/page:1'>
                1
            </a>
            <a href='/articles/listing/page:2'>
                2
            </a>
            <a href='/articles/listing/page:3'>
                3
            </a>

                4

            <a href='/articles/listing/page:5'>
                5
            </a>
    </section>

### Language (L:) pattern ###

Language pattern is useful for creating multi-language websites.
You can connect Templatte to some other class, which provides
translations.

Language patterns are enabled by default and translations are
handled by dummy method Templatte::lang().

You can disable language patterns in constructor by seeting option
`langs` to `false`.

    <?php
    # disable language pattern
    $tpl = new Templatte('tpl-file', array(
        'langs' => false,
    ));

#### Template File `tpl/user/profile.tpl` ####

    <section>
        <h1>{L:Your Profile}</h1>

        <p>{L:Login}: {LOGIN}</p>
    </section>

#### PHP File ####

    <?php

    # simple language translation class
    class Lang {
        protected static $langs = array(
            'Your Profile' => 'Váš profil',
            'Login'        => 'Prihlasovacie meno',
        );

        public static function get($key) {
            return isset(self::$langs[$key]) ? self::$langs[$key] : $key;
        }
    }

    Templatte:set_lang_handler('Lang', 'get');
    # You can pass also an instance of the class and method doesn't have to be static

    $tpl = new Templatte('user/profile');

    $tpl->bind(array(
        'LOGIN' => 'Jonatán',
    ));

    echo $tpl;

#### Result: ####

    <section>
        <h1>Váš profil</h1>

        <p>Prihlasovacie meno: Jonatán</p>
    </section>


### Parameter patterns ###

If you are using constants for naming URL and form parameters, you will find
these two patterns very useful.

In your template file you just use patterns named `PARAM_*` and `PARVAL_` and
they are automatically bound and replaces with their coresponding constant
values (if such a constant exists).

Parameter patterns are enabled by default. You can disable them by setting option
`params` to `false`.

    $tpl = new Templatte('file-name', array(
        'params' => false,
    ));

#### Template File `tpl/article/new.tpl`: ####

    <form method='post' action=/post.php'>
        <input type='hidden' name='{PARAM_ACTION}' value='{PARVAL_ACTION_NEW_ARTICLE}'>

        <div class='form_item'>
            Title:
            <input type='text' name='{PARAM_ART_TITLE}'>
        </div>

        <div class='form_item'>
            Text:
            <textarea name='{PARAM_ART_TEXT}'></textarea>
        </div>

        <input type='submit' value='Save'>
    </form>

#### PHP File: ####

    <?php

    define('PARAM_ACTION',    'action');
    define('PARAM_ART_TITLE', 'title');
    define('PARAM_ART_TEXT',  'text');

    define('PARVAL_ACTION_NEW_ARTICLE', 'new-article');

    $tpl = new Templatte('article/new');

    echo $tpl;

#### Result: ####

    <form method='post' action=/post.php'>
        <input type='hidden' name='action' value='new-article'>

        <div class='form_item'>
            Title:
            <input type='text' name='title'>
        </div>

        <div class='form_item'>
            Text:
            <textarea name='text'></textarea>
        </div>

        <input type='submit' value='Save'>
    </form>

### URL Patterns ###

There are two forms of URL patterns.

The first is very similar to parameter patterns. If you have
constants for your URL and all have prefix `URL_`, you can
use patterns like `URL_*` in your template file. And they will
be automatically bound.

The second is more powerful and is similar to language pattern.
You can connect your URL providing method to Templatte and all
of `URL:*` patterns in the template file will be passed to that
method. So, you can have your own system for generating urls 
directly connected to your template.

You can disable both of these patterns in constructor by setting option
`urls` to `false`.

    $tpl = new Templatte('tpl-file', array(
        'urls' => false,
    ));

#### Template File `tpl/article/listing` ####

    <a href='{URL_BASE}'>Home Page</a>

    <article>
        <h1><a href='{URL:/article/detail/7-reasons-why-use-templatte}'>7 reasons why use Templatté</a></h1>

        <p>I could not make up a single one.</p>
    </article>

#### PHP File: ####

    <?php

    define('URL_BASE', 'http://example.com/');

    class Url {
        protected static $aliases = array(
            '/article/detail' => '/item',
        );

        public static public url($url) {
            if ($url{0} != '/') {
                $url = '/' . $url;
            }

            foreach (self::$aliases as $each_alias => $new_value) {
                $q_alias = preg_quote($each_alias, '~');

                if (preg_match('~^'.$q_alias.'~', $url)) {
                    $url = preg_replace('~^'.$q_alias.'~', $new_value, $up);
                    return $url;
                }
            }
        }
    }

    $tpl = new Templatte('article/listing');

#### Result: ####
    
    <a href='http://example.com/'>Home Page</a>

    <article>
        <h1><a href='/item/7-reasons-why-use-templatte}'>7 reasons why use Templatté</a></h1>

        <p>I could not make up a single one.</p>
    </article>

