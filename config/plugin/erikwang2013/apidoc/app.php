<?php
return [
    'enable'  => true,
    'apidoc' => [
        'title'   => 'Apidoc',
        'desc'    => '',
        'groups' => [['title'=>'商品基础数据','name'=>'商品基础数据'],['title'=>'采购管理','name'=>'采购管理'],['title'=>'销售管理','name'=>'销售管理'],['title'=>'库存管理','name'=>'库存管理'],['title'=>'财务管理','name'=>'财务管理'],['title'=>'CRM','name'=>'CRM'],['title'=>'生产制造','name'=>'生产制造'],['title'=>'订单管理OMS','name'=>'订单管理OMS'],['title'=>'仓储管理WMS','name'=>'仓储管理WMS'],['title'=>'运输管理TMS','name'=>'运输管理TMS'],['title'=>'设备管理EAM','name'=>'设备管理EAM'],['title'=>'质量管理QMS','name'=>'质量管理QMS'],['title'=>'人力资源','name'=>'人力资源'],['title'=>'会员零售','name'=>'会员零售'],['title'=>'审批工作流','name'=>'审批工作流'],['title'=>'消息通知','name'=>'消息通知'],['title'=>'项目管理','name'=>'项目管理'],['title'=>'自定义报表','name'=>'自定义报表'],['title'=>'BI看板','name'=>'BI看板'],['title'=>'文档管理DMS','name'=>'文档管理DMS'],['title'=>'打印模板','name'=>'打印模板'],['title'=>'平台管理','name'=>'平台管理'],['title'=>'开放接口','name'=>'开放接口'],['title'=>'客户端认证','name'=>'客户端认证'],['title'=>'系统管理','name'=>'系统管理']],
        'apps'    => [
            [
                'title'  => 'Api接口',
                'path'   => 'app\controller',
                'key'    => 'erik.xyz',
                'groups' => [['title'=>'商品基础数据','name'=>'商品基础数据'],['title'=>'采购管理','name'=>'采购管理'],['title'=>'销售管理','name'=>'销售管理'],['title'=>'库存管理','name'=>'库存管理'],['title'=>'财务管理','name'=>'财务管理'],['title'=>'CRM','name'=>'CRM'],['title'=>'生产制造','name'=>'生产制造'],['title'=>'订单管理OMS','name'=>'订单管理OMS'],['title'=>'仓储管理WMS','name'=>'仓储管理WMS'],['title'=>'运输管理TMS','name'=>'运输管理TMS'],['title'=>'设备管理EAM','name'=>'设备管理EAM'],['title'=>'质量管理QMS','name'=>'质量管理QMS'],['title'=>'人力资源','name'=>'人力资源'],['title'=>'会员零售','name'=>'会员零售'],['title'=>'审批工作流','name'=>'审批工作流'],['title'=>'消息通知','name'=>'消息通知'],['title'=>'项目管理','name'=>'项目管理'],['title'=>'自定义报表','name'=>'自定义报表'],['title'=>'BI看板','name'=>'BI看板'],['title'=>'文档管理DMS','name'=>'文档管理DMS'],['title'=>'打印模板','name'=>'打印模板'],['title'=>'平台管理','name'=>'平台管理'],['title'=>'开放接口','name'=>'开放接口'],['title'=>'客户端认证','name'=>'客户端认证'],['title'=>'系统管理','name'=>'系统管理']],
            ]
        ],
        'definitions'  => "app\common\controller\Definitions",
        'auto_url' => ['letter_rule' => 'lcfirst', 'prefix' => ''],
        'auto_register_routes' => false,
        'cache' => ['enable' => false],
        'auth' => ['enable' => false, 'password' => '123456', 'secret_key' => 'apidoc#erik', 'expire' => 86400],
        'ignored_methods' => [],
        'params' => [],
        'responses' => [],
        'database' => [],
        'docs' => [],
        'generator' => [],
    ],
];
