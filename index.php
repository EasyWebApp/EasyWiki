<?php

if (!  preg_match('/bot|spider|Slurp/i', $_SERVER['HTTP_USER_AGENT'])) {
    echo file_get_contents('index.html');
    exit(0);
}


$_Name = isset( $_GET['_escaped_fragement_'] )  ?
        substr($_GET['_escaped_fragement_'], 5, -3)  :  'index';

if (file_exists( "data/cache/{$_Name}.html" )) {
    echo  file_get_contents( "data/cache/{$_Name}.html" );
    exit(0);
}


include('core/php/HyperDown.php');
include('core/php/EasyLibs.php');

$_Parser = new HyperDown\Parser;

$_Marker = new HTML_MarkDown($_Parser->makeHtml(
    file_get_contents( "data/{$_Name}.md" )
));

foreach ($_Marker->link['inner'] as $_Link) {
    $_HREF = $_Link->getAttribute('href');

    if (preg_match('/\.(md|markdown)$/i', $_HREF))
        $_Link->setAttribute('href',  '#!data/' . $_HREF);
}
ob_start();

?><!DocType HTML>
<html><head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <title><?php  echo trim($_Marker->title);  ?></title>
</head><body><?php

    echo $_Marker->DOM->html();

?><hr />
    <div>
        Powered by
        <a target="_blank" href="http://git.oschina.net/Tech_Query/EasyWiki">
            EasyWiki
        </a>
    </div>
</body></html><?php

$_Cache = new FS_File("data/cache/{$_Name}.html");
$_Cache->write( ob_get_contents() );