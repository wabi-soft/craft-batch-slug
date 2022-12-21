<?php

namespace wabisoft\craftbatchslug\controllers;

use Craft;
use craft\web\Controller;
use wabisoft\craftbatchslug\services\Remap;
use yii\web\Response;

/**
 * Process Updates controller
 */
class ProcessUpdatesController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /**
     * batch-slug/process-updates action
     */
    public function actionSection()
    {
        if(!self::canRemap()) {
            return;
        }
        (new Remap)->save();
        return;
    }

    private static function canRemap(): bool
    {
        $currentUser = Craft::$app->getUser()->getIdentity();
        if ($currentUser && $currentUser->can('accessCp')) {
            return true;
        }
        return false;
    }
}
