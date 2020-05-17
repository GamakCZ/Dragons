<?php

declare(strict_types=1);

namespace vixikhd\dragons\arena;

use pocketmine\level\Level;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use pocketmine\tile\Sign;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\kit\defaults\Kit;
use vixikhd\dragons\Lang;
use vixikhd\dragons\utils\ScoreboardBuilder;

/**
 * Class ArenaScheduler
 * @package vixikhd\dragons\arena
 */
class ArenaScheduler extends Task {

    public const START_TIME = 40; // TODO - Change start time
    public const RESTART_TIME = 10;

    public const PLAYERS_TO_START = 4;

    /** @var Arena $plugin */
    public $plugin;

    /** @var int $phase */
    public $phase = 0;

    /** @var int $startTime */
    public $startTime = self::START_TIME;
    /** @var int $gameTime */
    public $gameTime = 0;
    /** @var int $restartTime */
    public $restartTime = self::RESTART_TIME;

    /**
     * ArenaScheduler constructor.
     * @param Arena $plugin
     */
    public function __construct(Arena $plugin) {
        $this->plugin = $plugin;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        $this->sendScoreboard();
        $this->refreshSign();

        switch ($this->phase) {
            case 0:
                if(count($this->plugin->players) < self::PLAYERS_TO_START) { // TODO - Change min players to start
                    $this->plugin->broadcastTip(Lang::getMessage("waiting", [(string)count($this->plugin->players), $this->plugin->data["slots"]]));
                    break;
                }
                $this->plugin->broadcastTip(Lang::getMessage("starting", [gmdate("i:s", $this->startTime)]));
                $this->startTime--;

                if($this->startTime <= 0) {
                    $this->plugin->startGame();
                }
                break;
            case 1:
                $this->plugin->checkEnd();

                if($this->gameTime % 60 == 0 && count($this->plugin->dragonTargetManager->dragons) < $this->plugin->data["maxDragons"]) {
                    $this->plugin->dragonTargetManager->addDragon();
                    $this->plugin->broadcastMessage(Lang::getGamePrefix() . Lang::getMessage("dragon-spawned"));
                }
                $this->gameTime++;
                break;
            case 2:
                if($this->restartTime < 0) {
                    $this->plugin->broadcastMessage(Lang::getGamePrefix() . Lang::getMessage("restarting"));

                    $players = $this->plugin->players + $this->plugin->spectators;
                    foreach ($players as $player) {
                        $this->plugin->disconnectPlayer($player);
                    }
                }

                if($this->restartTime == 0) {
                    $this->plugin->mapReset->loadMap($this->plugin->level->getFolderName());
                }

                if($this->restartTime == -5) {
                    $this->plugin->reloadArena();
                }
                $this->restartTime--;
                break;
        }
    }

    public function refreshSign() {
        /** @var string $level */
        $level = $this->plugin->data["joinSignLevel"];
        /** @var Vector3 $pos */
        $pos = $this->plugin->data["joinSignPos"];

        if(!$this->plugin->plugin->getServer()->isLevelGenerated($level)) {
            return;
        }
        if(!$this->plugin->plugin->getServer()->isLevelLoaded($level)) {
            $this->plugin->plugin->getServer()->loadLevel($level);
        }

        $targetLevel = $this->plugin->plugin->getServer()->getLevelByName($level);
        if(!$targetLevel instanceof Level) {
            return;
        }

        $sign = $targetLevel->getTile($pos);
        if(!$sign instanceof Sign) {
            return;
        }

        $map = "§a---";
        if($this->plugin->level instanceof Level) {
            $map = $this->plugin->level->getName();
        }

        $phase = $this->phase === 0 ?
            ((count($this->plugin->players) < $this->plugin->data["slots"]) ? "§aJoin" : "§6Full") :
            (($this->phase === 1) ? "§5InGame" : "§cRestarting...");

        $sign->setText(
            "§5§lDragons§r",
            "§9[§b " . (string)count($this->plugin->players) . " / " . (string)$this->plugin->data["slots"] . " §9]",
            $phase,
            "§8Map: §7$map");
    }

    public function sendScoreboard() {
        if($this->plugin->level === null) {
            return;
        }

        $scoreboardSettings = $this->plugin->plugin->config["scoreboards"];
        if(!$scoreboardSettings["enabled"]) {
            var_dump($scoreboardSettings);
            return;
        }

        $map = $this->plugin->level === null ? "§a---" : "§a{$this->plugin->level->getName()}";

        /**
         * @param array $settings
         * @param string $map
         *
         * @return string
         */
        $replaceDefault = function (array $settings, string $map): string {
            $text = implode("\n", $settings);

            return str_replace(["{%players}", "{%maxPlayers}", "{%map}"], [(string)count($this->plugin->players), (string)$this->plugin->data["slots"], $map], $text);
        };

        /**
         * @param Dragons $plugin
         * @param Player $player
         *
         * @return string
         */
        $getKit = function (Dragons $plugin, Player $player): string {
            $kit = $plugin->kitManager->playerKits[$player->getName()] ?? "---";
            if($kit instanceof Kit) {
                $kit = $kit->getName();
            }

            return $kit;
        };

        switch ($this->phase) {
            case 0:
                if(count($this->plugin->players) < self::PLAYERS_TO_START) {
                    foreach ($this->plugin->players as $player) {
                        ScoreboardBuilder::removeBoard($player);
                        ScoreboardBuilder::sendBoard($player, str_replace(
                            ["{%kit}"],
                            [$getKit($this->plugin->plugin, $player)],
                            $replaceDefault($scoreboardSettings["formats"]["waiting"], $map)
                        ));
                    }
                }
                else {
                    foreach ($this->plugin->players as $player) {
                        ScoreboardBuilder::removeBoard($player);
                        ScoreboardBuilder::sendBoard($player, str_replace(
                            ["{%kit}", "{%startTime}"],
                            [$getKit($this->plugin->plugin, $player), gmdate("i:s", $this->startTime)],
                            $replaceDefault($scoreboardSettings["formats"]["starting"], $map)
                        ));
                    }
                }
                break;
            case 1:
                $players = $this->plugin->players + $this->plugin->spectators; // Did you try this already? xd
                foreach ($players as $player) {
                    ScoreboardBuilder::removeBoard($player);
                    ScoreboardBuilder::sendBoard($player, str_replace(
                        ["{%kit}", "{%gameTime}"],
                        [$getKit($this->plugin->plugin, $player), gmdate("i:s", $this->gameTime)],
                        $replaceDefault($scoreboardSettings["formats"]["playing"], $map)
                    ));
                }
                break;
            case 2:
                $players = $this->plugin->players + $this->plugin->spectators;
                foreach ($players as $player) {
                    ScoreboardBuilder::removeBoard($player);
                    ScoreboardBuilder::sendBoard($player, str_replace(
                        ["{%kit}", "{%restartTime}"],
                        [$getKit($this->plugin->plugin, $player), gmdate("i:s", $this->restartTime)],
                        $replaceDefault($scoreboardSettings["formats"]["restarting"], $map)
                    ));
                }
                break;
        }
    }

    public function resetTimer() {
        $this->startTime = self::START_TIME;
        $this->gameTime = 0;
        $this->restartTime = self::RESTART_TIME;

        $this->phase = 0;
    }
}