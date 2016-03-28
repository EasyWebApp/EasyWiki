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

$_No_Need = array(
    'logIn'    =>  array(
        'entry'     =>  array(
            'GET'  =>  true
        ),
        'category'  =>  array(
            'GET'  =>  true
        )
    ),
    'session'  =>  array(
        'user'    =>  array(
            'POST'  =>  true
        ),
        'online'  =>  array(
            'POST'  =>  true
        )
    )
);
function API_Filter($_Type, $_Model, $_Method) {
    global $_No_Need;

    $_Auth = $_No_Need[$_Type];

    if (isset( $_Auth[$_Model] )  &&  isset( $_Auth[$_Model][$_Method] ))
        return true;
}

$_SQL_DB = new SQLite('EasyWiki');

$_HTTP_Server = new HTTPServer(false,  function ($_Route, $_Request) {
    global  $_SQL_DB, $_HTTP_Server;

    if (API_Filter('logIn', $_Route[0], $_Request->method))  return;

    $_SQL_DB->createTable('Entry', array(
        'EID'     =>  'Integer Primary Key AutoIncrement',
        'Type'    =>  'Integer default 0',
        'Title'   =>  'Text not Null Unique',
        'AID'     =>  'Integer not Null',
        'Source'  =>  'Text'
    ));

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
    switch (count( $_User )) {
        case 0:     ;
        case 1:     {
            $_TimeOut = 0;
            if (empty( $_User[0])  ||  (! $_User[0]['aTime']))
                break;
        }
        default:    $_TimeOut = 172800;
    }

    session_set_cookie_params($_TimeOut, '/', '', FALSE, TRUE);

    session_start();

    if (! (
        API_Filter('session', $_Route[0], $_Request->method)  ||
        count($_SESSION)
    )) {
        $_HTTP_Server->setStatus(403);
        return false;
    }
    return $_User;
});

function New_Entry($_Type,  $_Name,  $_MarkDown,  $_URL = null) {
    global $_SQL_DB;

    file_put_contents("../data/{$_Name}.md", $_MarkDown);

    $_SQL_DB->Entry->insert(array(
        'Type'    =>  $_Type,
        'Title'   =>  $_Name,
        'AID'     =>  $_SESSION['UID'],
        'Source'  =>  $_URL
    ));
}

/* ---------- 业务逻辑 ---------- */

$_HTTP_Server->on('Get',  'entry/',  function () {

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
})->on('Post',  'user/',  function () {

    global $_SQL_DB, $_HTTP_Server;

    $_User = func_get_arg(2);
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
    $_Profile['UID'] = $_SESSION['UID'] = $_User[0]['UID'];

    $_SQL_DB->Profile->insert( $_Profile );

    return json_encode(array(
        'message'  =>  "注册成功！请立即登录此账号以激活~"
    ));

})->on('Post',  'online/',  function () {

    global $_SQL_DB;

    $_User = $_SQL_DB->query(array(
        'select'  =>  '*',
        'from'    =>  'User',
        'where'   =>  "Email = '{$_POST['email']}'"
    ));

    if (! count($_User))
        return json_encode(array(
            'message'  =>  "该电邮未注册！"
        ));

    $_PassWord = iEncrypt($_POST['password'], $_User[0]['Salt']);

    if ($_PassWord['code'] != $_User[0]['PassWord'])
        return json_encode(array(
            'message'  =>  "密码错误！"
        ));

    if (
        ($_User[0]['UID'] === 0)  &&
        ($_User[0]['aTime'] === 0)  &&
        (! isset( $_SESSION['UID'] ))
    ) {
        $_SQL_DB->User->delete("UID = {$_User[0]['UID']}");
        return json_encode(array(
            'message'  =>  "管理员账号未及时登录，系统初始化失败，须重新注册管理员！"
        ));
    }
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

})->on('Delete',  'online/',  function () {

    $_SESSION = array();

})->on('Post',  'entry/',  function () {

    $_Param = filter_input_array(INPUT_POST, array(
        'title'  =>  array(
            'filter'   =>  FILTER_VALIDATE_REGEXP,
            'options'  =>  array(
                'regexp'  =>  '/^[^\\/:\*\?"<>\|\.]{1,20}$/'
            )
        ),
        'type'   =>  array(
            'filter'   =>  FILTER_VALIDATE_INT,
            'options'  =>  array(
                'min_range'  =>  0,
                'max_range'  =>  2
            )
        )
    ));
    if (! is_int( $_Param['type'] ))
        return json_encode(array(
            'message'  =>  "词条类型错误！"
        ));

    require('php/HyperDown.php');

    $_Parser = new HyperDown\Parser;

    $_Marker = new HTML_MarkDown( $_Parser->makeHtml( $_POST['Source_MD'] ) );

    $_Name = Local_CharSet(
        $_Param['title']  ?  $_POST['title']  :  $_Marker->title
    );

    New_Entry($_Name, $_POST['Source_MD']);

    return json_encode(array(
        'message'  =>  "词条更新成功！"
    ));

})->on('Post',  'image/',  function () {

    $_File = $_FILES['editormd-image-file'];
    $_Type = explode('/', $_File['type'], 2);

    $_Return = array(
        'success'  =>  $_File['error'] ? 0 : 1,
        'message'  =>  $_File['error'] ? "失败……" : "成功！"
    );

    if ((! $_File['error'])  &&  ($_Type[0] == 'image')) {
        $_Path = '../data/image';
        @ mkdir($_Path);
        $_Path .= "/{$_File['name']}";

        move_uploaded_file($_File['tmp_name'], $_Path);

        $_Return['url'] = substr($_Path, 3);
    }

    return json_encode($_Return);

})->on('Post',  'spider/',  function () {

    global $_SQL_DB;

    if (! filter_input(INPUT_POST, 'url', FILTER_VALIDATE_URL))
        return json_encode(array());

    //  HTML to MarkDown
    $_Marker = new HTML_MarkDown($_POST['url'], $_POST['selector']);

    $_Name = Local_CharSet($_Marker->title, $_Marker->CharSet);

    if (empty( $_Name )) {
        preg_match($_POST['name'], $_POST['url'], $_Name);
        $_Name = $_Name[1];
    }
    New_Entry(0, $_Name, $_Marker->convert(), $_POST['url']);

    //  Fetch History
    $_SQL_DB->createTable('Fetch', array(
        'PID'    =>  'Integer Primary Key AutoIncrement',
        'URL'    =>  'Text not Null Unique',
        'Times'  =>  'Integer default 0',
        'Title'  =>  "Text default ''"
    ));
    foreach ($_Marker->link['inner'] as $_Link) {
        $_Title = trim( $_Link->getAttribute('title') );

        $_SQL_DB->Fetch->insert(array(
            'URL'    =>  $_Link->getAttribute('href'),
            'Title'  =>  $_Title ? $_Title : $_Link->textContent
        ));
    }
    $_SQL_DB->Fetch->update("URL = '{$_POST['url']}'", array(
        'Times'  =>  1
    ));

    return array(
        'header'    =>    array(
            'Content-Type'  =>  'application/json'
        ),
        'data'      =>    $_SQL_DB->query(array(
            'select'  =>  'URL, Title',
            'from'    =>  'Fetch',
            'where'   =>  'Times = 0'
        ))
    );
})->on('Get',  'auth/',  function () {

    $_Auth = json_decode(file_get_contents('auth.json'), true);

    $_Return = array();

    foreach ($_Auth  as  $_API => $_Config) {
        $_Config['API_URL'] = $_API;
        $_Return[] = $_Config;
    }

    return array(
        'header'    =>    array(
            'Content-Type'  =>  'application/json'
        ),
        'data'      =>    $_Return
    );
})->on('Post',  'auth/',  function () {

    $_Auth = array();

    foreach ($_POST  as  $_Key => $_Value)
        if ($_Value[0] == '{')
            $_Auth[$_Key] = json_decode($_Value);

    file_put_contents('auth.json', json_encode($_Auth));

    return json_encode(array(
        'message'  =>  "权限更新成功！"
    ));
});