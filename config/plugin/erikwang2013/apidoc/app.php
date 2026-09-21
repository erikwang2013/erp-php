<?php

/**
 * 取文档站凭据，**弱占位值一律按未配置处理**。
 *
 * .env.example 里预置的是 CHANGE_ME_* 占位串，而本文件是公开仓库的一部分 —— 照抄模板
 * 上线等于「口令公开」（vendor Auth 用 md5 比对，反查即得），比留空更危险：留空时
 * `Auth` 抛「缺少 secret_key」，文档站直接拒绝访问。故这里与 app/functions.php:142 的
 * 弱占位判定同正则，命中即视为空，逼部署方显式设值（scripts/gen-env-keys.sh 会生成强随机值）。
 */
$credential = static function (string $key): string {
    $value = (string) env($key, '');

    return preg_match('/(change[-_]me|xxx)/i', $value) ? '' : $value;
};

return [
    'enable' => true,
    'apidoc' => [
        'title' => 'Apidoc',
        'desc' => '',
        'groups' => [['title' => '商品基础数据','name' => '商品基础数据'],['title' => '采购管理','name' => '采购管理'],['title' => '销售管理','name' => '销售管理'],['title' => '库存管理','name' => '库存管理'],['title' => '财务管理','name' => '财务管理'],['title' => 'CRM','name' => 'CRM'],['title' => '生产制造','name' => '生产制造'],['title' => '订单管理OMS','name' => '订单管理OMS'],['title' => '仓储管理WMS','name' => '仓储管理WMS'],['title' => '运输管理TMS','name' => '运输管理TMS'],['title' => '设备管理EAM','name' => '设备管理EAM'],['title' => '质量管理QMS','name' => '质量管理QMS'],['title' => '人力资源','name' => '人力资源'],['title' => '会员零售','name' => '会员零售'],['title' => '审批工作流','name' => '审批工作流'],['title' => '消息通知','name' => '消息通知'],['title' => '项目管理','name' => '项目管理'],['title' => '自定义报表','name' => '自定义报表'],['title' => 'BI看板','name' => 'BI看板'],['title' => '文档管理DMS','name' => '文档管理DMS'],['title' => '打印模板','name' => '打印模板'],['title' => '平台管理','name' => '平台管理'],['title' => '开放接口','name' => '开放接口'],['title' => '客户端公开','name' => '客户端公开'],['title' => '客户端认证','name' => '客户端认证'],['title' => '系统管理','name' => '系统管理']],
        'apps' => [
            [
                'title' => 'Api接口',
                // 单字符串路径只扫 app\controller（24 业务域树）；客户端认证（app\api\v1）与
                // 系统管理（app\admin\controller）控制器在其外导致两组文档恒空 → 扩为多路径数组
                'path' => ['app\controller', 'app\admin\controller', 'app\api\v1\controller'],
                'key' => 'erik.xyz',
                'groups' => [['title' => '商品基础数据','name' => '商品基础数据'],['title' => '采购管理','name' => '采购管理'],['title' => '销售管理','name' => '销售管理'],['title' => '库存管理','name' => '库存管理'],['title' => '财务管理','name' => '财务管理'],['title' => 'CRM','name' => 'CRM'],['title' => '生产制造','name' => '生产制造'],['title' => '订单管理OMS','name' => '订单管理OMS'],['title' => '仓储管理WMS','name' => '仓储管理WMS'],['title' => '运输管理TMS','name' => '运输管理TMS'],['title' => '设备管理EAM','name' => '设备管理EAM'],['title' => '质量管理QMS','name' => '质量管理QMS'],['title' => '人力资源','name' => '人力资源'],['title' => '会员零售','name' => '会员零售'],['title' => '审批工作流','name' => '审批工作流'],['title' => '消息通知','name' => '消息通知'],['title' => '项目管理','name' => '项目管理'],['title' => '自定义报表','name' => '自定义报表'],['title' => 'BI看板','name' => 'BI看板'],['title' => '文档管理DMS','name' => '文档管理DMS'],['title' => '打印模板','name' => '打印模板'],['title' => '平台管理','name' => '平台管理'],['title' => '开放接口','name' => '开放接口'],['title' => '客户端公开','name' => '客户端公开'],['title' => '客户端认证','name' => '客户端认证'],['title' => '系统管理','name' => '系统管理']],
            ],
        ],
        'definitions' => "app\common\controller\Definitions",
        'auto_url' => ['letter_rule' => 'lcfirst', 'prefix' => ''],
        'auto_register_routes' => false,
        'cache' => ['enable' => false],
        // 口令/密钥取自环境变量（.env / .env.docker，scripts/gen-env-keys.sh 生成强随机值）；
        // 留空或仍是 CHANGE_ME_* 占位值时仅文档站拒绝访问（vendor Auth 抛「缺少 secret_key」），
        // 不影响应用启动
        'auth' => ['enable' => true, 'password' => $credential('APIDOC_PASSWORD'), 'secret_key' => $credential('APIDOC_SECRET_KEY'), 'expire' => 86400],
        'ignored_methods' => [],
         'params' => [
            // （选配）全局的请求Header
            'header' => [
                // name=字段名，type=字段类型，require=是否必须，default=默认值，desc=字段描述
                ['name' => 'Authorization','type' => 'string','require' => true,'desc' => '身份令牌Token'],
            ],
            // （选配）全局的请求Query
            'query' => [
                // 同上 header
            ],
            // （选配）全局的请求Body
            'body' => [
                // 同上 header
            ],
        ],
           // 全局响应体
        'responses' => [
            // 成功响应体
            'success' => [
                ['name' => 'code','desc' => '业务代码','type' => 'int','require' => 1],
                ['name' => 'message','desc' => '业务信息','type' => 'string','require' => 1],
                //参数同上 headers；main=true来指定接口Returned参数挂载节点
                ['name' => 'data','desc' => '业务数据','main' => true,'type' => 'object','require' => 1],
            ],
            // 异常响应体
            'error' => [
                ['name' => 'code','desc' => '业务代码','type' => 'int','require' => 1,'md' => '/docs/HttpError.md'],
                ['name' => 'message','desc' => '业务信息','type' => 'string','require' => 1],
            ],
        ],
        // （选配）全局响应状态码
        'responses_status' => [
            [
                'name' => '200',
                'desc' => '请求成功',
            ],
            [
                'name' => '401',
                'desc' => '登录令牌无效',
                'contentType' => '',
            ],
        ],
        'database' => [],
        'docs' => [],
        'generator' => [],
    ],
];
