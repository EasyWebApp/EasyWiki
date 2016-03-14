<?php

set_time_limit(0);

require_once('php/EasyLibs.php');



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

    //  HTML to MarkDown
    $_Marker = new HTML_MarkDown($_GET['url'], $_GET['selector']);

    $_Name = iconv($_Marker->CharSet, 'GBK', $_Marker->root['h1']->text());

    if (empty( $_Name )) {
        preg_match($_GET['name'], $_GET['url'], $_Name);
        $_Name = $_Name[1];
    }
    $_Marker->convertTo("../data/{$_Name}.md");

    //  Fetch History
    $_SQL_DB = new SQLite('../data/fetch');

    $_SQL_DB->createTable('Page', array(
        'PID'    =>  'Integer Primary Key',
        'URL'    =>  'Text not Null Unique',
        'Times'  =>  'Integer default 0',
        'Title'  =>  "Text default ''"
    ));
    foreach ($_Marker->link['inner'] as $_Link)
        $_SQL_DB->Page->insert(array('URL' => $_Link));

    $_Page = $_SQL_DB->query(array(
        'select'  =>  'PID, URL',
        'from'    =>  'Page',
        'where'   =>  "(URL = '{$_GET['url']}') and (Times = 0)"
    ));

    if (count( $_Page ))
        $_SQL_DB->Page->update("PID = {$_Page[0]['PID']}", array(
            'Times'  =>  1
        ));

    return array(
        'header'    =>    array(
            'Content-Type'  =>  'application/json'
        ),
        'data'      =>    $_SQL_DB->query(array(
            'select'  =>  'URL',
            'from'    =>  'Page',
            'where'   =>  'Times = 0'
        ))
    );
});