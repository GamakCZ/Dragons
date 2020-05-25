<?php

declare(strict_types=1);

namespace vixikhd\dragons;

use pocketmine\block\Block;
use pocketmine\block\BlockFactory;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\LeavesDecayEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\level\Position;
use pocketmine\math\Vector3;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\tile\Sign;
use vixikhd\dragons\arena\Arena;
use vixikhd\dragons\block\Farmland;
use vixikhd\dragons\entity\EggBait;
use vixikhd\dragons\entity\EnderDragon;
use vixikhd\dragons\entity\ThrownBlock;
use vixikhd\dragons\kit\KitManager;
use vixikhd\dragons\task\KitUseTimer;
use vixikhd\dragons\utils\ServerManager;

/**
 * Class Dragons
 * @package vixikhd\dragons
 */
class Dragons extends PluginBase implements Listener {

    /** @var Dragons $instance */
    private static $instance;

    /** @var Arena[] $arenas */
    public $arenas = [];

    /** @var array $setup */
    public $setup = [];
    /** @var array $spawnsSetup */
    public $spawnsSetup = [];

    /** @var array $config */
    public $config = [];

    /** @var EmptyArenaChooser $emptyArenaChooser */
    public $emptyArenaChooser;
    /** @var KitManager $kitManager */
    public $kitManager;
    /** @var KitUseTimer $kitTimer */
    public $kitTimer;
    /** @var Lang $lang */
    public $lang;

    public function onEnable() {
        Entity::registerEntity(EnderDragon::class);
        Entity::registerEntity(EggBait::class);
        Entity::registerEntity(ThrownBlock::class);

        self::$instance = $this;

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->initDataFiles();

        $this->lang = new Lang($this);
        $this->emptyArenaChooser = new EmptyArenaChooser($this);
        $this->kitManager = new KitManager($this);
        $this->kitTimer = new KitUseTimer($this);

        if($this->config["cancel-level-events"]) {
            BlockFactory::registerBlock(new Farmland(), true);
        }

        $this->getScheduler()->scheduleRepeatingTask($this->kitTimer, 2);

        $this->loadArenas();
    }

    public function onDisable() {
        $this->saveArenas();
    }

    public function initDataFiles() {
        if(!is_dir($this->getDataFolder())) {
            mkdir($this->getDataFolder());
        }
        if(!is_dir($this->getDataFolder() . "arenas")) {
            mkdir($this->getDataFolder() . "arenas");
        }
        if(!is_dir($this->getDataFolder() . "saves")) {
            mkdir($this->getDataFolder() . "saves");
        }

        $this->saveResource("/config.yml", false);
        $this->config = (array)yaml_parse_file($this->getDataFolder() . "config.yml");
    }

    public function loadArenas() {
        foreach (glob($this->getDataFolder() . "arenas/*.yml") as $arenaFile) {
            $data = (array)yaml_parse_file($arenaFile);
            foreach ($data as $key => $value) {
                if(is_string($value) && substr($value, 0, $length = strlen("serialized=")) == "serialized=") {
                    $data[$key] = unserialize(substr($value, $length));
                }
            }

            $this->arenas[] = new Arena($this, $data);
        }
    }

    public function saveArenas() {
        foreach ($this->arenas as $arena) {
            $data = $arena->data;
            foreach ($data as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    $data[$key] = "serialized=" . serialize($value);
                }
            }

            yaml_emit_file($this->getDataFolder() . "arenas/" . $data["level"] . ".yml", $data);
        }
    }

    /**
     * @api
     *
     * @param Player $player
     * @return Arena|null
     */
    public function getArenaByPlayer(Player $player): ?Arena {
        foreach ($this->arenas as $arena) {
            if(isset($arena->players[$player->getName()]) || isset($arena->spectators[$player->getName()])) {
                return $arena;
            }
        }

        return null;
    }

    /**
     * @param CommandSender $sender
     * @param Command $command
     * @param string $label
     * @param array $args
     *
     * @return bool
     */
    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if(!$sender instanceof Player) {
            return false;
        }

        if(!$sender->isOp()) {
            return false;
        }

        if($command->getName() !== "dragons") {
            return false;
        }

        if(!isset($args[0])) {
            $sender->sendMessage("§cUsage: §7/dg help");
            return false;
        }

        switch ($args[0]) {
            case "help":
                $sender->sendMessage("§7> Dragons help list\n".
                    "§a/dg help : Displays help\n".
                    "§a/dg create : Creates an arena\n".
                    "§a/dg list : Displays list of available arenas\n".
                    "§a/dg remove : Removes an arena\n".
                    "§a/dg start : Force starts a game");
                break;
            case "create":
                $sender->sendMessage("§a> Arena created!");
                $this->setup[$sender->getName()] = [];
                $sender->sendMessage("§6> Go to lobby level and write something to chat!");
                break;
            case "list":
                $arenas = array_map(function ($arena) {
                    /** @var Arena $arena */
                    return $arena->level === null ? "Unknown" : $arena->level->getName();
                }, $this->arenas);

                $lines = [];
                foreach ($arenas as $index => $levelName) {
                    $lines[] = "§7Arena num. §l$index §r§8(Level $levelName)";
                }

                $sender->sendMessage("§a> Available arenas:\n" . implode("\n", $lines));
                break;
            case "remove":
                if(!isset($args[1]) || !is_numeric($args[1])) {
                    $sender->sendMessage("§cUsage: §7/dg remove <arenaNum.>");
                    break;
                }

                $arenaNum = (int)$args[1];
                if(!isset($this->arenas[$arenaNum])) {
                    $sender->sendMessage("§c> Arena with number $arenaNum was not found.");
                    break;
                }

                if(!isset($args[2]) || $args[2] != "confirm") {
                    $sender->sendMessage("§6> Do you really want to remove that arena?");
                    $sender->sendMessage("§6> Type §l/dg remove {$arenaNum} confirm§r§6 to confirm.");
                    break;
                }

                $this->getScheduler()->cancelTask($this->arenas[$arenaNum]->scheduler->getTaskId());
                unlink($this->getDataFolder() . "arenas/{$this->arenas[$arenaNum]->data["level"]}.yml");
                unset($this->arenas[$arenaNum]);
                $sender->sendMessage("§a> Arena successfully removed!");
                break;
            case "start":
                $arena = $this->getArenaByPlayer($sender);
                if($arena === null) {
                    $sender->sendMessage("§c> Join the arena first!");
                    break;
                }

                $arena->scheduler->startTime = 10;
                $arena->scheduler->forceStart = true;
                $sender->sendMessage("§a> Starting the game...");

        }
        return false;
    }

    /**
     * TODO - change to PlayerLogEvent on BedrockPlay
     *
     * @param PlayerJoinEvent $event
     */
    public function onJoin(PlayerJoinEvent $event) {
        $event->setJoinMessage("");
        $player = $event->getPlayer();

        if($this->config["waterdog"]["enabled"]) {
            $arena = $this->emptyArenaChooser->getRandomArena();
            if(is_null($arena)) {
                $player->sendMessage("§c> All the arenas are full!");
                ServerManager::transferPlayer($player, "Lobby-1");
                return;
            }

            $arena->joinToArena($player);
        }
    }

    /**
     * @param PlayerQuitEvent $event
     */
    public function onQuit(PlayerQuitEvent $event) {
        $event->setQuitMessage("");
    }

    /**
     * @param PlayerChatEvent $event
     */
    public function onMessage(PlayerChatEvent $event) {
        $player = $event->getPlayer();
        $args = explode(" ", $event->getMessage());

        if(!isset($args[0])) {
            return;
        }

        if(!isset($this->setup[$player->getName()])) {
            return;
        }

        $event->setCancelled(true);
        switch (count($this->setup[$player->getName()])) {
            case 0:
                $player->sendMessage("§a> Lobby level updated to {$player->getLevel()->getName()}!");
                $this->setup[$player->getName()]["lobby"] = $player->getLevel()->getName();
                $player->sendMessage("§6> Go to first corner of the arena and write something to chat!");
                break;
            case 1:
                $player->sendMessage("§a> First corner set to {$player->asVector3()->__toString()} in level {$player->getLevel()->getName()}");
                $this->setup[$player->getName()]["corner1"] = Position::fromObject($player->ceil(), $player->getLevel());
                $player->sendMessage("§6> Go to second corner of the arena and write something to chat!");
                break;
            case 2:
                /** @var Position $firstPos */
                $firstPos = $this->setup[$player->getName()]["corner1"];
                if($firstPos->getLevel()->getId() !== $player->getLevel()->getId()) {
                    $player->sendMessage("§c> You are in wrong level!");
                    break;
                }

                $level = $firstPos->getLevel();
                $firstPos = $firstPos->ceil();

                $player->sendMessage("§6> Importing blocks...");
                $secondPos = $player->ceil();
                $blocks = [];

                for($x = min($firstPos->getX(), $secondPos->getX()); $x <= max($firstPos->getX(), $secondPos->getX()); $x++) {
                    for($y = min($firstPos->getY(), $secondPos->getY()); $y <= max($firstPos->getY(), $secondPos->getY()); $y++) {
                        for($z = min($firstPos->getZ(), $secondPos->getZ()); $z <= max($firstPos->getZ(), $secondPos->getZ()); $z++) {
                            if($level->getBlockIdAt($x, $y, $z) !== Block::AIR) {
                                $blocks["$x:$y:$z"] = new Vector3($x, $y, $z);
                            }
                        }
                    }
                }

                $player->sendMessage("§a> First corner set to {$player->asVector3()->__toString()} in level {$level->getName()}");
                $this->setup[$player->getName()]["corner1"] = $firstPos->ceil();
                $this->setup[$player->getName()]["corner2"] = $secondPos->ceil();
                $this->setup[$player->getName()]["blocks"] = $blocks;
                $this->setup[$player->getName()]["level"] = $level->getFolderName();
                $player->sendMessage("§6> Break blocks for spawns, then write something to chat to save.");
                $this->spawnsSetup[$player->getName()] = [];
                break;
            case 5:
                if(empty($this->spawnsSetup[$player->getName()])) {
                    $player->sendMessage("§c> Select at least 1 spawn!");
                    break;
                }

                $this->setup[$player->getName()]["spawns"] = $this->spawnsSetup[$player->getName()];
                $player->sendMessage("§6> Write max count of dragons in the arena to chat!");
                break;
            case 6:
                if(!is_numeric($args[0])) {
                    $player->sendMessage("§c> Specify number!");
                    break;
                }

                $player->sendMessage("§a> Max dragons count saved!");
                $this->setup[$player->getName()]["maxDragons"] = (int)$args[0];
                $player->sendMessage("§6> Specify max slots for the game in the chat");
                break;
            case 7:
                if(!is_numeric($args[0])) {
                    $player->sendMessage("§c> Specify number!");
                    break;
                }

                $player->sendMessage("§a> Slots updated!");
                $this->setup[$player->getName()]["slots"] = (int)$args[0];
                $player->sendMessage("§6> Break sign to update joinsign!");
                break;
        }
    }

    /**
     * @param BlockBreakEvent $event
     */
    public function onBreak(BlockBreakEvent $event) {
        $player = $event->getPlayer();

        if(!isset($this->setup[$player->getName()])) {
            return;
        }

        switch (count($this->setup[$player->getName()])) {
            case 5:
                if(!isset($this->spawnsSetup[$player->getName()])) {
                    break;
                }
                $this->spawnsSetup[$player->getName()][] = $event->getBlock()->add(0, 1);
                $player->sendMessage("§a> Added spawn position at {$event->getBlock()->add(0,1)->__toString()}");
                $event->setCancelled(true);
                break;

            case 8:
                if(!$event->getBlock()->getLevel()->getTile($event->getBlock()) instanceof Sign) {
                    $player->sendMessage("§c> Wrong block");
                    $event->setCancelled(true);
                    break;
                }

                $this->setup[$player->getName()]["joinSignLevel"] = $event->getBlock()->getLevel()->getName();
                $this->setup[$player->getName()]["joinSignPos"] = $event->getBlock()->asVector3();

                $arena = new Arena($this, $this->setup[$player->getName()]);

                $this->arenas[] = $arena;
				$this->saveArenas();
                $player->sendMessage("§a> Arena saved!");
                $event->setCancelled(true);

                unset($this->setup[$player->getName()]);
                break;
        }
    }

    /**
     * @param LeavesDecayEvent $event
     */
    public function onDecay(LeavesDecayEvent $event) {
        if($this->config["cancel-level-events"]) {
            $event->setCancelled(true);
        }
    }

    /**
     * @return Dragons
     */
    public static function getInstance(): Dragons {
        return self::$instance;
    }
}