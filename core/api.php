<?php

require_once('php/EasyLibs.php');

function URL_Domain($_URL) {
    $_URL = explode('/', $_URL, 4);
    $_URL[0] = '';
    return  join('/',  array_slice($_URL, 0, 3));
}


$_HTTP_Server = new HTTPServer();

$_HTTP_Server->on('Get',  'category/',  function () {
    return json_encode(array(
        'entry'  =>  array(
            array(
                'title'  =>  "首页",
                'tips'   =>  "百科起始页"
            ),
            array(
                'title'  =>  "关于",
                'tips'   =>  "百科介绍页"
            )
        )
    ));
})->on('Get',  'spider/',  function () {
    include('php/phpQuery.php');

    phpQuery::newDocumentFile( $_GET['url'] );

    $_URL_Domain = URL_Domain( $_GET['url'] );

    return array(
        'header'    =>    array(
            'Content-Type'  =>  'application/json'
        ),
        'data'      =>    phpQuery::map(
            pq('a[href]'),
            function ($_Link) use ($_URL_Domain) {
                $_HREF = pq($_Link)->attr('href');
                $_URL_Host = parse_url($_HREF, PHP_URL_HOST);

                if (
                    ($_HREF[0] == '#')  ||
                    ($_URL_Host  &&  (URL_Domain($_HREF) != $_URL_Domain))
                )
                    return;

                return  empty( $_URL_Host )  ?
                      "{$_URL_Domain}/{$_HREF}"  :  $_HREF;
            }
        )
    );
});