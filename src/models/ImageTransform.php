<?php

namespace thewebdudes\craftimageurltransformer\models;

use craft\models\ImageTransform as CraftImageTransform;

class ImageTransform extends CraftImageTransform
{
    public ?string $filters = '';
}