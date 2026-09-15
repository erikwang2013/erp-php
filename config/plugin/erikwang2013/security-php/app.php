<?php

/**
 * Security Plugin Configuration
 *
 * Copyright (c) 2026 erik <erik@erik.xyz> — https://erik.xyz
 *
 * This file controls all detection behavior.
 * Publish to your project config directory and customize as needed.
 */

return [
    /*
     * 总开关
     * 设为 false 可以临时关闭所有安全检测功能
     * 建议在调试或特定内部环境时设为 false
     */
    'enabled' => true,

    /*
     * 检测器配置
     * 每个检测器可以独立控制启用状态和处理模式
     *
     * enabled: true=启用检测, false=跳过
     * mode:
     *   'block'  — 检测到攻击时拦截请求，返回 403
     *   'log'    — 仅记录日志，不拦截，适合监控模式
     */
    'detectors' => [
        // XSS 跨站脚本攻击检测
        // 检测 <script>、onerror=、javascript: 等注入模式
        'xss' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // SQL 注入检测
        // 检测 union select、sleep(、-- 注释、or 1=1 等注入模式
        'sql_injection' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // 命令注入检测
        // 检测反引号、$()、管道符、/dev/tcp 等命令执行模式
        'command_injection' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // 路径遍历检测
        // 检测 ../、..\\、/etc/passwd、php://filter 等文件包含模式
        'path_traversal' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // 恶意文件上传检测
        // 检测文件扩展名是否在允许的白名单内，以及 PHP 标签头
        'upload' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // SSRF 服务端请求伪造检测
        // 检测内网 IP（127.x、10.x、172.16-31.x、192.168.x）、cloud metadata、危险协议
        'ssrf' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // XXE XML 外部实体注入检测
        // 检测 <!ENTITY、SYSTEM/PUBLIC 标识、DOCTYPE 声明等
        'xxe' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // HTTP 响应头注入检测
        // 检测 CRLF 换行符注入（%0d%0a、\r\n）、Set-Cookie、Location 等响应头注入
        // 注意：默认 log 模式，因为 \r\n\r\n 会匹配多段落文本（如表单 textarea）
        'header_injection' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // 反序列化攻击检测
        // 检测 PHP 序列化对象格式（O:数字:、C:数字:）、魔术方法等
        'deserialization' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // LDAP 注入检测
        // 检测 LDAP 过滤语法（&、|、!、*）、属性枚举等
        'ldap_injection' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // 邮件头注入检测
        // 检测 Bcc、Cc、From、To 等邮件头注入，防止邮件被劫持转发
        'mail_header' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // SSTI 服务端模板注入检测
        // 检测 Jinja2/Twig（{{}}、{%%}）、FreeMarker（${}）、ERB（<%%>）等模板语法
        // 注意：默认 log 模式，因为 {{ }} 会匹配 Vue/Angular/Handlebars 前端模板
        'ssti' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // NoSQL 注入检测
        // 检测 MongoDB $ne/$gt/$regex/$where 等操作符注入、认证绕过
        // 注意：默认 log 模式，因为 $ne/$gt 会匹配 Shell 变量、LaTeX、价格字符串
        'nosql_injection' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // Open Redirect 开放重定向检测
        // 检测 //evil.com 协议相对URL、javascript: 伪协议、外部域名重定向
        'open_redirect' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // JWT 攻击检测
        // 检测 alg:none 签名绕过、kid 参数注入、空签名等 JWT 安全问题
        'jwt_attack' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // Host 头攻击检测
        // 检测 Host 头注入、X-Forwarded-Host 投毒、X-Original-URL 等
        'host_header' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // HTTP Request Smuggling 检测
        // 检测 Transfer-Encoding/Content-Length 不一致、TE.CL/CL.TE 攻击
        'request_smuggling' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // GraphQL 注入检测
        // 检测 __schema/__type 内省查询、深度嵌套、批量查询攻击
        // 注意：默认 log 模式，因为深度嵌套模式可能匹配合法前端 JSON/JS 大括号结构
        'graphql_injection' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // XPATH 注入检测
        // 检测 or 1=1 布尔绕过、| 联合操作符、count/string/substring 函数注入
        'xpath_injection' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // JNDI / Log4Shell 注入检测
        // 检测 ${jndi:ldap://、${lower:j、${env:、${::-j} 等 Log4j 漏洞利用
        'jndi_injection' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // SSI 服务端包含注入检测
        // 检测 <!--#exec cmd=、<!--#include file=、<!--#echo var= 等 SSI 指令
        'ssi_injection' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // CSV 公式注入检测
        // 检测 =cmd|、=powershell、HYPERLINK() 等 Excel 公式攻击
        'csv_injection' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // 敏感数据泄露检测
        // 检测信用卡号、AWS Key、私钥头、数据库连接串、API Token、JWT Secret
        'data_leak' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // Prototype Pollution 检测
        // 检测 __proto__、constructor.prototype、__defineSetter__ 等 JS 原型污染
        'prototype_pollution' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // WebSocket 劫持检测
        // 检测 Upgrade:websocket 头注入、null Origin 绕过、WS URL 注入
        // 注意：默认 log 模式，因为 Upgrade 头检测可能匹配合法 WebSocket 建连请求
        'websocket' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // CORS 绕过检测
        // 检测 Origin 头注入、Access-Control-* 头注入、preflight 请求投毒
        'cors' => [
            'enabled' => true,
            'mode' => 'block',
        ],

        // DNS Rebinding 检测
        // 检测 Host 头内网 IP（127/10/172/192/0.0.0.0）、localhost、无 TLD 短主机名
        //
        // 降为 log：这个检测器（Detector/DnsRebindingDetector.php）只对 Host 头做字符串匹配，
        // 全程不做 DNS 解析（rebindingSeverity() 里只有 trim/端口剥离/正则），因此**测不到**
        // 真正的 DNS rebinding —— 真实攻击的浏览器送来的是攻击者自己的域名（Host: evil.com），
        // 不是 IP，九条规则一条都不命中。它命中的恰好相反：客户端主动用 IP 访问。
        // block 模式下的实际效果是裸 IP 与内网主机名访问整站 403（内网部署的 ERP 首当其冲，
        // 监控、CI 冒烟、运维直连一并失效），拿不到任何真实防护，只换到可用性损失。
        // 保留 enabled，攻击特征仍进日志，便于日后核对。
        'dns_rebinding' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // HTTP 方法校验
        // 检测请求方法是否在允许列表内，不在则返回 405 Method Not Allowed
        'http_method' => [
            'enabled' => true,
            'mode' => 'block',
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'HEAD', 'OPTIONS', 'PATCH'],
        ],

        // 请求体大小限制
        // 检测请求体是否超过最大允许大小，超过则返回 413 Payload Too Large
        // max_size 单位为字节，默认 10MB
        'body_size' => [
            'enabled' => true,
            'mode' => 'block',
            'max_size' => 10485760, // 10 MB
        ],

        // Content-Type 校验
        // 检测 Content-Type 是否在允许列表内，不在则返回 415 Unsupported Media Type
        'content_type' => [
            'enabled' => true,
            'mode' => 'block',
            'allowed_types' => [
                'application/x-www-form-urlencoded',
                'multipart/form-data',
                'application/json',
                'text/plain',
                'application/xml',
                'text/xml',
            ],
        ],

        // CSRF Origin 检查
        // 检测 Origin 头是否与 Host 匹配，不匹配则可能是 CSRF 攻击
        // allowed_origins 可选：额外允许的跨域来源
        'csrf_origin' => [
            'enabled' => true,
            'mode' => 'block',
            'allowed_origins' => [],
        ],

        // 会话劫持检测（Cookie 与 Token 登录通用）
        // 首次见到某会话时记录指纹基线（User-Agent + IP 网段），之后不一致即告警。
        // 会话标识优先取 Cookie，其次取 Authorization: Bearer / X-Token 等头，
        // 因此 API / 小程序 / App 的 token 登录同样覆盖。
        // 注意：默认 log 模式，因为移动网络切基站、浏览器升级都会改变指纹，
        // 需先观察误报率再切 block。命中时返回 401（登录态不可信，应重新认证）
        'session_hijack' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // 异地登录检测
        // 需应用在登录 / token 签发成功后调用 SecurityGuard::recordLogin($userId)。
        // 不调用则本项不产生任何结果（不会误报）。
        // 注意：默认 log 模式；首个登录地即基线，局限详见 README
        'unusual_login' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // 数据篡改检测
        // 校验受保护字段的 HMAC 签名（由 SecurityGuard::signFields() 签发）。
        // 必须同时配置 signing_key 且 identity.tamper.protected_fields 非空才生效，
        // 否则完全静默 —— 已有应用升级后不会因此失败
        'data_tamper' => [
            'enabled' => true,
            'mode' => 'log',
        ],

        // 登录暴力破解锁定
        // 需应用在登录失败分支调用 SecurityGuard::recordFailedLogin($userId, $ip)，
        // 在认证前用 SecurityGuard::isLockedOut($userId, $ip) 提前拦截。
        // 不调用则本项不产生任何结果（不会误报）。
        // 注意：默认 log 模式；以账号为锁定单位，攻击者可故意锁死受害者账号，
        // 故 lock_seconds 默认较短，详见 README
        'login_lockout' => [
            'enabled' => true,
            'mode' => 'log',
        ],
    ],

    /*
     * IP 攻击升级黑名单
     * 同一 IP 在 window_seconds 秒内触发 max_attempts 次攻击检测后，
     * 自动封禁 ban_duration_seconds 秒。
     * 数据持久化到 storage_path（默认系统临时目录）。
     */
    'ip_blacklist' => [
        'enabled' => true,
        'max_attempts' => 5,
        'window_seconds' => 60,
        'ban_duration_seconds' => 900, // 15 分钟
    ],

    /*
     * 身份维度检测（会话劫持 / 异地登录 / 数据篡改）
     * 这三项需要跨请求状态，基线数据存入下面的 storage。
     * 存储 key 一律是 sha256 摘要，不落原始 session id / token 明文。
     */
    'identity' => [
        'enabled' => true,

        // 会话劫持：会话标识 -> 指纹基线
        'session' => [
            // 本应用不用 Cookie：鉴权一律走 Authorization: Bearer（无 Session 中间件），
            // 留空表示只查下面的头，避免插件去读一个永远不存在的会话 Cookie
            'cookie' => '',
            // 无 Cookie 时按顺序查这些头（大小写不敏感，自动剥离 Bearer 前缀）
            'headers' => ['authorization', 'x-token', 'x-auth-token'],
            // 绑定因子：ua = User-Agent，ip = IP 网段
            'bind' => ['ua', 'ip'],
            // IP 比较的网段位数，避免移动网络换基站即告警（IPv6 固定 /64）
            'ip_bits' => 24,
            // 超过该时长未活动的会话视为新会话，重新建立基线（秒）
            'ttl' => 7200,
            // 惰性回收：每 N 次请求触发一次过期清理，0 = 关闭
            // StorageInterface 没有 TTL，改用 Redis 后端时可以把这两项关掉
            'gc_probability' => 100,
            'gc_batch' => 20,
        ],

        // 异地登录：user_id -> 常用地点
        'login' => [
            // 地点在此时间内未再出现即遗忘（秒），也用于限制存储增长
            'ttl' => 86400,
            // 每个账号最多记住几个地点，超出按最久未用淘汰
            'max_points' => 10,
            // 无 $location 时回落到 IP 网段，比较位数
            'ip_bits' => 24,

            // 暴力破解锁定：滑动窗口内的失败次数
            'lockout' => [
                // 窗口内累计达到该次数即锁定（秒）
                'max_failures' => 5,
                // 失败计数的有效期（秒）
                'window_seconds' => 900,
                // 锁定时长（秒）。偏短：以账号为单位锁定，攻击者可用它锁死受害者
                'lock_seconds' => 900,
                // true 时按 user_id + IP 分别计数，把锁定收敛到攻击者来源，
                // 但同一账号换 IP 即可重置计数
                'include_ip' => false,
            ],
        ],

        // 数据篡改：受保护字段的 HMAC 签名
        'tamper' => [
            // 客户端回传签名的字段名
            'token_field' => '_security_sig',
            // 要签名的字段，扁平化点路径（如 'order.price'、'user.id'）
            // 留空则本项完全不生效 —— 必须显式声明保护哪些字段
            'protected_fields' => [],
            // 签名有效期（秒）
            'ttl' => 1800,
        ],
    ],

    /*
     * 编码/混淆归一化
     * 对携带编码信号的请求值额外扫描其解码结果，避免纯正则检测被绕过：
     *   URL 编码（%3Cscript%3E）、双重编码（%2527）、全角字符（Ｓｅｌｅｃｔ）、
     *   HTML 实体（&#60;script&#62;）。
     * 原值仍按原样扫描，两边都可能命中；解码命中在日志 detail 里标注 [decoded:xxx]。
     * 每种变体都先做廉价的信号预检（值里没有 % 就绝不调用 urldecode），
     * 所以无编码的请求不产生额外开销。
     * 注意：含 % 的合法文本（带 URL 的表单、搜索词）解码后可能新增命中，
     * 因此高误报检测器保持 log 模式即可，勿整组切 block。
     */
    'normalization' => [
        'enabled' => true,
        'urldecode' => true, // 值包含 % 时解码（含双重编码）
        'fullwidth' => true, // 全角 ASCII 转半角
        'entities' => true, // 值包含 &# 或 &amp; 时解 HTML 实体
    ],

    /*
     * 字段签名密钥（数据篡改检测用）
     * 留空则签名功能静默禁用，不影响任何既有行为。
     * 务必用环境变量注入，不要写进版本库：
     *   export SECURITY_SIGNING_KEY="$(php -r 'echo bin2hex(random_bytes(32));')"
     * 多机部署必须一致，否则各节点会互判签名无效。
     */
    'signing_key' => getenv('SECURITY_SIGNING_KEY') ?: '',

    /*
     * 存储配置
     * 控制系统持久化数据的存储后端
     *
     * type: 存储类型
     *   'file'  — 本地 JSON 文件（默认，零依赖）
     *   'redis' — Redis（分布式 / 高可用场景，需通过 redis_instance 传入 \Redis 实例）
     *   'cache' — 文件缓存（每个 key 独立文件，适合高并发读写）
     */
    'storage' => [
        // 必须用 redis：进程数 = cpu_count()*4（config/process.php:52），
        // file 后端是「单 JSON + flock」，多 worker 各写各的会互相覆盖，
        // 会话基线/登录地/失败计数全都不可信。
        //
        // redis_instance 不写在这里：support\Redis::connection() 返回的是
        // 协程连接池里的连接（webman/redis RedisManager.php:32-72，Context::onDestroy
        // 会把它归还复用），插件要长期持有，跨请求复用同一 socket 不安全。
        // 改由 app\middleware\SecurityFilter 在 init() 前注入一个本进程独占的 \Redis。
        'type' => 'redis',

        // File 存储配置（type=file 时生效）
        // 注意：多应用共享主机时，留空默认路径 sys_get_temp_dir() 会被多个应用共用，
        // 可能互相覆盖数据。建议显式配置独立路径，或改用 type=redis。
        'file' => [
            'path' => '', // 留空使用 sys_get_temp_dir() . '/security_storage.json'
        ],

        // Redis 存储配置（type=redis 时生效）
        // 连接参数（host/port/timeout/password）不由配置文件管理，
        // 请在外部创建 \Redis 实例后通过 redis_instance 传入。
        'redis' => [
            'prefix' => 'security:',
        ],

        // Cache 存储配置（type=cache 时生效）
        // 注意：多应用共享主机时，留空默认路径 sys_get_temp_dir() 会被多个应用共用，
        // 可能互相覆盖数据。建议显式配置独立路径，或改用 type=redis。
        'cache' => [
            'path' => '', // 留空使用 sys_get_temp_dir() . '/security_cache'
            'prefix' => 'security_',
        ],
    ],

    /*
     * 拦截响应配置
     * 当检测器的 mode 为 'block' 时生效
     */
    // HTTP 状态码，通常使用 403（禁止访问）或 406（不可接受）
    'block_status_code' => 403,

    // 返回给客户端的内容，{type} 会被替换为攻击类型标识
    'block_message' => 'Request blocked by security policy',

    /*
     * 安全响应头
     * 中间件在拦截响应与放行响应上都追加这些头。
     * 值为空字符串表示不发送 —— CSP/HSTS 依赖站点实际情况，留空即保持关闭。
     * 注意：Strict-Transport-Security 应只在 HTTPS 下发送，请自行判断后再填值。
     */
    'security_headers' => [
        'enabled' => true,
        'headers' => [
            // 禁止浏览器 MIME 类型嗅探（防止上传的 HTML 被当网页执行）
            'X-Content-Type-Options' => 'nosniff',
            // 防点击劫持：SAMEORIGIN=仅同源可嵌；DENY=完全禁止
            'X-Frame-Options' => 'SAMEORIGIN',
            // 控制跨站请求泄露多少来源信息
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // 例：geolocation=(), camera=(), microphone=()
            'Permissions-Policy' => '',
            // 例：default-src 'self'; script-src 'self'
            'Content-Security-Policy' => '',
            // 例：max-age=31536000; includeSubDomains
            'Strict-Transport-Security' => '',
        ],
    ],

    /*
     * 日志配置
     *
     * enabled: 是否记录攻击日志
     * path: 日志文件路径，留空则使用 sys_get_temp_dir() . '/security.log'
     *   注意：多应用共享主机时，留空默认路径会被多个应用共用同一日志文件，建议显式配置独立路径
     * max_size: 单个日志文件最大体积，单位 MB，超过后自动轮转。设为 0 禁用轮转
     * dedup_seconds: 去重窗口（秒）。同一请求内，相同 IP+类型+字段在此时间内不重复记录。设为 0 禁用
     *   注意：在 PHP-FPM 等短生命周期模式下，去重仅对单次请求有效，非跨请求去重
     */
    'log' => [
        'enabled' => true,
        // 与 app/middleware/SecurityFilter.php 原用的落盘位置一致；
        // 留空会落到 sys_get_temp_dir()，同机多应用会串写同一个文件
        'path' => runtime_path() . '/logs/security.log',
        'max_size' => 10,
        'dedup_seconds' => 5,
    ],

    /*
     * IP 白名单
     * 白名单内的 IP 地址不进行安全检测
     * 格式：支持单个 IP 和 CIDR 网段
     * 示例：
     *   '127.0.0.1',         — 单个 IP
     *   '10.0.0.0/8',        — CIDR 网段
     *   '192.168.1.0/24',    — /24 子网
     */
    'whitelist_ips' => [],

    /*
     * 信任的反向代理
     * 当客户端 IP（REMOTE_ADDR）命中此列表时，从 X-Forwarded-For 解析真实 IP，
     * 用于白名单/黑名单判断，避免误封代理 IP。
     * 示例：'127.0.0.1'、'10.0.0.0/8'。仅信任你自己的代理层，切勿放行公网 IP。
     *
     * 本应用保持为空 —— 客户端 IP 已由 app\middleware\SecurityFilter 解析好再传入。
     * 若在此填了代理网段，SecurityGuard::resolveClientIp() 会再解析一次，而它取的是
     * X-Forwarded-For 的**最左值**（SecurityGuard.php:305，注释称「standard XFF semantics」），
     * 那是可被客户端伪造的一跳，等于把已经修好的来源 IP 重新弄脏。
     */
    'trusted_proxies' => [],

    /*
     * 字段白名单
     * 这些字段名的值将跳过检测，不报告威胁
     * 框架自带的 token 字段、表单辅助字段等应当加入
     *
     * 例如 Laravel 的 _token（CSRF token）可能包含随机字符串，
     * 加入白名单可以避免误报
     */
    'whitelist_fields' => ['_token', '_method', 'csrf_token'],
];
