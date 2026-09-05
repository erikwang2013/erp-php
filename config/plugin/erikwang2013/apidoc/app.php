<?php
return [
    'enable'  => true,
    'apidoc' => [
        'title'              => 'Apidoc',
        'desc'               => '',
        'apps'           => [
            [
                'title'=>'Api接口',
                'path'=>'app\controller',
                'key'=>'erik.xyz',
            ]
        ],
        'definitions'        => "app\common\controller\Definitions",
        'auto_url' => [
            'letter_rule' => "lcfirst",
            'prefix'=>"",
        ],
        'auto_register_routes'=>false,
        'cache' => ['enable' => false],
        'auth' => ['enable' => false, 'password' => '123456', 'secret_key' => 'apidoc#erik', 'expire' => 86400],
        'params'=>[],
        'responses'=>[],
    ],
];
