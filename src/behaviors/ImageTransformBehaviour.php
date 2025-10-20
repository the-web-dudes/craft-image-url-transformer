<?php

namespace thewebdudes\craftimageurltransformer\behaviors;

use yii\base\Behavior;

class ImageTransformBehaviour extends Behavior
{
    public ?string $filters = '';
}