<?php

declare(strict_types=1);

namespace vixikhd\dragons\entity;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\level\Level;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\MoveActorAbsolutePacket;
use pocketmine\Player;
use pocketmine\timings\Timings;
use vixikhd\dragons\arena\DragonTargetManager;
use vixikhd\dragons\effects\CustomParticle;
use vixikhd\dragons\math\Math;

/**
 * Class EnderDragon
 * - This class has 2x smaller bounding box
 *
 * @package vixikhd\dragons\entity
 */
class EnderDragon extends Living {

    public const NETWORK_ID = Entity::ENDER_DRAGON;

    /** @var DragonTargetManager $targetManager */
    public $targetManager;

    /** @var float $width */
    public $width = 8.0;
    /** @var float $height */
    public $height = 4.0;

    /**
     * EnderDragon constructor.
     *
     * @param Level $level
     * @param CompoundTag $nbt
     * @param DragonTargetManager|null $targetManager
     */
    public function __construct(Level $level, CompoundTag $nbt, DragonTargetManager $targetManager = null) {
        parent::__construct($level, $nbt);
        if($targetManager === null) {
            $this->flagForDespawn();
        }

        $this->targetManager = $targetManager;
    }

    /**
     * @param int $tickDiff
     * @return bool
     */
    public function entityBaseTick(int $tickDiff = 1): bool { // TODO - make better movement system
        $return = parent::entityBaseTick($tickDiff);
        if($this->targetManager === null) {
            $this->flagForDespawn();
            return false;
        }

        if($this->distance($this->targetManager->mid) >= DragonTargetManager::MAX_DRAGON_MID_DIST || $this->getY() < 4 || $this->getY() > 250) {
            $this->lookAt($this->targetManager->getDragonTarget());
            $this->setMotion($this->getDirectionVector());
            return true;
        }

        $this->setMotion($this->getDirectionVector());

        return $return;
    }

    /**
     * Function copied from PocketMine (api missing - setting entity noclip)
     *
     * @param float $dx
     * @param float $dy
     * @param float $dz
     */
    public function move(float $dx, float $dy, float $dz): void {
        $this->blocksAround = null;

        Timings::$entityMoveTimer->startTiming();

        $movX = $dx;
        $movY = $dy;
        $movZ = $dz;

        if($this->keepMovement){
            $this->boundingBox->offset($dx, $dy, $dz);
        }else{
            $this->ySize *= 0.4;

            $axisalignedbb = clone $this->boundingBox;

            assert(abs($dx) <= 20 and abs($dy) <= 20 and abs($dz) <= 20, "Movement distance is excessive: dx=$dx, dy=$dy, dz=$dz");

            $list = $this->level->getCollisionCubes($this, $this->level->getTickRateTime() > 50 ? $this->boundingBox->offsetCopy($dx, $dy, $dz) : $this->boundingBox->addCoord($dx, $dy, $dz), false);
            foreach ($list as $bb) {
                $this->targetManager->removeBlock($this, (int)$bb->minX, (int)$bb->minY, (int)$bb->minZ);
            }

            $this->boundingBox->offset(0, $dy, 0); // x
            $fallingFlag = ($this->onGround or ($dy != $movY and $movY < 0));
            $this->boundingBox->offset($dx, 0, 0); // y
            $this->boundingBox->offset(0, 0, $dz); // z

            if($this->stepHeight > 0 and $fallingFlag and $this->ySize < 0.05 and ($movX != $dx or $movZ != $dz)){
                $cx = $dx;
                $cy = $dy;
                $cz = $dz;
                $dx = $movX;
                $dy = $this->stepHeight;
                $dz = $movZ;

                $axisalignedbb1 = clone $this->boundingBox;

                $this->boundingBox->setBB($axisalignedbb);

//                $list = $this->level->getCollisionCubes($this, $this->boundingBox->addCoord($dx, $dy, $dz), false);
//                foreach ($list as $bb) {
//                    $this->targetManager->removeBlock($this, (int)$bb->minX, (int)$bb->minY, (int)$bb->minZ);
//                }
                foreach (Math::getCollisionBlocks($this->level, $this->boundingBox->addCoord($dx, $dy, $dz)) as $block) {
                    $this->targetManager->removeBlock($this, $block->getX(), $block->getY(), $block->getZ());
                }

                $this->boundingBox->offset(0, $dy, 0);
                $this->boundingBox->offset($dx, 0, 0);
                $this->boundingBox->offset(0, 0, $dz);

                if(($cx ** 2 + $cz ** 2) >= ($dx ** 2 + $dz ** 2)){
                    $dx = $cx;
                    $dy = $cy;
                    $dz = $cz;
                    $this->boundingBox->setBB($axisalignedbb1);
                }
                else {
                    $this->ySize += 0.5;
                }
            }
        }

        $this->x = ($this->boundingBox->minX + $this->boundingBox->maxX) / 2;
        $this->y = $this->boundingBox->minY - $this->ySize;
        $this->z = ($this->boundingBox->minZ + $this->boundingBox->maxZ) / 2;

        $this->checkChunks();
        $this->checkBlockCollision();
        $this->checkGroundState($movX, $movY, $movZ, $dx, $dy, $dz);
        $this->updateFallState($dy, $this->onGround);

        if($movX != $dx){
            $this->motion->x = 0;
        }
        if($movY != $dy){
            $this->motion->y = 0;
        }
        if($movZ != $dz){
            $this->motion->z = 0;
        }

        Timings::$entityMoveTimer->stopTiming();
    }

    /**
     * Wtf mojang
     * - Function edited to send +180 yaw
     *
     * @param bool $teleport
     */
    protected function broadcastMovement(bool $teleport = false) : void{
        $pk = new MoveActorAbsolutePacket();
        $pk->entityRuntimeId = $this->id;
        $pk->position = $this->getOffsetPosition($this);

        //this looks very odd but is correct as of 1.5.0.7
        //for arrows this is actually x/y/z rotation
        //for mobs x and z are used for pitch and yaw, and y is used for headyaw
        $pk->xRot = $this->pitch;
        $pk->yRot = ($this->yaw + 180) % 360; //TODO: head yaw
        $pk->zRot = ($this->yaw + 180) % 360;

        if($teleport){
            $pk->flags |= MoveActorAbsolutePacket::FLAG_TELEPORT;
        }

        $this->level->broadcastPacketToViewers($this, $pk);
    }

    /**
     * @param EntityDamageEvent $source
     */
    public function attack(EntityDamageEvent $source): void {
        if($source->getCause() !== $source::CAUSE_ENTITY_ATTACK && $source->getCause() !== $source::CAUSE_PROJECTILE) {
            $source->setCancelled(true);
        }

        if($source instanceof EntityDamageByEntityEvent && $source->getCause() === $source::CAUSE_PROJECTILE) {
            $this->setRotation(($this->getYaw() + 180) % 360, ($this->getPitch() + 180) % 360);
            $this->setMotion($this->getDirectionVector());

            for($x = -10; $x < 10; $x++) {
                for($y = -10; $y < 10; $y++) {
                    for($z = - 10; $z < 10; $z++) {
                        if($this->distance($this->add($x, $y, $z)) < 5) {
                            $this->getLevel()->addParticle(new CustomParticle(CustomParticle::VILLAGER_ANGRY, $this->add($x, $y, $z)));
                        }
                    }
                }
            }
        }

        parent::attack($source);
    }

    /**
     * @param Player $player
     */
    public function onCollideWithPlayer(Player $player): void {
        $player->attack(new EntityDamageByEntityEvent($this, $player, EntityDamageEvent::CAUSE_ENTITY_ATTACK, 0.5));

        parent::onCollideWithPlayer($player);
    }

    public function setOnFire(int $seconds): void {
    }

    /**
     * @return string
     */
    public function getName(): string {
        return "Ender Dragon";
    }
}