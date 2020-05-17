<?php

declare(strict_types=1);

namespace vixikhd\dragons\arena;

use pocketmine\math\Vector3;
use pocketmine\utils\Random;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\entity\EnderDragon;

/**
 * Class DragonTargetManager
 * @package vixikhd\dragons\arena
 */
class DragonTargetManager {

    public const MAX_DRAGON_MID_DIST = 64; // Dragon will rotate when will be distanced 64 blocks from map center

    /** @var Dragons $plugin */
    public $plugin;
    /** @var Vector3 $blocks */
    public $blocks = [];
    /** @var Vector3 $baits */
    public $baits = [];
    /** @var Vector3 $mid */
    public $mid; // Used when all the blocks the are broken

    /** @var EnderDragon[] $dragons */
    public $dragons = [];

    /** @var Random $random */
    public $random;

    /**
     * DragonTargetManager constructor.
     * @param Arena $plugin
     * @param Vector3[] $blocksToDestroy
     * @param Vector3 $mid
     */
    public function __construct(Arena $plugin, array $blocksToDestroy, Vector3 $mid) {
        $this->plugin = $plugin;
        $this->blocks = $blocksToDestroy;
        $this->mid = $mid;

        $this->random = new Random();
    }

    /**
     * @return Vector3
     */
    public function getDragonTarget(): Vector3 {
        foreach ($this->baits as $key => $vector3) {
            $pos = $vector3->ceil();
            unset($this->baits[$key]);

            return $pos;
        }

        return empty($this->blocks) ? $this->mid : $this->blocks[array_rand($this->blocks, 1)];
    }

    /**
     * @param EnderDragon $dragon
     *
     * @param int $x
     * @param int $y
     * @param int $z
     */
    public function removeBlock(EnderDragon $dragon, int $x, int $y, int $z): void {
        $this->plugin->level->setBlockIdAt($x, $y, $z, 0); // Todo - animations
        unset($this->blocks["$x:$y:$z"]);

        if($this->random->nextBoundedInt(500) === 0) {
            $dragon->lookAt($this->getDragonTarget());
        }
    }

    public function addDragon(): void {
        $findSpawnPos = function (Vector3 $mid): Vector3 {
            $randomAngle = mt_rand(0, 359);
            $x = ((DragonTargetManager::MAX_DRAGON_MID_DIST - 5) * cos($randomAngle)) + $mid->getX();
            $z = ((DragonTargetManager::MAX_DRAGON_MID_DIST - 5) * sin($randomAngle)) + $mid->getZ();

            return new Vector3($x, $mid->getY(), $z);
        };

        $dragon = new EnderDragon($this->plugin->level, EnderDragon::createBaseNBT($findSpawnPos($this->mid), new Vector3()), $this);
        $dragon->lookAt($this->getDragonTarget());

        $dragon->spawnToAll();
    }

    /**
     * @param Vector3 $baitPos
     */
    public function addBait(Vector3 $baitPos) {
        $this->baits[] = $baitPos;
    }
}