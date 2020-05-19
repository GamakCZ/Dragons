<?php

declare(strict_types=1);

namespace vixikhd\dragons\effects\kit;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\VoxelRayTrace;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use vixikhd\dragons\Dragons;

/**
 * Class BridgeEffect
 * @package vixikhd\bridge\be
 */
class BridgeEffect extends Task {

    public const MATERIALS = [236, 35, 241];

    /** @var Player $player */
    public $player;
    /** @var array $blocks */
    public $blocks = [];
    /** @var Level $level */
    public $level;

    /** @var int $phase */
    protected $phase = 0;
    /** @var int $stayTick */
    protected $stayTick = 0;

    /** @var array $toRemove */
    public $toRemove = [];

    /**
     * BridgeEffect constructor.
     * @param Player $player
     */
    public function __construct(Player $player) {
        $this->player = $player;
        $this->level = $player->getLevel();

        $direction = $player->getDirection();

        for($i = 1; $i < 20; $i++) {
            for($j = -1; $j <= 1; $j++) {
                $start = $player->add(($direction % 2 === 1 ? $j : 0), 0, ($direction % 2 === 0 ? $j : 0))->ceil();
                foreach (VoxelRayTrace::inDirection($start, $player->getDirectionVector(), $i) as $vector3) {
                    if(!in_array($vector3, $this->blocks)) {
                        $this->blocks[] = $vector3;
                    }
                }
            }
        }

        Dragons::getInstance()->getScheduler()->scheduleRepeatingTask($this, 2);
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        $level = $this->level;
        if(!$level instanceof Level) {
            Dragons::getInstance()->getScheduler()->cancelTask($this->getTaskId());
            return;
        }

        switch ($this->phase) {
            // Spawning the bridge
            case 0:
                for($i = 0; $i < 3; $i++) {
                    if(count($this->blocks) === 0) {
                        $this->phase++;
                        break;
                    }

                    $block = array_shift($this->blocks);
                    $this->toRemove[] = clone $this->level->getBlock($block);
                    $level->setBlock($block, Block::get(self::MATERIALS[array_rand(self::MATERIALS, 1)], 4));
                }
                break;
            // Waiting for remove
            case 1:
                if($this->stayTick === 20) { // 2 seconds
                    $this->phase++;
                    break;
                }

                $this->stayTick++;
                break;
            // Removing the bridge
            case 2:
                for($i = 0; $i < 3; $i++) {
                    if(count($this->toRemove) === 0) {
                        Dragons::getInstance()->getScheduler()->cancelTask($this->getTaskId());
                        break;
                    }

                    $block = array_shift($this->toRemove);
                    $level->setBlock($block, $block);
                }
                break;
        }
    }
}