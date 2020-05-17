<?php

declare(strict_types=1);

namespace vixikhd\dragons\arena;

use pocketmine\entity\EntityIds;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityLevelChangeEvent;
use pocketmine\event\inventory\CraftItemEvent;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerExhaustEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\network\mcpe\protocol\LevelSoundEventPacket;
use pocketmine\Player;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\effects\Effects;
use vixikhd\dragons\entity\EggBait;
use vixikhd\dragons\Lang;
use vixikhd\dragons\math\Math;
use vixikhd\dragons\task\KitUseTimer;
use vixikhd\dragons\utils\ScoreboardBuilder;
use vixikhd\dragons\utils\ServerManager;

/**
 * Class Arena
 * @package vixikhd\dragons\arena
 */
class Arena implements Listener {

    /** @var Dragons $plugin */
    public $plugin;

    /** @var ArenaScheduler $scheduler */
    public $scheduler;
    /** @var MapReset $mapReset */
    public $mapReset;
    /** @var DragonTargetManager $dragonTargetManager */
    public $dragonTargetManager;

    /** @var Level $level */
    public $level;

    /** @var array $data */
    public $data;

    /** @var Player[] $players */
    public $players = [];
    /** @var Player[] $spectators */
    public $spectators = [];

    /** @var Player[] $leaving */
    public $leaving = [];

    /**
     * Arena constructor.
     * @param Dragons $plugin
     * @param array $data
     */
    public function __construct(Dragons $plugin, array $data) {
        $this->data = $data;
        $this->plugin = $plugin;

        // lobby level
        if(!$plugin->getServer()->isLevelGenerated($this->data["lobby"])) {
            $plugin->getLogger()->error("Invalid lobby level!");
            return;
        }
        if(!$plugin->getServer()->isLevelLoaded($this->data["lobby"])) {
            $plugin->getServer()->loadLevel($this->data["lobby"]);
        }

        $this->scheduler = new ArenaScheduler($this);
        $this->mapReset = new MapReset($this);
        if(!file_exists($this->plugin->getDataFolder() . "saves/{$this->data["level"]}.zip")) {
            $this->mapReset->saveMap($this->plugin->getServer()->getLevelByName($this->data["level"]));
        }

        $this->mapReset->loadMap($this->data["level"]);

        $this->level = $this->plugin->getServer()->getLevelByName($this->data["level"]);

        $plugin->getServer()->getPluginManager()->registerEvents($this, $plugin);
        $plugin->getScheduler()->scheduleRepeatingTask($this->scheduler, 20);
    }


    /**
     * @param Player $player
     * @return bool
     */
    public function joinToArena(Player $player): bool {
        if(count($this->players) >= $this->data["slots"]) {
            $player->sendMessage(Lang::getPrefix() . Lang::getMessage("arena-full"));
            return false;
        }
        if($this->scheduler->phase > 0 || $this->scheduler->startTime <= 6) {
            $player->sendMessage(Lang::getPrefix() . Lang::getMessage("arena-ingame"));
            return false;
        }

        $this->players[$player->getName()] = $player;

        $player->setHealth(20);
        $player->setMaxHealth(20);
        $player->setFood(20);
        $player->extinguish();
        $player->teleport($this->plugin->getServer()->getLevelByName($this->data["lobby"])->getSpawnLocation());

        $player->setGamemode($player::ADVENTURE);

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();

        $player->getInventory()->setItem(7, Item::get(Item::FEATHER)->setCustomName("§r§eSelect kit\n§7[Use]"));
        $player->getInventory()->setItem(8, Item::get(Item::BED)->setCustomName("§r§eLeave the game\n§7[Use]"));

        $this->broadcastMessage(Lang::getMessage("join", [$player->getName()]));
        return true;
    }

    /**
     * @param Player $player
     * @param bool $findNewGame
     */
    public function disconnectPlayer(Player $player, bool $findNewGame = false) {
        $this->broadcastMessage(Lang::getMessage("quit", [$player->getName()]));

        if(isset($this->players[$player->getName()]))
            unset($this->players[$player->getName()]);
        if(isset($this->spectators[$player->getName()]))
            unset($this->spectators[$player->getName()]);

        $player->setHealth(20);
        $player->setMaxHealth(20);
        $player->setFood(20);
        $player->extinguish();
        $player->teleport($this->plugin->getServer()->getDefaultLevel()->getSafeSpawn());

        $player->getInventory()->clearAll();
        $player->getArmorInventory()->clearAll();
        $player->getCursorInventory()->clearAll();

        $player->setGamemode($this->plugin->getServer()->getDefaultGamemode());

        ScoreboardBuilder::removeBoard($player);

        if($findNewGame) {
            $arena = $this->plugin->emptyArenaChooser->getRandomArena();
            if($arena !== null && $arena->joinToArena($player)) {
                return;
            }
        }

        if($this->plugin->config["waterdog"]["enabled"]) {
            $lobby = $this->plugin->config["waterdog"]["lobby"] ?? null;
            if(!is_null($lobby)) {
                ServerManager::transferPlayer($player, $lobby);
            }
        }
    }

    /**
     * @param EntityDamageEvent $event
     */
    public function onDamage(EntityDamageEvent $event) {
        $player = $event->getEntity();
        if(!$player instanceof Player) {
            return;
        }

        if(!isset($this->players[$player->getName()])) {
            return;
        }

        if($this->scheduler->phase === 0) {
            $event->setCancelled(true);
            if($event->getCause() === $event::CAUSE_VOID) {
                $player->teleport($this->plugin->getServer()->getLevelByName($this->data["lobby"])->getSpawnLocation());
            }

            return;
        }

        if($this->scheduler->phase === 1) {
            if($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                if($damager instanceof Player) {
                    $event->setCancelled(true);
                }
            }

            if($player->getHealth() - $event->getFinalDamage() <= 0 && !$event->isCancelled()) {
                $player->setHealth(20);
                $player->setMaxHealth(20);
                $player->setFood(20);
                $player->extinguish();
                $player->teleport($this->data["spawns"][0]->add(0, 10));

                $player->getInventory()->clearAll();
                $player->getArmorInventory()->clearAll();
                $player->getCursorInventory()->clearAll();

                $player->getInventory()->setItem(7, Item::get(Item::MOB_HEAD, 3)->setCustomName("§r§eRandom teleport\n§7[Use]"));
                $player->getInventory()->setItem(8, Item::get(Item::BED)->setCustomName("§r§eLeave the game\n§7[Use]"));

                $player->setGamemode($player::SPECTATOR);

                $player->addTitle(Lang::getMessage("spectating"));

                if(isset($this->players[$player->getName()])) {
                    unset($this->players[$player->getName()]);
                }
                $this->spectators[$player->getName()] = $player;

                $this->broadcastMessage(Lang::getMessage("death", [$player->getName()]));
                $event->setCancelled(true);

                $this->checkEnd();
            }
        }

        if($this->scheduler->phase === 2) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) {
        $player = $event->getPlayer();

        if(isset($this->players[$player->getName()]) || isset($this->spectators[$player->getName()])) {
            $this->disconnectPlayer($player);
        }
    }

    /**
     * @param EntityLevelChangeEvent $event
     */
    public function onLevelChange(EntityLevelChangeEvent $event) {
        $player = $event->getEntity();
        if(!$player instanceof Player) {
            return;
        }

        if(!isset($this->players[$player->getName()]) && !isset($this->spectators[$player->getName()])) {
            return;
        }

        switch ($this->scheduler->phase) {
            case 0:
                if($event->getTarget()->getName() === $this->data["lobby"]) {
                    break;
                }
                if($this->level === null && $event->getTarget()->getName() !== $this->data["lobby"]) {
                    $this->disconnectPlayer($player);
                    break;
                }

                if($event->getTarget()->getId() !== $this->level->getId()) {
                    $this->disconnectPlayer($player);
                }
                break;
            case 2:
            case 1:
                $this->disconnectPlayer($player);
                break;
        }
    }

    /**
     * @param PlayerItemHeldEvent $event
     */
    public function onHeld(PlayerItemHeldEvent $event) {
        $player = $event->getPlayer();

        if(!isset($this->spectators[$player->getName()])) {
            return;
        }

        switch ($event->getItem()->getId()) {
            case ItemIds::MOB_HEAD:
                $event->setCancelled(true);
                if(count($this->players) === 0) {
                    $player->sendMessage(Lang::getGamePrefix() . Lang::getMessage("no-players"));
                }

                $playing = array_values($this->players);
                $randomPlaying = $playing[array_rand($playing, 1)];

                $player->teleport($randomPlaying);
                $player->sendMessage(Lang::getGamePrefix() . Lang::getMessage("random-tp", [$randomPlaying->getName()]));
                break;
            case ItemIds::BED:
                $event->setCancelled(true);
                if(!isset($this->leaving[$player->getName()])) {
                    $this->leaving[$player->getName()] = 0;
                    $player->sendMessage(Lang::getGamePrefix() . Lang::getMessage("leave-confirm"));
                    break;
                }

                $this->disconnectPlayer($player);
                break;
        }
    }

    /**
     * @param PlayerInteractEvent $event
     */
    public function onInteract(PlayerInteractEvent $event) {
        $player = $event->getPlayer();
        if(!isset($this->players[$player->getName()])) {
            /** @var string $level */
            $level = $this->data["joinSignLevel"];
            /** @var Vector3 $pos */
            $pos = $this->data["joinSignPos"];

            if($event->getBlock()->equals($pos) && $event->getBlock()->getLevel()->getName() == $level) {
                $this->joinToArena($player);
            }
            return;
        }

        switch ($this->scheduler->phase) {
            case 0:
                if($event->getAction() !== $event::RIGHT_CLICK_AIR && $event->getAction() !== $event::LEFT_CLICK_AIR) {
                    break;
                }

                switch ($event->getItem()->getId()) {
                    case Item::FEATHER:
                        $this->plugin->kitManager->showKitsForm($player);
                        break;
                    case Item::BED:
                        $this->disconnectPlayer($player);
                        break;

                }
                break;
            case 1:
                if($event->getAction() !== $event::RIGHT_CLICK_AIR && $event->getAction() !== $event::LEFT_CLICK_AIR) {
                    break;
                }

                switch ($event->getItem()->getId()) {
                    case Item::IRON_AXE:
                        if(KitUseTimer::canUseKit($player)) {
                            KitUseTimer::addToQueue($player);
                            $player->setMotion($player->getDirectionVector()->add(0, 0.2)->multiply(1.2));
                            Effects::spawnEffect(Effects::LEAP_BOOST, $player);
                            $player->resetFallDistance();
                            break;
                        }

                        $player->sendMessage(Lang::getKitsPrefix() . Lang::getMessage("kits-delay"));
                        break;
                    case Item::EMERALD:
                        if(KitUseTimer::canUseKit($player)) {
                            KitUseTimer::addToQueue($player);

                            $player->getLevel()->broadcastLevelSoundEvent($player, LevelSoundEventPacket::SOUND_THROW, 0, EntityIds::PLAYER);
                            $egg = new EggBait($this->level, EggBait::createBaseNBT($player->add(0, $player->getEyeHeight()), $player->getDirectionVector()->multiply(1.5), $player->getYaw(), $player->getPitch()), $player);
                            $egg->spawnToAll();
                            break;
                        }

                        $player->sendMessage(Lang::getKitsPrefix() . Lang::getMessage("kits-delay"));
                        break;
                    case Item::BED:
                        $this->disconnectPlayer($player);
                        break;

                }
                break;
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) {
        if(isset($this->players[$event->getPlayer()->getName()]) || isset($this->spectators[$event->getPlayer()->getName()])) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param BlockPlaceEvent $event
     */
    public function onPlace(BlockPlaceEvent $event) {
        if(isset($this->players[$event->getPlayer()->getName()]) || isset($this->spectators[$event->getPlayer()->getName()])) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param CraftItemEvent $event
     */
    public function onCraft(CraftItemEvent $event) {
        if(isset($this->players[$event->getPlayer()->getName()]) || isset($this->spectators[$event->getPlayer()->getName()])) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param PlayerDropItemEvent $event
     */
    public function onDrop(PlayerDropItemEvent $event) {
        if(isset($this->players[$event->getPlayer()->getName()]) || isset($this->spectators[$event->getPlayer()->getName()])) {
            $event->setCancelled(true);
        }
    }

    /**
     * @param InventoryPickupItemEvent $event
     */
    public function onPickup(InventoryPickupItemEvent $event) {
        foreach ($event->getInventory()->getViewers() as $viewer) {
            if(isset($this->players[$viewer->getName()]) || isset($this->spectators[$viewer->getName()])) {
                $event->setCancelled(true);
                $event->getItem()->flagForDespawn();
                break;
            }
        }
    }

    /**
     * @param PlayerExhaustEvent $event
     */
    public function onExhaust(PlayerExhaustEvent $event) {
        if(isset($this->players[$event->getPlayer()->getName()]) || isset($this->spectators[$event->getPlayer()->getName()])) {
            $event->getPlayer()->setFood(20); // Event->setCancelled doesn't work ._.
//            $event->setCancelled(true);
        }
    }

    public function teleportPlayers() {
        foreach ($this->players as $player) {
            $player->teleport(Position::fromObject($this->data["spawns"][array_rand($this->data["spawns"], 1)], $this->level));
            $player->setImmobile(true);
            $player->setGamemode($player::ADVENTURE);
        }

        $this->broadcastMessage(Lang::getGamePrefix() . Lang::getMessage("spawn-start"));
    }

    public function startGame() {
        foreach ($this->players as $player) {
            $player->addTitle(Lang::getMessage("started"), " ");
            $player->setGamemode($player::ADVENTURE);

            $player->getInventory()->clearAll();
            $player->getArmorInventory()->clearAll();
            $player->getCursorInventory()->clearAll();

            $this->plugin->kitManager->equipPlayer($player);
        }

        if(isset($this->dragonTargetManager)) {
            unset($this->dragonTargetManager);
        }
        $this->dragonTargetManager = new DragonTargetManager($this, $this->data["blocks"], Math::calculateCenterPosition($this->data["corner1"], $this->data["corner2"]));

        $this->scheduler->phase++;
    }

    public function endGame() {
        $winner = null;
        foreach ($this->players as $player) {
            $winner = $player;
        }

        if($winner === null) {
            $this->broadcastMessage(Lang::getPrefix() . "§cThere is no winner."); // shouldn't appear
            $this->scheduler->phase++;
            return;
        }

        $winner->addTitle(Lang::getMessage("congratulation"), Lang::getMessage("congratulation-subtitle"));
        $this->plugin->getServer()->broadcastMessage(Lang::getPrefix() . Lang::getMessage("win", [$player->getName(), $this->level->getName()]));
        $this->scheduler->phase++;
    }

    public function checkEnd() {
        if(count($this->players) > 1) {
            return;
        }

        $this->endGame();
    }

    /**
     * @param string $message
     */
    public function broadcastMessage(string $message): void {
        $players = $this->players + $this->spectators;

        foreach ($players as $player) {
            $player->sendMessage($message);
        }
    }

    /**
     * @param string $message
     */
    public function broadcastTip(string $message): void {
        $players = $this->players + $this->spectators;

        foreach ($players as $player) {
            $player->sendTip($message);
        }
    }

    public function reloadArena() {
        $this->level = $this->plugin->getServer()->getLevelByName($this->data["level"]);

        $this->scheduler->resetTimer();
    }
}