<?php

namespace thewebdudes\craftimageurltransformer\models;

use craft\base\Model;

/**
 * ImageTransformer settings
 */
class Settings extends Model
{
    public const TRANSFORMER_IMAGE_WSRV = 'wsrvImage';
    public const TRANSFORMER_IMAGE_CLOUDFLARE = 'cloudflareImage';
    public const TRANSFORMER_IMAGE_CLOUDFLARE_WORKER = 'cloudflareWorkerImage';
    public const TRANSFORMER_IMAGE_IMAGOR = 'imagorImage';
    public mixed $transformer = self::TRANSFORMER_IMAGE_IMAGOR;
    public string $secret = '';
    public string $transformerBaseUrl = '';
}
