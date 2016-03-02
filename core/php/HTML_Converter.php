<?php
//
//              >>>  HTML Converter  <<<
//
//
//      [Version]    v0.3  (2016-03-02)  Alpha
//
//      [Require]    PHP  v5.3+,
//
//                   phpQuery  v0.9.5+
//
//
//            (C)2016    shiy2008@gmail.com
//


require_once('phpQuery.php');


abstract class HTML_Converter {
    public static function getURLDomain($_URL) {
        $_URL = explode('/', $_URL, 4);
        $_URL[0] = '';
        return  join('/',  array_slice($_URL, 0, 3));
    }
    public $URL;
    public $domain;
    public $DOM;
    public $root;
    public $link = array(
        'inner'  =>  array(),
        'outer'  =>  array()
    );
    public $rule;

    private function innerLink($_URL) {
        $_Host = parse_url($_URL, PHP_URL_HOST);

        if (empty( $_Host ))
            return  parse_url($this->URL, PHP_URL_SCHEME) .
                ":{$this->domain}/{$_URL}";

        if (self::getURLDomain($_URL) == $this->domain)
            return $_URL;
    }

    public function __construct($_URL, $_Selector, $_Rule) {
        $this->URL = $_URL;
        $this->domain = self::getURLDomain($_URL);

        $this->DOM = phpQuery::newDocumentFile($_URL);

        $this->root = $this->DOM['body'];

        if (is_string( $_Selector )) {
            $_DOM = $this->root[ $_Selector ];
            if ( $_DOM->size() )  $this->root = $_DOM;
        }

        foreach ($this->root['a[href]'] as $_Link) {
            $_HREF = $_Link->getAttribute('href');

            if ($_HREF[0] != '#') {
                $_InnerURL = $this->innerLink($_HREF);
                array_push(
                    $this->link[$_InnerURL ? 'inner' : 'outer'],
                    $_InnerURL ? $_InnerURL : $_HREF
                );
            }
        }
        $this->rule = $_Rule;
    }

    public function convertTo($_File = null) {
        foreach ($this->rule  as  $_Selector => $_Callback)
            foreach ($this->root[$_Selector] as $_DOM) {
                $_DOM_ = pq($_DOM);

                $_DOM_->html(call_user_func(
                    $_Callback,  trim( $_DOM_->html() ),  $_DOM_
                ));
            }
        $_Text = trim( $this->root->text() );

        return  is_string( $_File )  ?
            file_put_contents($_File, $_Text)  :  $_Text;
    }
}


class HTML_MarkDown extends HTML_Converter {
    public function getTitleAttr($_DOM) {
        $_Title = ' "' . $_DOM->attr('title') . '"';
        return  (count($_Title) > 3)  ?  $_Title  :  '';
    }

    public function __construct($_URL,  $_Selector = null) {
        $_This = $this;

        parent::__construct($_URL, $_Selector, array(
            'h1, h2, h3, h4, h5, h6'  =>  function ($_HTML, $_DOM) {
                return  "\n\n" . str_repeat('#', $_DOM->get(0)->tagName[1]) .
                    " {$_HTML}";
            },
            'em'                      =>  function ($_HTML) {
                return  " *{$_HTML}*";
            },
            'b, strong'               =>  function ($_HTML) {
                return  " **{$_HTML}**";
            },
            'del'                     =>  function ($_HTML) {
                return  " ~~{$_HTML}~~";
            },
            'a[href]'                 =>  function ($_HTML, $_DOM) use ($_This) {
                return  "[{$_HTML}](" . $_DOM->attr('href') .
                    $_This->getTitleAttr($_DOM) . ')';
            },
            'ul > li'                 =>  function ($_HTML) {
                return  "\n - {$_HTML}";
            },
            'ol > li'                 =>  function ($_HTML, $_DOM) {
                return  "\n " . ($_DOM->prevAll()->size() + 1) . ". {$_HTML}";
            },
            'img'                     =>  function ($_HTML, $_DOM) use ($_This) {
                return  '![' . $_DOM->attr('alt') . '](' . $_DOM->attr('src') .
                    $_This->getTitleAttr($_DOM) . ')';
            },
            'hr'                      =>  function () {
                return  "\n\n---\n\n";
            },
            'p'                       =>  function ($_HTML) {
                return  "\n\n{$_HTML}\n\n";
            },
            'pre'                     =>  function ($_HTML) {
                return  "\n> {$_HTML}";
            },
            'code'                    =>  function ($_HTML) {
                return  " `{$_HTML}` ";
            },
            'pre > code'              =>  function ($_HTML) {
                return  "\n```\n{$_HTML}\n```\n";
            }
        ));
    }
}