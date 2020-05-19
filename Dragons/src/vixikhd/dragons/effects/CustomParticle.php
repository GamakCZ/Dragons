<?php

declare(strict_types=1);

namespace vixikhd\dragons\effects;

use pocketmine\level\particle\Particle;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\SpawnParticleEffectPacket;

/**
 * Class CustomParticle
 * @package vixikhd\playerparticles\particle
 */
class CustomParticle extends Particle {

    public const DRAGON_BREATH_TRAIL = "minecraft:dragon_breath_trail";
    public const DRAGON_BREATH_LINGERING = "minecraft:dragon_breath_lingering";
    public const VILLAGER_HAPPY = "minecraft:villager_happy";
    public const MOBSPELL_EMITTER = "minecraft:mobspell_emitter";
    public const FLAME = "minecraft:basic_flame_particle";
    public const VILLAGER_ANGRY = "minecraft:villager_angry";

    /** @var string $name */
    private $name;

    /**
     * CustomParticle constructor.
     * @param string $particleName
     * @param Vector3 $pos
     */
    public function __construct(string $particleName, Vector3 $pos) {
        $this->name = $particleName;
        parent::__construct($pos->getX(), $pos->getY(), $pos->getZ());
    }


    /**
     * @inheritDoc
     */
    public function encode() {
        $pk = new SpawnParticleEffectPacket();
        $pk->position = $this->asVector3();
        $pk->particleName = $this->name;

        return $pk;
    }
}