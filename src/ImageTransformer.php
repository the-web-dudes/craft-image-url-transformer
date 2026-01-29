<?php

namespace thewebdudes\craftimageurltransformer;

use Craft;
use craft\base\Component;
use craft\base\imagetransforms\ImageTransformerInterface;
use craft\elements\Asset;
use craft\errors\ImageTransformException;
use craft\helpers\App;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\models\ImageTransform;
use Illuminate\Support\Collection;
use thewebdudes\craftimageurltransformer\models\Settings;
use yii\base\InvalidConfigException;
use yii\base\NotSupportedException;

class ImageTransformer extends Component implements ImageTransformerInterface
{
    public const SUPPORTED_IMAGE_FORMATS = ['jpg', 'jpeg', 'gif', 'png', 'avif'];
    protected Asset $asset;

    public function getTransformUrl(Asset $asset, ImageTransform $imageTransform, bool $immediately): string
    {
        $this->asset = $asset;
        $this->assertTransformable();

        $params = $this->buildTransformParams($imageTransform);
        return $this->assetUrl($params);
    }

    protected function assertTransformable(): void
    {
        $mimeType = $this->asset->getMimeType();

        if ($mimeType === 'image/gif' && !Craft::$app->getConfig()->getGeneral()->transformGifs) {
            throw new NotSupportedException('GIF files shouldn’t be transformed.');
        }

        if ($mimeType === 'image/svg+xml' && !Craft::$app->getConfig()->getGeneral()->transformSvgs) {
            throw new NotSupportedException('SVG files shouldn’t be transformed.');
        }
    }

    private function sign($path)
    {
        $secret = ImageUrlTransformer::getInstance()->getSettings()->secret;
        $hash = base64_encode(
            hash_hmac('sha1', $path, $secret, true)
        );

        $hash = str_replace(['+', '/'], ['-', '_'], $hash);

        return $hash . '/' . $path;
    }

    public function getTransformer(): mixed
    {
        return ImageUrlTransformer::getInstance()->getSettings()->transformer;
    }

    protected function assetUrl(Collection $params)
    {
        $transformer = $this->getTransformer();

        if (is_callable($transformer)) {
            return call_user_func($transformer, $params, $this->asset);
        }

        $map = [
            Settings::TRANSFORMER_IMAGE_WSRV => [$this, 'wsrvUrl'],
            Settings::TRANSFORMER_IMAGE_CLOUDFLARE => [$this, 'cloudflareUrl'],
            Settings::TRANSFORMER_IMAGE_CLOUDFLARE_WORKER => [$this, 'cloudflareWorkerUrl'],
            Settings::TRANSFORMER_IMAGE_IMAGOR => [$this, 'imagorUrl'],
        ];
        return call_user_func($map[$transformer], $params);
    }

    protected function cloudflareWorkerUrl(Collection $params)
    {
        return $this->cloudflareUrl($params, true);
    }
    protected function cloudflareUrl(Collection $params, $forWorker = false)
    {
        $base = ImageUrlTransformer::getInstance()->getSettings()->transformerBaseUrl ?: UrlHelper::siteUrl();

        $cloudflareParams = [];
        $width = $params->get('width') ?? '';
        $height = $params->get('height') ?? '';
        $quality = $params->get('quality') ?? '';
        $filters = $params->get('filters') ?? '';
        $format = $params->get('format') ?? 'webp';
        $fit = $params->get('fit') ?? '';

        if ($width) {
            $cloudflareParams[] = "width=$width";
        }

        if ($height) {
            $cloudflareParams[] = "height=$height";
        }

        if ($quality) {
            $cloudflareParams[] = "quality=$quality";
        }

        if ($filters) {
            $cloudflareParams[] = $filters;
        }

        if ($format) {
            $cloudflareParams[] = "format=$format";
        }

        if ($fit) {
            $cloudflareParams[] = "fit=$fit";
        }

        $transformUrlParts = [
            rtrim($base, '/'),
            $forWorker
                ? $this->sign(implode(',', $cloudflareParams)."/{$this->asset->getPath()}")
                : "cdn-cgi/images".implode(',', $cloudflareParams)."/{$this->getBasePath()}"
        ];

        return Html::encodeSpaces(implode('/', $transformUrlParts));
    }

    /**
     * @throws InvalidConfigException
     */
    protected function wsrvUrl(Collection $params)
    {
        $width = $params->get('width') ?? '';
        $height = $params->get('height') ?? '';
        $quality = $params->get('quality') ?? '';
        $filters = $params->get('filters') ?? '';
        $format = $params->get('format') ?? 'webp';
        $fit = $params->get('fit') ?? '';

        $params = [
            'url' => ltrim($this->asset->getUrl(), 'https:')
        ];

        if ($width) {
            $params['w'] = $width;
        }

        if ($height) {
            $params['h'] = $height;
        }

        if ($quality) {
            $params['q'] = $quality;
        }

        if ($format) {
            $params['output'] = $format;
        }

        if ($fit) {
            $params['fit'] = $fit;
        }

        if ($filters) {
            $params['filt'] = $filters;
        }

        return Html::encodeSpaces(
            UrlHelper::url('https://wsrv.nl/', $params)
        );
    }

    protected function getBasePath(): string
    {
        return rtrim(
            $this->asset->fs->getRootUrl() . $this->asset->getVolume()->getSubpath(),
            '/'
        );
    }

    protected function imagorUrl(Collection $params)
    {
        $basePath = $this->getBasePath();
        $parts = parse_url($basePath);

        $width = $params->get('width') ?? '';
        $height = $params->get('height') ?? '';
        $quality = $params->get('quality') ?? '';
        $filters = $params->get('filters') ?? '';
        $format = $params->get('format') ?? 'webp';
        $fit = $params->get('fit') ?? '';

        $filterString = '';
        if ($format) {
            $filterString .= ":format($format)";
        }

        if ($quality) {
            $filterString .= ":quality($quality)";
        }

        if ($filters) {
            $filterString .= "$filters";
        }

        $directives = "{$width}x{$height}";

        if ($fit) {
            $directives = "$fit/$directives";
        }
        if  ($filterString) {
            $directives .= "/filters$filterString";
        }

        $base = ImageUrlTransformer::getInstance()->getSettings()->transformerBaseUrl ?? '';
        if (!$base && isset($parts['host'])) {
            $base = (isset($parts['scheme']) ? ($parts['scheme'] . ':') : '') . "//{$parts['host']}" . (isset($parts['port']) ? (':' . $parts['port']) : '');
        }

        return Html::encodeSpaces(
            "$base/imagor/".$this->sign("$directives".($parts['path'] ?? '')."/{$this->asset->getPath()}")
        );
    }

    /**
     * Cloudflare Images does not support purging resized variants individually. URLs starting with /cdn-cgi/ cannot be purged. However, purging of the original image’s URL will also purge all of its resized variants.
     * @param Asset $asset
     * @return void
     */
    public function invalidateAssetTransforms(Asset $asset): void
    {
//        if(CloudflareImageTransforms::getInstance()->getSettings()->enableCachePurge) {
//            $job = new PurgeImageCache(['files' => [$asset->getUrl()]]);
//            Craft::$app->getQueue()->push($job);
//        }
    }

    public function buildTransformParams(ImageTransform $imageTransform): Collection
    {
        return Collection::make([
            'width' => $imageTransform->width,
            'height' => $imageTransform->height,
            'quality' => $imageTransform->quality ?: Craft::$app->getConfig()->general->defaultImageQuality,
            'format' => $this->getFormatValue($imageTransform),
            'fit' => $this->getFitValue($imageTransform),
            'filters' => $imageTransform->filters,
//            'background' => $this->getBackgroundValue($imageTransform),
//            'gravity' => $this->getGravityValue($imageTransform),
        ])->whereNotNull();
    }

    protected function getGravityValue(ImageTransform $imageTransform): ?string
    {
        $value = $this->getGravity($imageTransform);

        if(!$value) {
            return null;
        }

        $value = array_values($value);

        return "$value[0]x$value[1]";
    }
    protected function getGravity(ImageTransform $imageTransform): ?array
    {
        if ($this->asset->getHasFocalPoint()) {
            return $this->asset->getFocalPoint();
        }

        if ($imageTransform->position === 'center-center') {
            return null;
        }

        // TODO: maybe just do this in Craft
        $parts = explode('-', $imageTransform->position);
        $yPosition = $parts[0] ?? null;
        $xPosition = $parts[1] ?? null;

        try {
            $x = match ($xPosition) {
                'top' => 0,
                'center' => 0.5,
                'bottom' => 1,
            };
            $y = match ($yPosition) {
                'top' => 0,
                'center' => 0.5,
                'bottom' => 1,
            };
        } catch (\UnhandledMatchError $e) {
            throw new ImageTransformException('Invalid `position` value.');
        }

        return [$x, $y];
    }

    protected function getBackgroundValue(ImageTransform $imageTransform): ?string
    {
        return $imageTransform->mode === 'letterbox'
            ? $imageTransform->fill ?? '#FFFFFF'
            : null;
    }

    protected function getFitValue(ImageTransform $imageTransform): string
    {
        $transformer = $this->getTransformer();
        if (is_callable($transformer)) {
            return $imageTransform->mode;
        }

        $fitMap = [
            Settings::TRANSFORMER_IMAGE_IMAGOR => [
                'stretch' => 'stretch',
                'crop' => '',
                'letterbox' => 'pad',
                'fit' => 'fit-in',
            ],
            Settings::TRANSFORMER_IMAGE_WSRV => [
                'stretch' => 'fill',
                'crop' => 'cover',
                'letterbox' => 'inside',
                'fit' => 'contain',
            ],
            Settings::TRANSFORMER_IMAGE_CLOUDFLARE => [
                'stretch' => 'squeeze',
                'crop' => 'cover',
                'letterbox' => 'scale-down',
                'fit' => 'contain',
            ],
            Settings::TRANSFORMER_IMAGE_CLOUDFLARE_WORKER => [
                'stretch' => 'squeeze',
                'crop' => 'cover',
                'letterbox' => 'scale-down',
                'fit' => 'contain',
            ]
        ];
        return $fitMap[$transformer][$imageTransform->mode ?? 'crop'];
    }

    protected function getFormatValue(ImageTransform $imageTransform): string
    {
        return match ($imageTransform->format) {
            'jpg' => 'jpeg',
            default => $imageTransform->format ?? 'webp',
        };
    }
}