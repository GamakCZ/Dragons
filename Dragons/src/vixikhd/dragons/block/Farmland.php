<?php

declare(strict_types=1);

namespace vixikhd\dragons\block;

use vixikhd\dragons\Dragons;

/**
 * Class Farmland
 * @package vixikhd\dragons\block
 */
class Farmland extends \pocketmine\block\Farmland {
    /**
     * @return bool
     */
    protected function canHydrate(): bool {
        $arenas = array_map(function ($arena) {
        /** @var Arena $arena */
			return $arena->level === null ? "Unknown" : $arena->level->getName();
        }, Dragons::getInstance()->arenas);
        if (in_array($this->getLevel()->getName(),$arenas)) {
			return true;
		}
		else {
			return parent::canHydrate();
		}
    }
}