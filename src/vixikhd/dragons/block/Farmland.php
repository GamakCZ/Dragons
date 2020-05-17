<?php

declare(strict_types=1);

namespace vixikhd\dragons\block;

/**
 * Class Farmland
 * @package vixikhd\dragons\block
 */
class Farmland extends \pocketmine\block\Farmland {

    /**
     * @return bool
     */
    protected function canHydrate(): bool {
        return true;
    }
}