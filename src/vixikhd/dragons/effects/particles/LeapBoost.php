<?php

declare(strict_types=1);

namespace vixikhd\dragons\effects\particles;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\Player;
use pocketmine\scheduler\Task;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\effects\CustomParticle;

/**
 * Class LeapBoost
 * @package vixikhd\dragons\effects\particles
 */
class LeapBoost extends Task {

    /** @var Player $player */
    public $player;
    /** @var int $particleTick */
    public $particleTick = 0;

    /**
     * LeapBoost constructor.
     * @param Player $player
     */
    public function __construct(Player $player) {
        $this->player = $player;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        // First particle
        $this->spawnParticle(Position::fromObject($this->player->add(
            cos(deg2rad($this->player->getYaw())),
            $this->player->getEyeHeight() / 2,
            sin(deg2rad($this->player->getYaw()))),
            $this->player->getLevel()));

        // Second particle
        $this->spawnParticle(Position::fromObject($this->player->add(
            cos(deg2rad(($this->player->getYaw() + 180) % 360)),
            $this->player->getEyeHeight() / 2,
            sin(deg2rad(($this->player->getYaw() + 180) % 360))),
            $this->player->getLevel()));

        if(($this->player->isOnGround() && $this->particleTick > 2) || $this->player->getY() < 10 || $this->player->getGamemode() === $this->player::SPECTATOR) {
            Dragons::getInstance()->getScheduler()->cancelTask($this->getTaskId());
        }

        $this->particleTick++;
    }

    /**
     * @param Position $pos
     */
    public function spawnParticle(Position $pos) {
        if($pos->getLevel() instanceof Level) {
            $pos->getLevel()->addParticle(new CustomParticle(CustomParticle::FLAME, $pos->subtract(0.001, 0, 0.001)));
            $pos->getLevel()->addParticle(new CustomParticle(CustomParticle::FLAME, $pos->add(0.001, 0, 0.001)));
        }
    }
}