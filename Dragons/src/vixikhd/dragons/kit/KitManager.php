<?php

declare(strict_types=1);

namespace vixikhd\dragons\kit;

use pocketmine\Player;
use vixikhd\dragons\Dragons;
use vixikhd\dragons\form\SimpleForm;
use vixikhd\dragons\kit\defaults\Baiter;
use vixikhd\dragons\kit\defaults\Builder;
use vixikhd\dragons\kit\defaults\Kit;
use vixikhd\dragons\kit\defaults\Leaper;
use vixikhd\dragons\kit\defaults\Sniper;
use vixikhd\dragons\Lang;

/**
 * Class KitManager
 * @package vixikhd\dragons\kit
 */
class KitManager {

    /** @var Dragons $plugin */
    public $plugin;
    /** @var Kit[] $kits */
    public $kits = [];

    /** @var Kit[] $playerKits */
    public $playerKits = [];

    /**
     * KitManager constructor.
     * @param Dragons $plugin
     */
    public function __construct(Dragons $plugin) {
        $this->plugin = $plugin;
        $this->registerKits();
    }

    public function registerKits() {
        $this->kits[] = new Leaper(); // default kit

        $this->kits[] = new Baiter();
        $this->kits[] = new Builder();
        $this->kits[] = new Sniper();
    }

    public function showKitsForm(Player $player) {
        $form = new SimpleForm(Lang::getMessage("kit-form-title"), Lang::getMessage("kit-form-subtitle"));
        foreach ($this->kits as $kit) {
            $form->addButton("§a" . $kit->getName() . "\n" ."§7" . $kit->getDescription());
        }

        $form->setCallable([$this, "handleKitSelect"]);

        $player->sendForm($form);
    }

    /**
     * @param Player $player
     * @param mixed $data
     */
    public function handleKitSelect(Player $player, $data) {
        if(is_null($data) || !is_int($data)) {
            return;
        }

        $this->playerKits[$player->getName()] = $this->kits[$data];
        $player->sendMessage(Lang::getKitsPrefix() . Lang::getMessage("kit-selected", [$this->kits[$data]->getName()]));
    }

    /**
     * @param Player $player
     * @param bool $addDefaultKit
     */
    public function equipPlayer(Player $player, bool $addDefaultKit = true) {
        $kit = $this->playerKits[$player->getName()] ?? null;
        if($addDefaultKit && $kit === null) {
            $kit = $this->kits[0];
        }

        if($kit === null) {
            return;
        }

        $kit->sendKitContents($player);
    }
}