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

// Skip the GD auto-quality binary search loop — it takes 30+ seconds on uploads
// because Imagick (which short-circuits the loop) is not available in the Heroku
// PHP buildpack, and PHP-FPM's 30s request_terminate_timeout fires before it
// finishes. set_time_limit(0) does NOT override request_terminate_timeout.
$components['images'] = function () {
    return new class extends \craft\services\Images {
        public function cleanImage(string $filePath): void {}
    };
};

// craftcms/aws-s3 hardcodes ServerSideEncryption: AES256 on every PutObject.
// Nine Object Storage returns 501 UnsupportedEncryptionHeader for that header.
// Override the fs service to swap craft\awss3\Fs with NineFsAdapter, a subclass
// that omits the SSE option from createAdapter().
//
// This cannot be done via config/filesystems.php: once a filesystem is saved in
// project config (DB), Craft reads its type from there and ignores filesystems.php.
if (!class_exists('NineFsAdapter')) {
    class NineFsAdapter extends \craft\awss3\Fs
    {
        protected function createAdapter(): \League\Flysystem\FilesystemAdapter
        {
            // Replicate private _getCredentials() + _getConfigArray().
            // keyId/secret are empty so the SDK uses the env-var credential chain
            // (AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY).
            $keyId  = \Craft::parseEnv($this->keyId);
            $secret = \Craft::parseEnv($this->secret);
            $region = \Craft::parseEnv($this->region);

            $client = static::client(
                self::buildConfigArray($keyId, $secret, $region),
                ['keyId' => $keyId, 'secret' => $secret, 'region' => $region],
            );

            // Replicate private _subfolder()
            $subfolder = '';
            if ($this->subfolder !== '') {
                $parsed = rtrim((string) App::parseEnv($this->subfolder), '/');
                if ($parsed !== '') {
                    $subfolder = $parsed . '/';
                }
            }

            return new \League\Flysystem\AwsS3V3\AwsS3V3Adapter(
                $client,
                App::parseEnv($this->bucket),
                $subfolder,
                new \League\Flysystem\AwsS3V3\PortableVisibilityConverter($this->visibility()),
                null,
                [],    // No ServerSideEncryption — Nine Object Storage returns 501 for it
                false,
            );
        }
    }
}

$components['fs'] = function () {
    return new class extends \craft\services\Fs {
        public function createFilesystem(mixed $config): \craft\base\FsInterface
        {
            if (($config['type'] ?? null) === \craft\awss3\Fs::class) {
                $config['type'] = 'NineFsAdapter';
            }
            return parent::createFilesystem($config);
        }
    };
};

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
