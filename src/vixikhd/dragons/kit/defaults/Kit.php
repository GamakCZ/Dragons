<?php

declare(strict_types=1);

namespace vixikhd\dragons\kit\defaults;

use pocketmine\Player;

/**
 * Interface Kit
 * @package vixikhd\dragons\kit\defaults
 */
interface Kit {

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @param Player $player
     */
    public function sendKitContents(Player $player): void;

    /**
     * @return string
     */
    public function getDescription(): string;
}