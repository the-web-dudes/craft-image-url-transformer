<?php

namespace thewebdudes\craftimageurltransformer\web\twig;

use craft\elements\Asset;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;
use yii\base\InvalidConfigException;
use Throwable;

/**
 * Twig extension
 */
class Extension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('renderImage', [$this, 'renderImage'], ['is_safe' => ['html']]),
            new TwigFunction('renderAsset', [$this, 'renderAsset'], ['is_safe' => ['html']]),
            new TwigFunction('renderShopifyImage', [$this, 'renderShopifyImage'], ['is_safe' => ['html']]),
        ];
    }

    private function _getVideoId(?string $url)
    {
        if (!$url) {
            return null;
        }
        // YouTube patterns
        $youtubePatterns = [
            // Standard YouTube URLs
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube\.com\/v\/)([a-zA-Z0-9_-]{11})/',
            // YouTube shorts
            '/youtube\.com\/shorts\/([a-zA-Z0-9_-]{11})/',
            // YouTube with additional parameters
            '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/',
        ];

        // Vimeo patterns
        $vimeoPatterns = [
            // Standard Vimeo URLs
            '/vimeo\.com\/(\d+)/',
            // Vimeo player URLs
            '/player\.vimeo\.com\/video\/(\d+)/',
            // Vimeo with additional parameters
            '/vimeo\.com\/.*\/(\d+)/',
        ];

        // Check YouTube patterns
        foreach ($youtubePatterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'provider' => 'youtube',
                    'id' => $matches[1]
                ];
            }
        }

        // Check Vimeo patterns
        foreach ($vimeoPatterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return [
                    'provider' => 'vimeo',
                    'id' => $matches[1]
                ];
            }
        }

        return null;
    }

    private function _parseSized(array $sizes): string
    {
        $breaks = [
            'sm'  => '(max-width: 767px) ',
            'md'  => '(max-width: 1023px) ',
            'lg'  => '(max-width: 1279px) ',
            'xl'  => '(max-width: 1535px) ',
            '2xl' => '(max-width: 1718px) ',
        ];

        $parsedParts = [];
        foreach ($sizes as $key => $columns) {
            if (isset($breaks[$key])) {

                $col = floor(100 / $columns) . "vw";
                $parsedParts[] = $breaks[$key] . $col;
            } else {
                $parsedParts[] = floor(100 / $columns) . "vw";
            }
        }

        return implode(', ', $parsedParts);
    }

    private function _transformedSize($width, $height, $transform): array
    {
        if ($transform && (isset($transform['width']) || isset($transform['height']))) {
            if (isset($transform['width']) && isset($transform['height'])) {
                $width = $transform['width'];
                $height = $transform['height'];
            } else {
                if (isset($transform['width']) && $height) {
                    $height = round(($height * ($transform['width'] / $width)), 0, PHP_ROUND_HALF_DOWN);
                    $width = $transform['width'];
                }

                if (isset($transform['height']) && $width) {
                    $width = round($width * ($transform['height'] / $height), 0, PHP_ROUND_HALF_DOWN);
                    $height = $transform['height'];
                }
            }
        }

        return [$width, $height];
    }

    /**
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function renderImage(Asset $asset, array $options = []): string
    {
        $class = $options['class'] ?? null;
        if ($asset->extension === 'svg') {
            return Html::tag(
                'div',
                Html::svg($asset),
                [
                    'class' => [
                        $class ?? null,
                    ],
                ]
            );
        }

        $transform = $options['transform'] ?? null;
        $width     = $options['width'] ?? ($asset->assetWidth ?? ($asset->width ?? null));
        $height    = $options['height'] ?? ($asset->assetHeight ?? ($asset->height ?? null));
        $alt       = $options['alt'] ?? ($asset->caption ?? ($asset->alt ?? ($asset->title ?? null)));
        $isGif     = $asset->extension === 'gif';


        $src = !$isGif && $transform ? $asset->getUrl($transform) : $asset->url;
        $srcset = !$isGif && $transform ? $asset->getSrcset([$width / 2, $width, $width * 2], $transform) : null;

        return $this->imageMarkup([
            ...$options,
            'src' => $src,
            'srcset' => $srcset,
            'width' => $width,
            'height' => $height,
            'alt' => $alt,
            'isGif' => $isGif,
        ]);
    }

    public function imageMarkup($options = []): string
    {
        $class     = $options['class'] ?? null;
        $transform = $options['transform'] ?? null;
        $width     = $options['width'] ?? null;
        $height    = $options['height'] ?? null;
        $alt       = $options['alt'] ?? ($asset->caption ?? ($asset->alt ?? ($asset->title ?? null)));
        $lazy      = $options['lazy'] ?? true;
        $sizes     = $options['sizes'] ?? [];
        $isGif     = $options['isGif'] ?? false;
        $src       = $options['src'] ?? '';
        $srcset    = $options['srcset'] ?? null;

        return Html::tag('img', null, [
            'class' => $class,
            'width' => $width,
            'height' => $height,
            'alt' => $alt,
            'sizes' => !$isGif ? $this->_parseSized($sizes) : null,
            'srcset' => $srcset,
            'src' => $src,
            'loading' => $lazy ? 'lazy' : null,
            'onload' => $lazy ? 'this.classList.add("loaded")' : null
        ]);
    }

    private function _gcd(int $a, int $b): int
    {
        return $b ? $this->_gcd($b, $a % $b) : $a;
    }

    /**
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function renderImageWrap(?Asset $asset, array $options = []): string
    {
        $class = $options['class'] ?? null;
        $width = $options['width'] ?? ($asset->assetWidth ?? ($asset->width ?? null));
        $height = $options['height'] ?? ($asset->assetHeight ?? ($asset->height ?? null));
        $inset = $options['inset'] ?? false;
        $hasMobile = $options['hasMobile'] ?? false;
        $isMobile = $options['isMobile'] ?? false;
        unset($options['class']);

        [$width, $height] = $this->_transformedSize($width, $height, $options['transform'] ?? null);

        $ratio = $options['ratio'] ?? null;
        if ($width && $height) {
            $gcd = $this->_gcd($width, $height);
            $ratio = ($width / $gcd) . '/' . ($height / $gcd);
        }

        $options['width'] = $width;
        $options['height'] = $height;

        return Html::tag('div', $asset ? $this->renderImage($asset, $options) : $this->imageMarkup($options), [
            'class' => [
                'image-wrap',
                $inset ? 'inset-image' : null,
                $hasMobile ? 'max-md:hidden' : null,
                $isMobile ? 'md:hidden' : null,
                $class
            ],
            'style' => $ratio && !$inset ? 'aspect-ratio: ' . $ratio : null
        ]);
    }

    public function renderVideo(?Asset $asset, array $options = []): string
    {
        if (!$asset) {
            return '';
        }

        $class     = $options['class'] ?? null;
        $transform = $options['transform'] ?? null;
        $width     = $options['width'] ?? ($asset->assetWidth ?? ($asset->width ?? null));
        $height    = $options['height'] ?? ($asset->assetHeight ?? ($asset->height ?? null));
        $autoplay  = $options['autoplay'] ?? true;
        $lazy      = $options['lazy'] ?? true;
        $inset     = $options['inset'] ?? false;
        $hasMobile = $options['hasMobile'] ?? false;
        $isMobile  = $options['isMobile'] ?? false;
        $src       = $options['src'] ?? ($asset->externalVideoUrl ?? $asset->url);
        $poster    = $asset->videoThumbnail?->eagerly()->one() ?? null;

        // TODO: add ffmpeg or cloudflare

        $ratio = null;
        if ($width && $height) {
            $gcd = $this->_gcd($width, $height);
            $ratio = ($width / $gcd) . '/' . ($height / $gcd);
        }

        return Html::tag(
            'div',
            Html::tag(
                'video',
                Html::tag(
                    'source',
                    null,
                    [
                        'src' => !$lazy ? $src : null,
                        'crossorigin' => 'anonymous',
                        'data-src' => $lazy ? $src : null,
                        'type' => 'video/mp4',
                    ]
                ),
                [
                    'muted' => '',
                    'playsinline' => '',
                    'loop' => '',
                    'autoplay' => $autoplay,
                    'poster' => $poster ? ($transform ? $poster->getUrl($transform) : $poster->url) : null,
                ]
            ),
            [
                'class' => [
                    'video-wrap',
                    $hasMobile ? 'to-md:hidden' : null,
                    $isMobile ? 'md:hidden' : null,
                    $lazy ? 'lazy-video' : null,
                    $inset ? 'inset-image' : null,
                    $class,
                ],
                'style' => $ratio ? 'aspect-ratio: ' . $ratio : ''
            ]
        );
    }

    /**
     * @throws InvalidConfigException
     */
    public function renderPlayer(Asset $asset, array $options = []): string
    {
        $inset     = $options['inset'] ?? false;
        $url       = $options['src'] ?? ($asset->externalVideoUrl ?? null);
        $code      = $option['code'] ?? $this->_getVideoId($asset->externalVideoUrl);
        $autoplay  = $options['autoplay'] ?? null;
        $width     = $asset->assetWidth ?? ($asset->width ?? 1920);
        $height    = $asset->assetHeight ?? ($asset->height ?? 1080);
        $transform = $options['transform'] ?? null;
        $poster    = ($asset->isExternalVideo ?? null) ? $asset : $asset->videoThumbnail?->eagerly()->one();

        return Html::tag(
            'b-player',
            Html::tag(
                'div',
                $code ? Html::tag('div', null, [
                    'data' => [
                        'el' => 'main',
                        'plyr-provider' => $code['provider'],
                        'plyr-embed-id' => $code['id'],
                        'poster' => $poster ? ($transform ? $poster->getUrl($transform) : $poster->url) : null,
                    ]
                ]) : Html::tag('video', null, [
                    'src' => $url ?? ($asset->url ?? null),
                    'width' => $width,
                    'height' => $height,
                    'data' => [
                        'el' => 'main',
                        'poster' => $poster ? ($transform ? $poster->getUrl($transform) : $poster->url) : null,
                    ]
                ]),
                [
                    'class' => [
                        'video-player',
                        $inset ? 'inset-video' : null,
                    ]
                ]
            ),
            ['autoplay' => $autoplay]
        );
    }

    /**
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function renderAsset(?Asset $asset, array $options = []): string
    {
        if (!$asset) {
            return '';
        }
        if ($asset->kind === 'video' || ($asset->isExternalVideo ?? null)) {
            $mobileVideo = $asset->mobileVideo->eagerly()->one() ?? null;
            $asPlayer = $options['asPlayer'] ?? ($asset->asPlayer ?? false);
            $code = $options['code'] ?? $this->_getVideoId($asset->externalVideoUrl);
            if ($code) {
                $options['code'] = $code;
            }
            if ($asPlayer || $code) {
                return $this->renderPlayer($asset, $options);
            }
            $mobileVideoRender = $mobileVideo ? $this->renderVideo($mobileVideo, [
                ...$options,
                'isMobile' => true,
            ]) : '';

            if ($mobileVideo) {
                $options['hasMobile'] = true;
            }

            return $mobileVideoRender.$this->renderVideo($asset, $options);
        }

        $mobileAsset = $asset->mobileImage->eagerly()->one() ?? null;
        $mobileImage = $mobileAsset ? $this->renderImageWrap($mobileAsset, [
            ...$options,
            'isMobile' => true,
        ]) : '';

        if ($mobileAsset) {
            $options['hasMobile'] = true;
        }

        return $mobileImage.$this->renderImageWrap($asset, $options);
    }

    /**
     * @throws Throwable
     * @throws InvalidConfigException
     */
    public function renderShopifyImage($shopifyImage = [], $options = []): string
    {
        $src = $shopifyImage['url'] ?? null;
        $width = $shopifyImage['width'] ?? null;
        $height = $shopifyImage['height'] ?? null;
        $options['width'] = $width;
        $options['height'] = $height;
        $widthTransform = $options['transform']['width'] ?? 1800;
        $heightTransform = $options['transform']['height'] ?? null;
        $srcset = null;

        if ($width) {
            $src = UrlHelper::url($src, [
                'width' => $widthTransform ?? null,
                'height' => $heightTransform ?? null,
                'crop' => 'center'
            ]);
            $srcset = implode(', ', array_map(function ($m) use ($src, $widthTransform, $heightTransform) {
                return UrlHelper::url($src, [
                        'width' => $widthTransform ? $widthTransform * $m : null,
                        'height' => $heightTransform ? $heightTransform * $m : null,
                        'crop' => 'center'
                    ]) . ' ' . $widthTransform * $m . 'w';
            }, [0.5, 1, 2]));
        }
        return $this->renderImageWrap(null, [
            ...$options,
            'src' => $src,
            'srcset' => $srcset,
        ]);
    }
}
