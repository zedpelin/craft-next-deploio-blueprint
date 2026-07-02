<?php
/**
 * Filesystem config for asset storage.
 *
 * On Deploio, assets must live in Nine Object Storage (S3-compatible) because
 * the local filesystem is ephemeral. When BUCKET_ENDPOINT is set, this file
 * tells Craft to use that S3 bucket for any filesystem with the handle "nine-s3".
 *
 * Setup steps:
 *  1. Create an Object Storage bucket in the Nine console.
 *  2. Add BUCKET_ENDPOINT, BUCKET_KEY, BUCKET_SECRET, BUCKET_NAME as env vars
 *     in your Deploio app.
 *  3. In the Craft CP → Settings → Filesystems, create a new filesystem with
 *     handle "nine-s3" and type "Amazon S3". The credentials below are injected
 *     at runtime — you don't need to enter them in the CP.
 *  4. Create an asset volume that uses the "nine-s3" filesystem.
 */

use craft\awss3\Fs;
use craft\helpers\App;

if (!App::env('BUCKET_ENDPOINT')) {
    return [];
}

return [
    'nine-s3' => [
        'type'        => Fs::class,
        'keyId'       => App::env('BUCKET_KEY'),
        'secret'      => App::env('BUCKET_SECRET'),
        'region'      => App::env('BUCKET_REGION') ?: 'nine-cz42',
        'bucket'      => App::env('BUCKET_NAME'),
        'endpoint'    => App::env('BUCKET_ENDPOINT'),
        'subfolder'   => 'uploads',
        'addCorsHdrs' => true,
        'makePublic'  => true,
    ],
];
