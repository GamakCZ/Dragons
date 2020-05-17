<?php

declare(strict_types=1);

namespace vixikhd\dragons\effects;

use pocketmine\level\Position;
use pocketmine\scheduler\Task;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\effects\particles\BaitSpiral;
use vixikhd\dragons\effects\particles\LeapBoost;

/**
 * Class Effects
 * @package vixikhd\dragons\effects
 */
class Effects {

    public const BAIT_SPIRAL = 0;
    public const LEAP_BOOST = 1;

    /** @var string[] $effects */
    private static $effects = [
        self::BAIT_SPIRAL => BaitSpiral::class,
        self::LEAP_BOOST => LeapBoost::class
    ];

    /**
     * @param int $id
     * @param mixed $effectData
     */
    public static function spawnEffect(int $id, $effectData) {
        $class = self::$effects[$id];
        /** @var Task $effect */
        $effect = new $class($effectData);

        Dragons::getInstance()->getScheduler()->scheduleRepeatingTask($effect, 1);
    }
}