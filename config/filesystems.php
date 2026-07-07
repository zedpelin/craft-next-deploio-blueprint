<?php
/**
 * Filesystem config for asset storage.
 *
 * On Deploio, assets must live in Nine Object Storage (S3-compatible) because
 * the local filesystem is ephemeral. When BUCKET_ENDPOINT is set, this file
 * tells Craft to use that S3 bucket for any filesystem with the handle "nine_s3".
 *
 * Setup steps:
 *  1. Create an Object Storage bucket in the Nine console.
 *  2. Add BUCKET_ENDPOINT, BUCKET_KEY, BUCKET_SECRET, BUCKET_NAME as env vars
 *     in your Deploio app.
 *  3. In the Craft CP → Settings → Filesystems, create a new filesystem with
 *     handle "nine_s3" and type "Amazon S3". The credentials below are injected
 *     at runtime — you don't need to enter them in the CP.
 *  4. Create an asset volume that uses the "nine_s3" filesystem.
 */

use craft\awss3\Fs;
use craft\helpers\App;

if (!App::env('BUCKET_ENDPOINT')) {
    return [];
}

return [
    'nine_s3' => [
        'type'              => Fs::class,
        // Leave keyId and secret empty so the plugin skips the AWS STS exchange
        // and the SDK credential chain picks up AWS_ACCESS_KEY_ID /
        // AWS_SECRET_ACCESS_KEY env vars directly. Nine Object Storage credentials
        // are not AWS IAM credentials and are rejected by STS.
        'keyId'             => '',
        'secret'            => '',
        'region'            => App::env('BUCKET_REGION') ?: 'us-east-1',
        'bucket'            => App::env('BUCKET_NAME'),
        'subfolder'         => 'uploads',
        'makeUploadsPublic' => true,
        // Set the base URL to endpoint/bucket only — Craft appends the subfolder
        // via addSubfolderToRootUrl. If the URL in project config (set via the CP)
        // already includes the subfolder, Craft would produce a double-subfolder URL
        // like /uploads/uploads/. Driving the URL from env vars here prevents that.
        'url'                    => rtrim(App::env('BUCKET_ENDPOINT') ?: '', '/') . '/' . App::env('BUCKET_NAME'),
        'addSubfolderToRootUrl'  => true,
    ],
];
