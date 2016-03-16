<?php

set_time_limit(0);

require_once('php/EasyLibs.php');



$_HTTP_Server = new HTTPServer();

$_SQL_DB = new SQLite('EasyWiki');


$_HTTP_Server->on('Get',  'search/',  function () {

    $_KeyWord = iconv('UTF-8', ini_get('default_charset'), $_GET['keyword']);

    return json_encode(array_map(
        function ($_Path) {
            $_Entry = array(
                'cTime'  =>  filectime($_Path),
                'mTime'  =>  filemtime($_Path)
            );
            $_Path = iconv(ini_get('default_charset'), 'UTF-8', $_Path);

            $_Entry['URL'] = substr($_Path, 3);
            $_Entry['title'] = substr($_Path, 8, -3);

            return $_Entry;
        },
        glob("../data/*{$_KeyWord}*.md")
    ));

})->on('Get',  'category/',  function () {
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
})->on('Get',  'signUp/',  function () {

    global $_SQL_DB;

    $_SQL_DB->createTable('User', array(
        'UID'       =>  'Integer Primary Key AutoIncrement',
        'Email'     =>  'Text not Null Unique',
        'Salt'      =>  'Integer not Null',
        'PassWord'  =>  'Text not Null',
        'Auth'      =>  'Integer default 1',
        'cTime'     =>  'Integer not Null',
        'mTime'     =>  'Integer default 0'
    ));

    $_User = $_SQL_DB->query(array(
        'select'  =>  '*',
        'form'    =>  'User'
    ));
    $_IPA = $_SERVER['REMOTE_ADDR'];
    $_Auth = 1;

    if (! count($_User)) {
        if (($_IPA != '127.0.0.1')  &&  ($_IPA != '::1'))
            return json_encode(array(
                'message'  =>  "请站长在 <em>localhost</em> 完成“管理员”账号的初始化……"
            ));
        $_Auth = 0;
    }
    $_Salt = mt_rand(9, 9);

    if (! $_SQL_DB->User->insert(array(
        'Email'     =>  $_GET['email'],
        'Salt'      =>  $_Salt,
        'PassWord'  =>  md5( "{$_GET['password']}{$_Salt}" ),
        'Auth'      =>  $_Auth,
        'cTime'     =>  time()
    )))
        return json_encode(array(
            'message'  =>  "该电邮已注册！"
        ));

    return json_encode(array(
        'message'  =>  "注册成功！请立即登录此账号以激活~"
    ));

})->on('Get',  'spider/',  function () {

    //  HTML to MarkDown
    $_Marker = new HTML_MarkDown($_GET['url'], $_GET['selector']);

    $_Name = iconv(
        $_Marker->CharSet,  ini_get('default_charset'),  $_Marker->title
    );

    if (empty( $_Name )) {
        preg_match($_GET['name'], $_GET['url'], $_Name);
        $_Name = $_Name[1];
    }
    $_Marker->convertTo("../data/{$_Name}.md");

    //  Fetch History
    global $_SQL_DB;

    $_SQL_DB->createTable('Fetch', array(
        'PID'    =>  'Integer Primary Key',
        'URL'    =>  'Text not Null Unique',
        'Times'  =>  'Integer default 0',
        'Title'  =>  "Text default ''"
    ));
    foreach ($_Marker->link['inner'] as $_Link)
        $_SQL_DB->Fetch->insert(array('URL' => $_Link));

    $_Page = $_SQL_DB->query(array(
        'select'  =>  'PID, URL',
        'from'    =>  'Fetch',
        'where'   =>  "(URL = '{$_GET['url']}') and (Times = 0)"
    ));

    if (count( $_Page ))
        $_SQL_DB->Fetch->update("PID = {$_Page[0]['PID']}", array(
            'Times'  =>  1
        ));

    return array(
        'header'    =>    array(
            'Content-Type'  =>  'application/json'
        ),
        'data'      =>    $_SQL_DB->query(array(
            'select'  =>  'URL',
            'from'    =>  'Fetch',
            'where'   =>  'Times = 0'
        ))
    );
});