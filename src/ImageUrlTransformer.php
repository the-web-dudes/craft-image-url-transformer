<?php

namespace thewebdudes\craftimageurltransformer;

use Craft;
use craft\base\Event;
use craft\base\Model;
use craft\base\Plugin;
use craft\imagetransforms\FallbackTransformer;
use craft\imagetransforms\ImageTransformer as CraftImageTransformer;
use craft\models\ImageTransform;
use thewebdudes\craftimageurltransformer\behaviors\ImageTransformBehaviour;
use thewebdudes\craftimageurltransformer\web\twig\Extension;

/**
 * Image Url Transformer plugin
 *
 * @method static ImageUrlTransformer getInstance()
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

        Craft::$container->set(
            CraftImageTransformer::class,
            ImageTransformer::class,
        );

        Craft::$container->set(
            FallbackTransformer::class,
            ImageTransformer::class,
        );

        Event::on(
            ImageTransform::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            function (Event $event) {
                $event->behaviors['filters'] = ImageTransformBehaviour::class;
            }
        );

        Craft::$app->view->registerTwigExtension(new Extension());
    }
}
