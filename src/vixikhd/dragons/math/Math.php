<?php

declare(strict_types=1);

namespace vixikhd\dragons\math;

use pocketmine\block\Block;
use pocketmine\level\Level;
use pocketmine\math\AxisAlignedBB;
use pocketmine\math\Vector3;

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

    /**
     * Originally took from Level.php
     *
     * @param Level $level
     * @param AxisAlignedBB $bb
     *
     * @return Block[]
     */
    public static function getCollisionBlocks(Level $level, AxisAlignedBB $bb): array {
        $minX = (int) floor($bb->minX - 1);
        $minY = (int) floor($bb->minY - 1);
        $minZ = (int) floor($bb->minZ - 1);
        $maxX = (int) floor($bb->maxX + 1);
        $maxY = (int) floor($bb->maxY + 1);
        $maxZ = (int) floor($bb->maxZ + 1);

        $collides = [];

        for($z = $minZ; $z <= $maxZ; ++$z){
            for($x = $minX; $x <= $maxX; ++$x){
                for($y = $minY; $y <= $maxY; ++$y) {
                    $block = $level->getBlockAt($x, $y, $z);
                    foreach ($block->getCollisionBoxes() as $blockBB) {
                        if ($blockBB->intersectsWith($bb)) {
                            $collides[] = $block;
                            break;
                        }
                    }

                }
            }
        }

        return $collides;
    }
}