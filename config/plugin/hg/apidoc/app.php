<?php

return [
    'enable' => true,
    'apidoc' => [
        // （选配）文档标题，显示在左上角与首页
        'title' => '开放ERP系统 API 文档',
        // （选配）文档描述，显示在首页
        'desc' => '',
        // （必须）设置文档的应用/版本
        'apps' => [
            [
                'title' => '管理端接口 (Admin)',
                'path' => 'app\controller',
                'key' => 'admin',
                'group' => [
                    ['title' => '仪表盘', 'path' => 'app\admin\controller\DashboardController'],
                    ['title' => '用户管理', 'path' => 'app\admin\controller\UserController'],
                    ['title' => '角色管理', 'path' => 'app\admin\controller\RoleController'],
                    ['title' => '权限管理', 'path' => 'app\admin\controller\PermissionController'],
                    ['title' => '系统配置', 'path' => 'app\admin\controller\ConfigController'],
                    ['title' => '操作日志', 'path' => 'app\admin\controller\LogController'],
                    ['title' => '文件管理', 'path' => 'app\admin\controller\ExportController'],
                    ['title' => '个人中心', 'path' => 'app\admin\controller\ProfileController'],
                    ['title' => '导入管理', 'path' => 'app\admin\controller\ImportController'],
                    ['title' => '上传管理', 'path' => 'app\admin\controller\UploadController'],
                    ['title' => 'API文档', 'path' => 'app\admin\controller\DocsController'],
                    ['title' => '健康检查', 'path' => 'app\admin\controller\HealthController'],
                    ['title' => '监控指标', 'path' => 'app\admin\controller\MetricsController'],
                    ['title' => '商品管理', 'path' => 'app\controller\product'],
                    ['title' => '采购管理', 'path' => 'app\controller\purchase'],
                    ['title' => '销售管理', 'path' => 'app\controller\sales'],
                    ['title' => '库存管理', 'path' => 'app\controller\inventory'],
                    ['title' => '财务管理', 'path' => 'app\controller\finance'],
                    ['title' => 'CRM', 'path' => 'app\controller\crm'],
                    ['title' => '审批工作流', 'path' => 'app\controller\workflow'],
                    ['title' => '通知系统', 'path' => 'app\controller\notification'],
                    ['title' => '项目管理', 'path' => 'app\controller\project'],
                    ['title' => '人力资源', 'path' => 'app\controller\hr'],
                    ['title' => '生产制造', 'path' => 'app\controller\manufacturing'],
                    ['title' => '自定义报表', 'path' => 'app\controller\report'],
                ],
            ],
            [
                'title' => '客户端接口 (Service API)',
                'path' => 'app\api\v1\controller',
                'key' => 'service',
                'group' => [
                    ['title' => '认证', 'path' => 'app\api\v1\controller\AuthController'],
                    ['title' => '验证码', 'path' => 'app\api\v1\controller\CaptchaController'],
                    ['title' => '商品', 'path' => 'app\api\v1\controller\ProductController'],
                ],
            ],
        ],
        // （必须）指定通用注释定义的文件地址
        'definitions' => 'app\common\controller\Definitions',
        // （必须）自动生成url规则，当接口不添加@Apidoc\Url ("xxx")注解时，使用以下规则自动生成
        'auto_url' => [
            // 字母规则，lcfirst=首字母小写；ucfirst=首字母大写；
            'letter_rule' => 'lcfirst',
            // url前缀
            'prefix' => '',
        ],
        // （选配）是否自动注册路由
        'auto_register_routes' => false,
        // （必须）缓存配置
        'cache' => [
            // 是否开启缓存
            'enable' => false,
        ],
        // （必须）权限认证配置
        'auth' => [
            // 是否启用密码验证
            'enable' => false,
            // 全局访问密码
            'password' => '123456',
            // 密码加密盐
            'secret_key' => 'apidoc#hg_code',
            // 授权访问后的有效期
            'expire' => 24 * 60 * 60,
        ],
        // 全局参数
        'params' => [
            // （选配）全局的请求Header
            'header' => [
                // name=字段名，type=字段类型，require=是否必须，default=默认值，desc=字段描述
                ['name' => 'Authorization', 'type' => 'string', 'require' => false, 'desc' => 'JWT身份令牌 (Bearer xxx)'],
                ['name' => 'API-Version', 'type' => 'string', 'require' => false, 'desc' => 'API版本号 (v1)'],
                ['name' => 'Accept-Language', 'type' => 'string', 'require' => false, 'desc' => '语言 (zh-CN/en)'],
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
                ['name' => 'code','desc' => '错误代码','type' => 'int','require' => 1],
                ['name' => 'message','desc' => '错误信息','type' => 'string','require' => 1],
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
        //（选配）默认作者
        'default_author' => 'erik',
        //（选配）默认请求类型
        'default_method' => 'GET',
        //（选配）Apidoc允许跨域访问
        'allowCrossDomain' => false,
        /**
         * （选配）解析时忽略带@注解的关键词，当注解中存在带@字符并且非Apidoc注解，如 @key test，此时Apidoc页面报类似以下错误时:
         * [Semantical Error] The annotation "@key" in method xxx() was never imported. Did you maybe forget to add a "use" statement for this annotation?
         */
        'ignored_annitation' => [],

        // （选配）解析时忽略的方法
        'ignored_methods' => [],

        // （选配）数据库配置
        'database' => [],
        // （选配）Markdown文档
        'docs' => [],
        // （选配）接口生成器配置 注意：是一个二维数组
        'generator' => [],
    ],
];
