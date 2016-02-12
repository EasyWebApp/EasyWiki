<?php

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
});