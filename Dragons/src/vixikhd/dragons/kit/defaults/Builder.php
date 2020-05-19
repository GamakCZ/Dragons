<?php

declare(strict_types=1);

namespace vixikhd\dragons\kit\defaults;

use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\Item;
use pocketmine\item\ItemIds;
use pocketmine\Player;

/**
 * Class Builder
 * @package vixikhd\dragons\kit\defaults
 */
class Builder implements Kit {

    /**
     * @return string
     */
    public function getName(): string {
        return "Builder";
    }

    /**
     * @param Player $player
     */
    public function sendKitContents(Player $player): void {
        $player->getInventory()->setItem(0, Item::get(ItemIds::BONE)->setCustomName("§eBridge builder\n§7[Use]"));

        $boots = Item::get(ItemIds::DIAMOND_BOOTS);
        $boots->addEnchantment(new EnchantmentInstance(Enchantment::getEnchantment(Enchantment::FEATHER_FALLING), 4));
        $player->getArmorInventory()->setBoots($boots);
    }

    /**
     * @return string
     */
    public function getDescription(): string {
        return "Contains bone for building";
    }
}