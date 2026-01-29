<?php

namespace thewebdudes\craftimageurltransformer;

use Craft;
use craft\base\Plugin;
use craft\imagetransforms\FallbackTransformer;
use craft\imagetransforms\ImageTransformer as CraftImageTransformer;
use craft\models\ImageTransform;
use craft\base\Model;
use thewebdudes\craftimageurltransformer\web\twig\Extension;
use thewebdudes\craftimageurltransformer\models\Settings;

/**
 * Image Url Transformer plugin
 *
 * @method static ImageUrlTransformer getInstance()
 * @method Settings getSettings()
 * @author @the-web-dudes
 * @copyright @the-web-dudes
 * @license MIT
 */
class ImageUrlTransformer extends Plugin
{
    public string $schemaVersion = '1.0.0';

//    public static function config(): array
//    {
//        return [
//            'components' => [
//                // Define component configs here...
//            ],
//        ];
//    }

    public function init(): void
    {
        parent::init();

        Craft::$container->set(ImageTransform::class, [
            'class' => models\ImageTransform::class,
        ]);

        Craft::$container->set(
            CraftImageTransformer::class,
            ImageTransformer::class,
        );

        Craft::$container->set(
            FallbackTransformer::class,
            ImageTransformer::class,
        );

        Craft::$app->view->registerTwigExtension(new Extension());
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }
}
