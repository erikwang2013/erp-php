<?php

/**
 * Copyright (c) erik <erik@erik.xyz> (https://erik.xyz). All Rights Reserved.
 */

// 强校验：使用 elasticsearch 驱动时，ES 口令必须显式配置
// （缺失/为空/弱占位值 change-me/CHANGE_ME/xxx 一律拒绝启动，fail-fast）
if ((getenv('SCOUT_DRIVER') ?: 'elasticsearch') === 'elasticsearch') {
    env_secret('ES_PASSWORD', 'Elasticsearch');
}

return [
    'enable' => true,
    /*
    |--------------------------------------------------------------------------
    | Default Search Engine
    |--------------------------------------------------------------------------
    |
    | This option controls the default search connection that gets used while
    | using Laravel Scout. This connection is used when syncing all models
    | to the search service. You should adjust this based on your needs.
    |
    | Supported: "algolia", "meilisearch", "typesense","elasticsearch",
    |            "database", "collection", "null","opensearch"
    |
    */

    // getenv 仅单参数；勿写 getenv('X','default')，第二个参数是 local_only(bool)，会导致 driver=false 进而误匹配 createDriver()
    'driver' => getenv('SCOUT_DRIVER') ?: 'elasticsearch',

    /*
    |--------------------------------------------------------------------------
    | Index Prefix
    |--------------------------------------------------------------------------
    |
    | Here you may specify a prefix that will be applied to all search index
    | names used by Scout. This prefix may be useful if you have multiple
    | "tenants" or applications sharing the same search infrastructure.
    |
    */

    'prefix' => getenv('SCOUT_PREFIX', ''),

    /*
    |--------------------------------------------------------------------------
    | Queue Data Syncing
    |--------------------------------------------------------------------------
    |
    | This option allows you to control if the operations that sync your data
    | with your search engines are queued. When this is set to "true" then
    | all automatic data syncing will get queued for better performance.
    |
    */

    'queue' => getenv('SCOUT_QUEUE', false),

    /*
    |--------------------------------------------------------------------------
    | Database Transactions
    |--------------------------------------------------------------------------
    |
    | This configuration option determines if your data will only be synced
    | with your search indexes after every open database transaction has
    | been committed, thus preventing any discarded data from syncing.
    |
    */

    'after_commit' => false,

    /*
    |--------------------------------------------------------------------------
    | Chunk Sizes
    |--------------------------------------------------------------------------
    |
    | These options allow you to control the maximum chunk size when you are
    | mass importing data into the search engine. This allows you to fine
    | tune each of these chunk sizes based on the power of the servers.
    |
    */

    'chunk' => [
        'searchable' => 500,
        'unsearchable' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Soft Deletes
    |--------------------------------------------------------------------------
    |
    | This option allows to control whether to keep soft deleted records in
    | the search indexes. Maintaining soft deleted records can be useful
    | if your application still needs to search for the records later.
    |
    */

    'soft_delete' => false,

    /*
    |--------------------------------------------------------------------------
    | Identify User
    |--------------------------------------------------------------------------
    |
    | This option allows you to control whether to notify the search engine
    | of the user performing the search. This is sometimes useful if the
    | engine supports any analytics based on this application's users.
    |
    | Supported engines: "algolia"
    |
    */

    'identify' => getenv('SCOUT_IDENTIFY', false),

    /*
    |--------------------------------------------------------------------------
    | Algolia Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Algolia settings. Algolia is a cloud hosted
    | search engine which works great with Scout out of the box. Just plug
    | in your application ID and admin API key to get started searching.
    |
    */

    'algolia' => [
        'id' => getenv('ALGOLIA_APP_ID', ''),
        'secret' => getenv('ALGOLIA_SECRET', ''),
        'index-settings' => [
            // 'users' => [
            //     'searchableAttributes' => ['id', 'name', 'email'],
            //     'attributesForFaceting'=> ['filterOnly(email)'],
            // ],
        ],
    ],

    'xunsearch' => [
        // XunSearch 配置文件路径
        'config_path' => getenv('XUNSEARCH_CONFIG_PATH', base_path('config/xunsearch')),

        // 默认索引配置
        'default_index' => getenv('XUNSEARCH_DEFAULT_INDEX', 'default'),

        // 字符集
        'charset' => getenv('XUNSEARCH_CHARSET', 'utf-8'),

        // 搜索选项
        'search' => [
            'fuzzy' => getenv('XUNSEARCH_FUZZY', true),
            'auto_synonym' => getenv('XUNSEARCH_AUTO_SYNONYM', true),
            'auto_flush' => getenv('XUNSEARCH_AUTO_FLUSH', true),
            'batch_size' => getenv('XUNSEARCH_BATCH_SIZE', 100),
        ],

        // 缓存配置
        'cache' => [
            'enabled' => getenv('XUNSEARCH_CACHE_ENABLED', true),
            'ttl' => getenv('XUNSEARCH_CACHE_TTL', 300),
            'store' => getenv('XUNSEARCH_CACHE_STORE', 'file'),
        ],

        // 索引配置模板
        'index_templates' => [
            'default' => [
                'project.name' => 'default',
                'project.default_charset' => 'utf-8',
                'server.index' => '8383',
                'server.search' => '8384',
                // 字段定义
                'field.id' => [
                    'type' => 'id',
                ],
                'field.title' => [
                    'type' => 'title',
                ],
                'field.content' => [
                    'type' => 'body',
                ],
                'field.created_at' => [
                    'type' => 'numeric',
                ],
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Meilisearch Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Meilisearch settings. Meilisearch is an open
    | source search engine with minimal configuration. Below, you can state
    | the host and key information for your own Meilisearch installation.
    |
    | See: https://www.meilisearch.com/docs/learn/configuration/instance_options#all-instance-options
    |
    */

    'meilisearch' => [
        'host' => getenv('MEILISEARCH_HOST', 'http://localhost:7700'),
        'key' => getenv('MEILISEARCH_KEY'),
        'index-settings' => [
            // 'users' => [
            //     'filterableAttributes'=> ['id', 'name', 'email'],
            // ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Typesense Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your Typesense settings. Typesense is an open
    | source search engine using minimal configuration. Below, you will
    | state the host, key, and schema configuration for the instance.
    |
    */

    'typesense' => [
        'client-settings' => [
            'api_key' => getenv('TYPESENSE_API_KEY', 'xyz'),
            'nodes' => [
                [
                    'host' => getenv('TYPESENSE_HOST', 'localhost'),
                    'port' => getenv('TYPESENSE_PORT', '8108'),
                    'path' => getenv('TYPESENSE_PATH', ''),
                    'protocol' => getenv('TYPESENSE_PROTOCOL', 'http'),
                ],
            ],
            'nearest_node' => [
                'host' => getenv('TYPESENSE_HOST', 'localhost'),
                'port' => getenv('TYPESENSE_PORT', '8108'),
                'path' => getenv('TYPESENSE_PATH', ''),
                'protocol' => getenv('TYPESENSE_PROTOCOL', 'http'),
            ],
            'connection_timeout_seconds' => getenv('TYPESENSE_CONNECTION_TIMEOUT_SECONDS', 2),
            'healthcheck_interval_seconds' => getenv('TYPESENSE_HEALTHCHECK_INTERVAL_SECONDS', 30),
            'num_retries' => getenv('TYPESENSE_NUM_RETRIES', 3),
            'retry_interval_seconds' => getenv('TYPESENSE_RETRY_INTERVAL_SECONDS', 1),
        ],
        // 'max_total_results' => getenv('TYPESENSE_MAX_TOTAL_RESULTS', 1000),
        'model-settings' => [
            // User::class => [
            //     'collection-schema' => [
            //         'fields' => [
            //             [
            //                 'name' => 'id',
            //                 'type' => 'string',
            //             ],
            //             [
            //                 'name' => 'name',
            //                 'type' => 'string',
            //             ],
            //             [
            //                 'name' => 'created_at',
            //                 'type' => 'int64',
            //             ],
            //         ],
            //         'default_sorting_field' => 'created_at',
            //     ],
            //     'search-parameters' => [
            //         'query_by' => 'name'
            //     ],
            // ],
        ],
        'import_action' => getenv('TYPESENSE_IMPORT_ACTION', 'upsert'),
    ],

    'elasticsearch' => [
        // 主机列表：逗号分隔，与 .env 的 SCOUT_HOSTS 保持一致
        'hosts' => array_values(array_filter(array_map('trim', explode(',', (string)(getenv('SCOUT_HOSTS') ?: 'http://127.0.0.1:9200'))))),
        // 认证信息：从环境变量读取；未配置时为匿名访问（本地开发）
        'auth' => [
            'user' => getenv('ES_USERNAME') ?: null,
            'pass' => getenv('ES_PASSWORD') ?: null,
            'api_id' => getenv('ES_API_ID') ?: null,
            'api_key' => getenv('ES_API_KEY') ?: null,
            'cloud_id' => getenv('ES_CLOUD_ID') ?: null,
        ],
        // index为设定的index名称 如果你的index名称为goods 则下面的index应写成goods
        //        'index' => [
        //            'setting' => [],
        //            'aliases' => [],
        //            'mappings' => [],
        //        ]
    ],

    'opensearch' => [
        'host' => getenv('OPENSEARCH_HTTP_HOST', 'https://127.0.0.1:6205'),
        'username' => getenv('OPENSEARCH_USERNAME', 'admin'),
        'password' => getenv('OPENSEARCH_PASSWORD', 'admin'),
        'prefix' => getenv('OPENSEARCH_INDEX_PREFIX'),
        'ssl_verification' => (bool)getenv('OPENSEARCH_SSL_VERIFICATION', false),
        'ssl_cert' => getenv('OPENSEARCH_SSL_CERT', ''),
        'ssl_key' => getenv('OPENSEARCH_SSL_KEY', ''),
        'retries' => getenv('OPENSEARCH_RETRIES', 2),
        'connection_timeout' => getenv('OPENSEARCH_CONNECTION_TIMEOUT', 10),
        'timeout' => getenv('OPENSEARCH_TIMEOUT', 30),
        'indices' => [
            'products' => [
                'settings' => [
                    'index' => [
                        'number_of_shards' => 1,
                        'number_of_replicas' => 1,
                        'knn' => true,
                        'knn.algo_param.ef_search' => 100,
                    ],
                ],
                'mappings' => [
                    'properties' => [
                        'vector' => [
                            'type' => 'knn_vector',
                            'dimension' => 1536,
                        ],
                        'location' => [
                            'type' => 'geo_point',
                        ],
                    ],
                ],
                'aliases' => ['products_alias'],
            ],
        ],
    ],

];
