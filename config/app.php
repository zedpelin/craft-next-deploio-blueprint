<?php
/**
 * Yii Application Config
 *
 * Edit this file at your own risk!
 *
 * The array returned by this file will get merged with
 * vendor/craftcms/cms/src/config/app.php and app.[web|console].php, when
 * Craft's bootstrap script is defining the configuration for the entire
 * application.
 *
 * You can define custom modules and system components, and even override the
 * built-in system components.
 *
 * If you want to modify the application config for *only* web requests or
 * *only* console requests, create an app.web.php or app.console.php file in
 * your config/ folder, alongside this one.
 *
 * Read more about application configuration:
 * @link https://craftcms.com/docs/5.x/reference/config/app.html
 */

use craft\helpers\App;

// When NINE_KVS_CACHE_FQDN is present (Deploio production), use Redis for cache.
// Without it (local dev), Craft falls back to file cache — no action needed.
$components = [];

if ($redisHost = App::env('NINE_KVS_CACHE_FQDN')) {
    // yii2-redis with useSSL=true connects via tcp:// then calls
    // stream_socket_enable_crypto() — but SSL context options (cafile) are
    // associated with the tcp:// socket and don't carry over to the upgrade.
    // Using scheme='tls' makes stream_socket_client() do a full TLS handshake
    // from the start so the context (including Nine's CA cert) is applied
    // correctly. useSSL must be false to avoid a redundant second TLS call.
    $sslContext = [];
    if ($caCert = App::env('NINE_KVS_CACHE_CA_CERT')) {
        $caFile = sys_get_temp_dir() . '/nine-kvs-ca.pem';
        file_put_contents($caFile, $caCert);
        $sslContext['cafile'] = $caFile;
    }

    $components['redis'] = [
        'class'          => \yii\redis\Connection::class,
        'scheme'         => 'tls',
        'hostname'       => $redisHost,
        'port'           => (int)(App::env('NINE_KVS_CACHE_PORT') ?: 6379),
        'password'       => App::env('NINE_KVS_CACHE_PASSWORD'),
        'useSSL'         => false,
        'contextOptions' => ['ssl' => $sslContext],
    ];
    // Cache uses the 'redis' component defined above.
    $components['cache'] = [
        'class'     => \yii\redis\Cache::class,
        'keyPrefix' => App::env('CRAFT_APP_ID') ?: 'craft',
    ];
}

return [
    'id'         => App::env('CRAFT_APP_ID') ?: 'CraftCMS',
    'components' => $components,
];
