<?php

set_time_limit(0);


/* ---------- 基础库 ---------- */

require_once('php/EasyLibs.php');

function Local_CharSet($_Raw,  $_Raw_CS = 'UTF-8') {
    return  iconv($_Raw_CS, ini_get('default_charset'), $_Raw);
}

function iEncrypt($_Raw,  $_Salt = null) {
    $_Salt = $_Salt  ?  $_Salt  :  (time() + mt_rand(100, mt_getrandmax()));

    return array(
        'salt'  =>  $_Salt,
        'code'  =>  md5( "{$_Raw}{$_Salt}" )
    );
}

/* ---------- 通用逻辑 ---------- */

$_No_Login = array('search', 'category');

$_SQL_DB = new SQLite('EasyWiki');

$_HTTP_Server = new HTTPServer(false,  function ($_Route) {
    global  $_No_Login, $_SQL_DB;

    if (in_array($_Route[0], $_No_Login))  return;

    $_SQL_DB->createTable('Entry', array(
        'EID'     =>  'Integer Primary Key AutoIncrement',
        'Title'   =>  'Text not Null Unique',
        'AID'     =>  'Integer not Null',
        'Source'  =>  'Text'
    ));

    session_set_cookie_params(0, '/', '', FALSE, TRUE);

    session_start();
});

/* ---------- 业务逻辑 ---------- */

$_HTTP_Server->on('Get',  'search/',  function () {

    $_KeyWord = Local_CharSet( $_GET['keyword'] );

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
})->on('Post',  'signUp/',  function () {

    global $_SQL_DB, $_HTTP_Server;

    $_SQL_DB->createTable('User', array(
        'UID'       =>  'Integer Primary Key AutoIncrement',
        'Email'     =>  'Text not Null Unique',
        'Salt'      =>  'Integer not Null',
        'PassWord'  =>  'Text not Null',
        'Auth'      =>  'Integer default 1',
        'cTime'     =>  'Integer not Null',
        'aTime'     =>  'Integer default 0'
    ));

    $_User = $_SQL_DB->query(array(
        'select'  =>  '*',
        'from'    =>  'User'
    ));
    $_IPA = $_HTTP_Server->request->IPAddress;
    $_Auth = 1;

    if (! count($_User)) {
        if (($_IPA != '127.0.0.1')  &&  ($_IPA != '::1'))
            return json_encode(array(
                'message'  =>  "请站长在 <em>localhost</em> 完成“管理员”账号的初始化……"
            ));
        $_Auth = 0;
    }

    if (! filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL))
        return json_encode(array(
            'message'  =>  "请填写规范的 Email 地址！"
        ));

    $_PassWord = iEncrypt( $_POST['password'] );

    if (! $_SQL_DB->User->insert(array(
        'Email'     =>  $_POST['email'],
        'Salt'      =>  $_PassWord['salt'],
        'PassWord'  =>  $_PassWord['code'],
        'Auth'      =>  $_Auth,
        'cTime'     =>  time()
    )))
        return json_encode(array(
            'message'  =>  "该电邮已注册！"
        ));

    $_SQL_DB->createTable('Profile', array(
        'UID'       =>  'Integer not Null',
        'NickName'  =>  'Text not Null',
        'Gender'    =>  'Integer default 0',
        'Portrait'  =>  'Text'
    ));

    $_User = $_SQL_DB->query(array(
        'select'  =>  'UID',
        'from'    =>  'User',
        'where'   =>  "Email = '{$_POST['email']}'"
    ));

    $_Profile = array_map(
        function ($_Value) {
            return  is_array($_Value) ? $_Value[0] : $_Value;
        },
        filter_input_array(INPUT_POST, array(
            'NickName'  =>  FILTER_SANITIZE_STRING,
            'Gender'    =>  array(
                'filter'   =>  FILTER_VALIDATE_INT,
                'options'  =>  array(
                    'min_range'  =>  0,
                    'max_range'  =>  2,
                )
            ),
            'Portrait'  =>  FILTER_VALIDATE_URL
        ))
    );
    $_Profile['UID'] = $_User[0]['UID'];

    $_SQL_DB->Profile->insert( $_Profile );

    return json_encode(array(
        'message'  =>  "注册成功！请立即登录此账号以激活~"
    ));

})->on('Get',  'logIn/',  function () {

    global $_SQL_DB;

    $_User = $_SQL_DB->query(array(
        'select'  =>  '*',
        'from'    =>  'User',
        'where'   =>  "Email = '{$_GET['email']}'"
    ));

    if (! count($_User))
        return json_encode(array(
            'message'  =>  "该电邮未注册！"
        ));

    $_PassWord = iEncrypt($_GET['password'], $_User[0]['Salt']);

    if ($_PassWord['code'] != $_User[0]['PassWord'])
        return json_encode(array(
            'message'  =>  "密码错误！"
        ));

    $_UID = $_SESSION['UID'] = $_User[0]['UID'];

    $_SQL_DB->User->update("UID = '{$_UID}'", array(
        'aTime'  =>  time()
    ));

    $_Profile = $_SQL_DB->query(array(
        'select'  =>  '*',
        'from'    =>  'Profile',
        'where'   =>  "UID = {$_UID}"
    ));
    $_Profile[0]['message'] = "欢迎进入 EasyWiki 的世界！";

    return  json_encode( $_Profile[0] );

})->on('Post',  'deliver/',  function () {

    global $_SQL_DB;

    require('php/HyperDown.php');

    //  存文件
    $_Parser = new HyperDown\Parser;

    $_HTML = $_Parser->makeHtml( $_POST['Source_MD'] );

    $_Marker = new HTML_MarkDown( $_HTML );

    $_Name = filter_input(INPUT_POST, 'title', FILTER_VALIDATE_REGEXP, array(
        'options'  =>  array(
            'regexp'  =>  '/^[^\\/:\*\?"<>\|\.]{1,20}$/'
        )
    ))  ?
        $_POST['title']  :  Local_CharSet( $_Marker->title );

    $_Cache = new FS_File("../data/cache/{$_Name}.html", 'w');
    $_Cache->write( $_HTML );

    file_put_contents("../data/{$_Name}.md", $_POST['Source_MD']);

    //  存数据
    $_SQL_DB->Entry->insert(array(
        'Title'   =>  $_Name,
        'AID'     =>  $_SESSION['UID']
    ));

    return json_encode(array(
        'message'  =>  "词条更新成功！"
    ));

})->on('Post',  'spider/',  function () {

    global $_SQL_DB, $_HTTP_Server;

    if (! count($_SESSION))
        return $_HTTP_Server->setStatus(403);

    if (! filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL))
        return json_encode(array());

    //  HTML to MarkDown
    $_Marker = new HTML_MarkDown($_POST['url'], $_POST['selector']);

    $_Name = Local_CharSet($_Marker->title, $_Marker->CharSet);

    if (empty( $_Name )) {
        preg_match($_POST['name'], $_POST['url'], $_Name);
        $_Name = $_Name[1];
    }
    $_Marker->convertTo("../data/{$_Name}.md");

    $_SQL_DB->Entry->insert(array(
        'Title'   =>  $_Name,
        'AID'     =>  $_SESSION['UID'],
        'Source'  =>  $_POST['url']
    ));

    //  Fetch History

    $_SQL_DB->createTable('Fetch', array(
        'PID'    =>  'Integer Primary Key AutoIncrement',
        'URL'    =>  'Text not Null Unique',
        'Times'  =>  'Integer default 0',
        'Title'  =>  "Text default ''"
    ));
    foreach ($_Marker->link['inner'] as $_Link)
        $_SQL_DB->Fetch->insert(array('URL' => $_Link));

    $_Page = $_SQL_DB->query(array(
        'select'  =>  'PID, URL',
        'from'    =>  'Fetch',
        'where'   =>  "(URL = '{$_POST['url']}') and (Times = 0)"
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