<?php

require_once('EasyLibs.php');



class EasyWiki {
    public static function searchFile($_Pattern,  $_Callback = null) {
        return array_map(
            function ($_Path) use ($_Callback) {
                $_Item = array(
                    'cTime'  =>  filectime($_Path),
                    'mTime'  =>  filemtime($_Path)
                );
                $_Path = iconv(ini_get('default_charset'), 'UTF-8', $_Path);

                $_Item['title'] = pathinfo($_Path, PATHINFO_FILENAME);

                if (is_callable( $_Callback ))
                    $_Item = call_user_func($_Callback, $_Path, $_Item);

                return $_Item;
            },
            glob($_Pattern)
        );
    }

    public $dataBase;

    public function __construct($_Data_Path) {
        $this->dataBase = new SQLite("{$_Data_Path}/EasyWiki");

        $this->dataBase->createTable('Entry', array(
            'EID'     =>  'Integer Primary Key AutoIncrement',
            'Type'    =>  'Integer default 0',
            'Title'   =>  'Text not Null Unique',
            'AID'     =>  'Integer not Null',
            'Source'  =>  'Text'
        ));

        $this->dataBase->createTable('User', array(
            'UID'       =>  'Integer Primary Key AutoIncrement',
            'Email'     =>  'Text not Null Unique',
            'Salt'      =>  'Integer not Null',
            'PassWord'  =>  'Text not Null',
            'Auth'      =>  'Integer default 1',
            'cTime'     =>  'Integer not Null',
            'aTime'     =>  'Integer default 0'
        ));
    }

/* ---------- 用户模块 ---------- */

    private static function encrypt($_Raw,  $_Salt = null) {
        $_Salt = $_Salt  ?  $_Salt  :  (time() + mt_rand(100, mt_getrandmax()));

        return array(
            'salt'  =>  $_Salt,
            'code'  =>  md5( "{$_Raw}{$_Salt}" )
        );
    }

    private $authGroup = array('Admin', 'Director', 'Writer', 'Reader');

    public function bindSession($_User, &$_Profile) {
        $_Profile['UID'] = $_User['UID'];

        $_Profile['auth'] = json_decode(
            file_get_contents(
                "data/Auth/{$this->authGroup[$_User['Auth']]}.json"
            ),
            true
        );
        $_SESSION = $_Profile;
    }

    public function addUser($_IPA) {
        $_User = $this->dataBase->query(array(
            'select'  =>  'UID',
            'from'    =>  'User'
        ));
        $_Auth = 1;

        if (! count($_User)) {
            if (($_IPA != '127.0.0.1')  &&  ($_IPA != '::1'))
                return array(
                    'message'  =>  "请站长在 <em>localhost</em> 完成“管理员”账号的初始化……"
                );
            $_Auth = 0;
        }

        if (! filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL))
            return array('message' => "请填写规范的 Email 地址！");

        $_PassWord = self::encrypt( $_POST['password'] );

        if (! $this->dataBase->User->insert(array(
            'Email'     =>  $_POST['email'],
            'Salt'      =>  $_PassWord['salt'],
            'PassWord'  =>  $_PassWord['code'],
            'Auth'      =>  $_Auth,
            'cTime'     =>  time()
        )))
            return  array('message' => "该电邮已注册！");

        $this->dataBase->createTable('Profile', array(
            'UID'       =>  'Integer not Null',
            'NickName'  =>  'Text not Null',
            'Gender'    =>  'Integer default 0',
            'Portrait'  =>  'Text'
        ));

        $_User = $this->dataBase->query(array(
            'select'  =>  'UID, Auth',
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

        $this->dataBase->Profile->insert( $_Profile );

        $this->bindSession($_User[0], $_Profile);

        return  array('message' => "注册成功！请立即登录此账号以激活~");
    }

    public function login() {
        $_User = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL)  ?
            $this->dataBase->query(array(
                'select'  =>  '*',
                'from'    =>  'User',
                'where'   =>  "Email = '{$_POST['email']}'"
            )) :
            array();

        if (! count($_User))
            return  array('message' => "该电邮未注册！");

        $_PassWord = self::encrypt($_POST['password'], $_User[0]['Salt']);

        if ($_PassWord['code'] != $_User[0]['PassWord'])
            return array('message' => "密码错误！");

        if (
            ($_User[0]['UID'] === 0)  &&
            ($_User[0]['aTime'] === 0)  &&
            (! isset( $_SESSION['UID'] ))
        ) {
            $this->dataBase->User->delete("UID = {$_User[0]['UID']}");
            return array(
                'message'  =>  "管理员账号未及时登录，系统初始化失败，须重新注册管理员！"
            );
        }
        $_UID = $_User[0]['UID'];

        $this->dataBase->User->update("UID = '{$_UID}'", array(
            'aTime'  =>  time()
        ));

        $_Profile = $this->dataBase->query(array(
            'select'  =>  '*',
            'from'    =>  'Profile',
            'where'   =>  "UID = {$_UID}"
        ));

        $this->bindSession($_User[0], $_Profile[0]);

        $_Profile[0]['message'] = "欢迎进入 EasyWiki 的世界！";

        return $_Profile[0];
    }

/* ---------- 内容模块 ---------- */

    public function addEntry($_Type,  $_Name,  $_MarkDown,  $_URL = null) {
        file_put_contents("../data/{$_Name}.md", $_MarkDown);

        $this->dataBase->Entry->insert(array(
            'Type'    =>  $_Type,
            'Title'   =>  $_Name,
            'AID'     =>  $_SESSION['UID'],
            'Source'  =>  $_URL
        ));
    }
}