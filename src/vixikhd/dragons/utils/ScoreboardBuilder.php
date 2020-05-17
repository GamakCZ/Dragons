<?php

/**
 * My library for creating scoreboards (part of SW pro), originally from MyStats plugin
 * - Can be found at https://gist.github.com/GamakCZ/bccaf4d3bfe8b2401f9a65524b2d3d3a
 */

declare(strict_types=1);

namespace vixikhd\dragons\utils;

use pocketmine\entity\Entity;
use pocketmine\network\mcpe\protocol\RemoveObjectivePacket;
use pocketmine\network\mcpe\protocol\SetDisplayObjectivePacket;
use pocketmine\network\mcpe\protocol\SetScorePacket;
use pocketmine\network\mcpe\protocol\types\ScorePacketEntry;
use pocketmine\Player;

/**
 * Class ScoreboardBuilder
 * @package czechpmdevs\mystats
 */
class ScoreboardBuilder {

    /** @var int $line */
    private static $eid;

    /** @var string[] $boards */
    private static $boards = [];

    /**
     * @param Player $player
     */
    public static function removeBoard(Player $player) {
        $pk = new RemoveObjectivePacket();
        $pk->objectiveName = $player->getName();
        $player->dataPacket($pk);

        unset(self::$boards[$player->getName()]);
    }

    /**
     * @param Player $player
     * @param string $text
     */
    public static function sendBoard(Player $player, string $text) {
        if(isset(self::$boards[$player->getName()]) && self::$boards[$player->getName()] == $text) return;

        if(isset(self::$boards[$player->getName()])) {
            self::removeBoard($player);
        }

        $lines = explode("\n", $text);
        $title = array_shift($lines);
        foreach (self::buildBoard($player->getName(), $title, implode("\n", $lines)) as $packet) {
            $player->dataPacket($packet);
        }

        self::$boards[$player->getName()] = $text;
    }

    /**
     * @param string $id
     * @param string $title
     * @param string $text
     * @return array
     */
    public static function buildBoard(string $id, string $title, string $text) {
        $pk = new SetDisplayObjectivePacket();
        $pk->objectiveName = $id;
        $pk->displayName = $title;
        $pk->sortOrder = 0;
        $pk->criteriaName = "dummy";
        $pk->displaySlot = "sidebar";

        $packets[] = clone $pk;
        self::$eid = Entity::$entityCount++;

        $pk = new SetScorePacket();
        $pk->type = $pk::TYPE_CHANGE;
        $pk->entries = self::buildLines($id, $text);
        $packets[] = clone $pk;
        return $packets;
    }

    /**
     * @param string $id
     * @param string $text
     * @return ScorePacketEntry[] $lines
     */
    private static function buildLines(string $id, string $text): array {
        $texts = explode("\n", $text);
        $lines = [];
        $fixDuplicates = [];
        foreach ($texts as $line) {
            $entry = new ScorePacketEntry();
            $entry->score = count($lines);
            $entry->scoreboardId = count($lines);
            $entry->objectiveName = $id;
            $entry->entityUniqueId = self::$eid;
            $entry->type = ScorePacketEntry::TYPE_FAKE_PLAYER;

            $text = " " . $line . str_repeat(" ", 2); // it seems better;
            fixDuplicate:
            if(in_array($text, $fixDuplicates)) {
                $text .= " ";
                goto fixDuplicate;
            }
            $fixDuplicates[] = $text;
            $entry->customName = $text;
            $lines[] = $entry;
        }
        return $lines;
    }
}