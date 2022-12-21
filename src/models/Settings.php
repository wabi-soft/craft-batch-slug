<?php

namespace wabisoft\craftbatchslug\models;

use Craft;
use craft\base\Model;

/**
 * Batch Slug settings
 */
class Settings extends Model
{
    public array $urlsToRemove = ['https://www.pjpower.com/'];
}
