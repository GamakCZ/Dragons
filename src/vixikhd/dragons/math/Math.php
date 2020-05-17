<?php

declare(strict_types=1);

namespace vixikhd\dragons\math;

use pocketmine\entity\Entity;
use pocketmine\math\Vector3;
use pocketmine\Player;

/**
 * Class Math
 * @package vixikhd\dragons\math
 */
class Math {

    /**
     * @param Vector3 $pos1
     * @param Vector3 $pos2
     *
     * @return Vector3
     */
    public static function calculateCenterPosition(Vector3 $pos1, Vector3 $pos2): Vector3 {
        $max = new Vector3(max($pos1->getX(), $pos2->getX()), max($pos1->getY(), $pos2->getY()), max($pos1->getZ(), $pos2->getZ()));
        $min = new Vector3(min($pos1->getX(), $pos2->getX()), min($pos1->getY(), $pos2->getY()), min($pos1->getZ(), $pos2->getZ()));

        return $min->add($max->subtract($min)->divide(2)->ceil());
    }
}