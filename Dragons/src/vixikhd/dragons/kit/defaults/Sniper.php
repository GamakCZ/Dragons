<?php

declare(strict_types=1);

namespace vixikhd\dragons\kit\defaults;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\Player;

/**
 * Class Sniper
 * @package vixikhd\dragons\kit\defaults
 */
class Sniper implements Kit {

    /**
     * @return string
     */
    public function getName(): string {
        return "Sniper";
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        return "Contains bow and arrows";
    }

    /**
     * @param Player $player
     */
    public function sendKitContents(Player $player): void {
        $player->getInventory()->setItem(0, Item::get(ItemIds::BOW)->setCustomName("§r§eBow"));
        $player->getInventory()->setItem(9, Item::get(ItemIds::ARROW, 0, 16));

        $boots = Item::get(ItemIds::CHAIN_BOOTS);
        $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::FEATHER_FALLING), 4));
        $player->getArmorInventory()->setBoots($boots);
    }
}