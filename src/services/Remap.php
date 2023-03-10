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
        $matches = self::collectMatches($rows, $section, $process);
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
        $count = 0;
        foreach ($matches as $match) {
            $update = self::updateEntrySlug($match, $section);
            if($update) {
                $count++;
            }
        }
        $message = "Updated " . $count . " Entries in " . $section . " out of " . count($matches) . " matches.";
        Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'batch-slug');
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
        Craft::$app->elements->saveElement($entry);
        return true;
    }


    private static function collectMatches($rows, $section, $process) {
        $matches = [];
        foreach ($rows as $row) {
            $entry = self::matchEntry($row['from'], $section, $process);

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

    private static function matchEntry($url, $section, $process) {
        $uri = self::getUriFromUrl($url);

        if(!$uri) {
            return false;
        }
        $entry = Entry::find()
            ->section($section)
            ->uri($uri)
            ->collect();

        if(!$entry || sizeof($entry) == 0) {
            if($process) {
                $message = "Did not find an entry with an URI of " . $uri . " in section: " . $section;
                Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'batch-slug');
            }
            return false;
        }
        if(sizeof($entry) > 1) {
            if($process) {
                $message = "More than one entry with an URI of " . $uri . " in section: " . $section;
                Craft::getLogger()->log($message, Logger::LEVEL_INFO, 'batch-slug');
            }
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
