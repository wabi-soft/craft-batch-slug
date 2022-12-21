<?php

namespace wabisoft\craftbatchslug\services;

use Craft;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\errors\ElementNotFoundException;
use craft\errors\VolumeException;
use craft\helpers\StringHelper;
use PhpOffice\PhpSpreadsheet\IOFactory;
use yii\base\Component;
use yii\base\Exception;
use yii\base\InvalidConfigException;
use yii\log\Logger;

/**
 * Remap service
 */
class Remap extends Component
{
    public function preview() {
        return self::parseRemap();
    }

    public function save() {
        return self::parseRemap(true);
    }


    /**
     * @throws InvalidConfigException
     * @throws VolumeException
     */
    private static function parseRemap($process = false) {
        $assetId = Craft::$app->request->getParam('assetId');
        $section = Craft::$app->request->getParam('section');
        $csv =  Asset::find()->id(intval($assetId))->one();
        if (!$csv) {
            return false;
        }
        $fullPath = $csv->getCopyOfFile();
        $rows = self::getSheet($fullPath);
        $matches = self::collectMatches($rows, $section);
        if(!$process) {
            return $matches;
        }
        self::processMatches($matches, $section);
        return true;
    }


    /**
     * @throws Exception
     * @throws \Throwable
     * @throws ElementNotFoundException
     */
    private static function processMatches($matches, $section) {
        foreach ($matches as $match) {
            self::updateEntrySlug($match, $section);
        }
    }


    /**
     * @throws Exception
     * @throws \Throwable
     * @throws ElementNotFoundException
     */
    private static function updateEntrySlug($match, $section): bool
    {
        $entry = Entry::find()
            ->id($match['id'])
            ->one();
        if(!$entry) {
            return false;
        }
        /*
         * Verify that we don't already have that slug in the
         * section
         */
        $existing = Entry::find()
            ->section($section)
            ->uri($match['to'])
            ->one();
        if($existing) {
            /*
             * Log that warning
             */
            $message = "Existing entry with id: " . $existing->id . " in section: " . $existing->section->handle . ' with uri of ' . $match['to'];
            Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'batch-slug');
            return false;
        }
        $message = "Updating Entry ID: " . $entry->id . " from slug of: " . $entry->slug . " to " . $match['updatedSlug'];
        $entry->slug = $match['updatedSlug'];
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'batch-slug');

        return Craft::$app->elements->saveElement($entry);
    }


    private static function collectMatches($rows, $section) {
        $matches = [];
        foreach ($rows as $row) {
            $entry = self::matchEntry($row['from'], $section);

            if($entry) {
                $matches[] = [
                    'id' => $entry['id'],
                    'from' => self::getUriFromUrl($row['from']),
                    'to' => self::getUriFromUrl($row['to']),
                    'updatedSlug' => self::getSlugFromString($row['to']),
                ];
            }
        }

        return $matches;
    }

    private static function matchEntry($url, $section) {
        $uri = self::getUriFromUrl($url);

        if(!$uri) {
            return false;
        }
        $entry = Entry::find()
            ->section($section)
            ->uri($uri)
            ->collect();

        if(!$entry) {
            return false;
        }
        if(sizeof($entry) > 1) {
            return false;
        }
        if(sizeof($entry) == 0) {
            return false;
        }
        return $entry[0];
    }

    private static function getUriFromUrl($url) {
        if(!$url) {
            return false;
        }
        $replacements = [];
        $currentSite = Craft::$app->request->getBaseUrl();
        $replacements =  \wabisoft\craftbatchslug\BatchSlug::getInstance()->getSettings()->urlsToRemove;
        $removeSite = $url;
        foreach ($replacements as $replacement) {
            $removeSite = StringHelper::replaceFirst( $removeSite, $replacement, '');
        }
        $removeSite = StringHelper::trim( $removeSite, '/');

        return StringHelper::trim($removeSite);
    }

    private static function getSlugFromString($string) {
        $keys = parse_url($string); // parse the url
        $path = explode("/", $keys['path']); // splitting the path
        return end($path);
    }

    private static function getSheet($path) {
        $spreadsheet = IOFactory::load($path);
        $worksheet = $spreadsheet->getActiveSheet(1);
        $rows = [];
        foreach ($worksheet->toArray() as $row) {
            $rows[] = [
                "from" => $row[0],
                "to" => $row[1]
            ];
        }
        return $rows;
    }
}
