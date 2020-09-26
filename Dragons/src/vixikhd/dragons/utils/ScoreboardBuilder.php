<?php

/**
 * My library for creating scoreboards from OpenAPI
 * - Can be found at https://github.com/BedrockPlay/OpenAPI
 */

declare(strict_types=1);

namespace vixikhd\dragons\utils;

use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;

/**
 * Class ScoreboardBuilder
 * @package bedrockplay\openapi\scoreboard
 */
class ScoreboardBuilder {

    /** @var array $displayedTexts */
    private static $displayedTexts = [];
    /** @var array $scoreBoards */
    private static $scoreBoards = [];
    /** @var array $titles */
    private static $titles = [];

    /**
     * Sends text as a scoreboard to the player
     *
     * @param Player $player
     * @param string $text
     */
    public static function sendScoreBoard(Player $player, string $text) {
        self::$displayedTexts[$player->getName()] = $text;

        $text = self::formatLines($text);
        $text = self::removeDuplicateLines($text);

        $splitText = explode("\n", $text);
        $title = array_shift($splitText);

        if(!isset(self::$titles[$player->getName()]) || self::$titles[$player->getName()] !== $title) {
            if(isset(self::$titles[$player->getName()])) {
                self::removeScoreBoard($player);
            }

            self::createScoreBoard($player, self::$titles[$player->getName()] = $title);
        }

        if(!isset(self::$scoreBoards[$player->getName()])) {
            self::sendLines($player, $splitText);
            self::$scoreBoards[$player->getName()] = $splitText;
            return;
        }

        self::updateLines($player, self::$scoreBoards[$player->getName()], $splitText);
        self::$scoreBoards[$player->getName()] = $splitText;
    }

    /**
     * Removes scoreboard from player
     *
     * @param Player $player
     */
    public static function removeScoreBoard(Player $player) {
        if(!isset(self::$titles[$player->getName()])) {
            return;
        }
        if(isset(self::$scoreBoards[$player->getName()])) {
            unset(self::$scoreBoards[$player->getName()]);
        }
        if(isset(self::$displayedTexts[$player->getName()])) {
            unset(self::$displayedTexts[$player->getName()]);
        }

        $pk = new RemoveObjectivePacket();
        $pk->objectiveName = strtolower($player->getName());

        $player->dataPacket($pk);

        unset(self::$titles[$player->getName()]);
    }

    /**
     * Returns if player has sent SetDisplayObjectivePacket
     *
     * @param Player $player
     * @return bool
     */
    public static function hasObjectiveDisplayed(Player $player): bool {
        return isset(self::$titles[$player->getName()]);
    }

    /**
     * Returns displayed text
     *
     * @param Player $player
     * @return string
     */
    public static function getDisplayedText(Player $player): string {
        return self::$displayedTexts[$player->getName()] ?? "";
    }

    /**
     * Creates objective which can display lines
     *
     * @param Player $player
     * @param string $title
     */
    private static function createScoreBoard(Player $player, string $title) {
        $pk = new SetDisplayObjectivePacket();
        $pk->objectiveName = strtolower($player->getName());
        $pk->displayName = $title;
        $pk->sortOrder = 0; // Ascending
        $pk->criteriaName = "dummy";
        $pk->displaySlot = "sidebar";

        $player->dataPacket($pk);
    }

    /**
     * Displays lines
     *
     * @param Player $player
     * @param array $splitText
     * @param int[]|null $filter
     */
    private static function sendLines(Player $player, array $splitText, ?array $filter = null) {
        if(is_array($filter)) {
            $splitText = array_filter($splitText, function ($key) use ($filter) {
                return in_array($key, $filter);
            }, ARRAY_FILTER_USE_KEY);
        }

        $entries = [];
        foreach ($splitText as $i => $line) {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = strtolower($player->getName());
            $entry->scoreboardId = $entry->score = $i + 1; // Lmao it works :,D
            $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;
            $entry->customName = $line;

            $entries[] = $entry;
        }

        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_CHANGE;
        $pk->entries = $entries;

        $player->dataPacket($pk);
    }

    /**
     * Updates scoreboard lines
     *
     * @param Player $player
     * @param array $oldSplitText
     * @param array $splitText
     */
    private static function updateLines(Player $player, array $oldSplitText, array $splitText) {
        if(count($oldSplitText) == count($splitText)) {
            $updateList = [];

            foreach ($splitText as $i => $line) {
                if($oldSplitText[$i] != $line) {
                    $updateList[] = $i;
                }
            }

            self::removeLines($player, $updateList);
            self::sendLines($player, $splitText, $updateList);
            return;
        }

        if(count($oldSplitText) > count($splitText)) {
            $updateList = [];

            foreach ($oldSplitText as $i => $line) {
                if(!isset($splitText[$i])) {
                    $updateList[] = $i;
                    continue;
                }

                if($splitText[$i] != $line) {
                    $updateList[] = $i;
                }
            }

            self::removeLines($player, $updateList);
            self::sendLines($player, $updateList);
            return;
        }

        $toRemove = [];
        $toSend = [];
        foreach($splitText as $i => $line) {
            if(!isset($oldSplitText[$i])) {
                $toSend[] = $i;
                continue;
            }

            if($oldSplitText[$i] != $line) {
                $toRemove[] = $i;
                $toSend[] = $i;
                continue;
            }
        }

        self::removeLines($player, $toRemove);
        self::sendLines($player, $splitText, $toSend);
    }

    /**
     * @param Player $player
     * @param int[] $lines
     */
    private static function removeLines(Player $player, array $lines) {
        $entries = [];
        foreach ($lines as $line) {
            $entry = new ScorePacketEntry();
            $entry->objectiveName = strtolower($player->getName());
            $entry->scoreboardId = $entry->score = $line + 1;

            $entries[] = $entry;
        }

        $pk = new SetScorePacket();
        $pk->type = SetScorePacket::TYPE_REMOVE;
        $pk->entries = $entries;

        $player->dataPacket($pk);
    }


    /**
     * Client removes duplicate lines, so we must add edit them to be different
     *
     * @param string $text
     * @return string
     */
    private static function removeDuplicateLines(string $text): string {
        $lines = explode("\n", $text);

        $used = [];
        foreach ($lines as $i => $line) {
            if($i === 0) {
                continue; // Title
            }

            while (in_array($line, $used)) {
                $line .= " ";
            }

            $lines[$i] = $line;
            $used[] = $line;
        }

        return implode("\n", $lines);
    }

    /**
     * Adds " " to begin of every line
     *
     * @param string $text
     * @return string
     */
    private static function formatLines(string $text): string {
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if($i === 0) {
                continue;
            }

            $lines[$i] = " " . $line . " ";
        }

        return implode("\n", $lines);
    }
}