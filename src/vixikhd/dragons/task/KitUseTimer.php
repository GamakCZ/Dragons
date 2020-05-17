<?php

declare(strict_types=1);

namespace vixikhd\dragons\task;

use pocketmine\Player;
use pocketmine\scheduler\Task;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\Lang;

/**
 * Class KitUseTimer
 * @package vixikhd\dragons\task
 */
class KitUseTimer extends Task {

    /** @var int[] $queue */
    private static $queue = [];
    /** @var Player[] $players */
    private static $players = [];

    /** @var Dragons $plugin */
    public $plugin;

    /**
     * KitUseTimer constructor.
     * @param Dragons $plugin
     */
    public function __construct(Dragons $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param Player $player
     * @return bool
     */
    public static function canUseKit(Player $player): bool {
        return !isset(self::$queue[$player->getName()]);
    }

    /**
     * @param Player $player
     */
    public static function addToQueue(Player $player) {
        self::$queue[$player->getName()] = 0;
        self::$players[$player->getName()] = $player;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        foreach (self::$queue as $name => $tick) {
            $player = self::$players[$name];

            if($tick === 10) {
                $player->sendMessage(Lang::getKitsPrefix() . "§aYou can use your kit again!");
                $player->sendPopup("§aKit Recharged!");

                unset(self::$queue[$player->getName()]);
                unset(self::$players[$player->getName()]);
                continue;
            }

            $progress = "§6Recharging the kit...\n§a";
            for($i = 0; $i < 10; $i++) {
                $progress .= ($i == $tick ? "§c||" : "||");
            }
            $player->sendPopup($progress);

            self::$queue[$name]++;
        }
    }
}