<?php

if (!  preg_match('/bot|spider|Slurp/i', $_SERVER['HTTP_USER_AGENT'])) {
    echo file_get_contents('index.html');
    exit(0);
}

include('core/php/HyperDown.php');
include('core/php/EasyLibs.php');

$_Parser = new HyperDown\Parser;

$_MarkDown = file_get_contents(
    isset( $_GET['_escaped_fragement_'] )  ?
        $_GET['_escaped_fragement_'] :
        'data/index.md'
);
preg_match('/^\#\s+(.+)$/m', $_MarkDown, $_Title);

$_Marker = new HTML_MarkDown( $_Parser->makeHtml($_MarkDown) );

foreach ($_Marker->DOM['a[href]'] as $_Link) {
    $_HREF = $_Link->getAttribute('href');
    $_URL = $_Marker->innerLink( $_HREF );

    if ($_URL  &&  preg_match('/\.(md|markdown)$/i', $_HREF))
        $_Link->setAttribute('href',  '#!data/' . $_HREF);
}

?><!DocType HTML>
<html><head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php  echo trim($_Title[1]);  ?></title>
</head><body><?php

    echo $_Marker->DOM->html();

?><hr />
    <div>
        Powered by
        <a target="_blank" href="http://git.oschina.net/Tech_Query/EasyWiki">
            EasyWiki
        </a>
    </div>
</body></html>