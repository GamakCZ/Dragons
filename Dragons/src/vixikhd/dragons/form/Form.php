<?php

/**
 * - My form api took from BPCore
 * - I think it's also part of SkyWars PRO
 * - It's also updated version of MultiWorld's form api
 */

declare(strict_types=1);

namespace vixikhd\dragons\form;

use pocketmine\Player;

/**
 * Class Form
 * @package vixikhd\dragons\form
 */
abstract class Form implements \pocketmine\form\Form {

    /** @var callable $callable */
    private $callable; // func(Player, data)

    /** @var callable $advancedCallable */
    private $advancedCallable; // func(Player, data, Form)

    /** @var array $data */
    public $data;

    /** @var mixed $customData */
    public $customData;

    /**
     * @param mixed $customData
     */
    public function setCustomData($customData): void {
        $this->customData = $customData;
    }

    /**
     * @return mixed
     */
    public function getCustomData() {
        return $this->customData;
    }

    /**
     * @param callable $callable
     */
    public function setCallable(callable $callable): void {
        $this->callable = $callable;
    }

    /**
     * @param callable $advancedCallable
     */
    public function setAdvancedCallable(callable $advancedCallable): void {
        $this->advancedCallable = $advancedCallable;
    }

    /**
     * @param Player $player
     * @param array|int|null $data
     */
    public function handleResponse(Player $player, $data): void {
        if(is_callable($this->callable)) {
            $func = $this->callable;
            $func($player, $data);
        }

        if(is_callable($this->advancedCallable)) {
            $func = $this->advancedCallable;
            $func($player, $data, $this);
        }
    }

    /**
     * @return mixed
     */
    public function jsonSerialize() {
        return $this->data;
    }
}