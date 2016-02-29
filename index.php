<?php

if (!  preg_match('/bot|spider|Slurp/i', $_SERVER['HTTP_USER_AGENT'])) {
    echo file_get_contents('index.html');
    exit(0);
}

include('core/php/HyperDown.php');

$_Parser = new HyperDown\Parser;

$_MarkDown = file_get_contents(
    isset( $_GET['_escaped_fragement_'] )  ?
        $_GET['_escaped_fragement_'] :
        'data/index.md'
);
preg_match('/^\#\s+(.+)$/m', $_MarkDown, $_Title);

?><!DocType HTML>
<html><head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php  echo trim($_Title[1]);  ?></title>
</head><body><?php

    echo  $_Parser->makeHtml( $_MarkDown );

?></body></html>