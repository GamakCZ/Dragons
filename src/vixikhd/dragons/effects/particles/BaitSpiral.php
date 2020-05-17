<?php

declare(strict_types=1);

namespace vixikhd\dragons\effects\particles;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\scheduler\Task;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\effects\CustomParticle;

/**
 * Class BaitSpiral
 * @package vixikhd\dragons\effects\particles
 */
class BaitSpiral extends Task {

    /** @var Position $center */
    public $center;
    /** @var int $currentAngle */
    public $currentAngle = 0;

    /**
     * BaitSpiral constructor.
     * @param Position $center
     */
    public function __construct(Position $center) {
        $this->center = $center;
    }

    /**
     * @param int $currentTick
     */
    public function onRun(int $currentTick) {
        if($this->center->level === null) {
            goto cancelTask;
        }

        $angle = ($this->currentAngle * 8) % 360;
        $y = $this->currentAngle / 360 * 1.5;

        // First particle
        $this->spawnParticle(Position::fromObject($this->center->add(cos(deg2rad($angle)), $y, sin(deg2rad($angle))), $this->center->getLevel()));

        $angle = ($angle + 180) % 360;

        // Second particle
        $this->spawnParticle(Position::fromObject($this->center->add(cos(deg2rad($angle)), $y, sin(deg2rad($angle))), $this->center->getLevel()));

        if($this->currentAngle > 90) {
            cancelTask:
            Dragons::getInstance()->getScheduler()->cancelTask($this->getTaskId());
        }

        $this->currentAngle++;
    }

    /**
     * @param Position $pos
     */
    public function spawnParticle(Position $pos) {
        if($pos->getLevel() instanceof Level) {
            $pos->getLevel()->addParticle(new CustomParticle(CustomParticle::DRAGON_BREATH_LINGERING, $pos));
        }
    }
}