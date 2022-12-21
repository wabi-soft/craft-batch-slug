<?php

namespace wabisoft\craftbatchslug;

use Craft;
use craft\base\Model;
use craft\base\Plugin;
use craft\web\twig\variables\CraftVariable;
use wabisoft\craftbatchslug\models\Settings;
use wabisoft\craftbatchslug\services\Remap;
use wabisoft\craftbatchslug\variables\BatchSlugHelper;
use yii\base\Event;

/**
 * Batch Slug plugin
 *
 * @method static BatchSlug getInstance()
 * @method Settings getSettings()
 * @author wabisoft <support@wabisoft.com>
 * @copyright wabisoft
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read Remap $remap
 */
class BatchSlug extends Plugin
{
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => ['remap' => Remap::class],
        ];
    }

    public function init()
    {
        parent::init();
        Craft::$app->onInit(function() {
            Event::on(
                CraftVariable::class,
                CraftVariable::EVENT_INIT,
                function(Event $e) {
                    /** @var CraftVariable $variable */
                    $variable = $e->sender;
                    $variable->set('batchSlugHelper', BatchSlugHelper::class);
                }
            );
        });
    }

    protected function createSettingsModel(): ?Model
    {
        return Craft::createObject(Settings::class);
    }

    protected function settingsHtml(): ?string
    {
        return Craft::$app->view->renderTemplate('batch-slug/_settings.twig', [
            'plugin' => $this,
            'settings' => $this->getSettings(),
        ]);
    }

    private function attachEventHandlers(): void
    {
    }
}
