<?php

declare(strict_types=1);

namespace vixikhd\dragons\entity;

use pocketmine\block\Block;
use pocketmine\entity\object\FallingBlock;
use pocketmine\level\particle\DestroyBlockParticle;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ListTag;

/**
 * Class ThrownBlock
 * @package vixikhd\dragons\entity
 */
class ThrownBlock extends FallingBlock {

    /**
     * @param float $dx
     * @param float $dy
     * @param float $dz
     */
    public function move(float $dx, float $dy, float $dz): void {
        $cubes = $this->level->getCollisionCubes($this, $this->boundingBox->addCoord($dx, $dy, $dz), false);
        if(count($cubes) > 0 && $this->ticksLived > 20) {
            $this->remove();
            return;
        }

        parent::move($dx, $dy, $dz);
    }

    public function remove() {
        for($i = 0; $i < 5; $i++) {
            $this->level->addParticle(new DestroyBlockParticle($this->add(lcg_value(), lcg_value(), lcg_value()), $this->block));
        }

        $this->flagForDespawn();
    }

    /**
     * Helper function which creates minimal NBT needed to spawn an entity.
     *
     * @param Vector3 $pos
     * @param Vector3|null $motion
     * @param float $yaw
     * @param float $pitch
     * @param Block|null $block
     *
     * @return CompoundTag
     */
    public static function createBaseNBT(Vector3 $pos, ?Vector3 $motion = null, float $yaw = 0.0, float $pitch = 0.0, ?Block $block = null): CompoundTag {
        if($block === null) {
            return parent::createBaseNBT($pos, $motion, $yaw, $pitch);
        }

        return new CompoundTag("", [
            new ListTag("Pos", [
                new DoubleTag("", $pos->x),
                new DoubleTag("", $pos->y),
                new DoubleTag("", $pos->z)
            ]),
            new ListTag("Motion", [
                new DoubleTag("", $motion !== null ? $motion->x : 0.0),
                new DoubleTag("", $motion !== null ? $motion->y : 0.0),
                new DoubleTag("", $motion !== null ? $motion->z : 0.0)
            ]),
            new ListTag("Rotation", [
                new FloatTag("", $yaw),
                new FloatTag("", $pitch)
            ]),
            new IntTag("TileID", $block->getId()),
            new ByteTag("Data", $block->getDamage())
        ]);
    }
}