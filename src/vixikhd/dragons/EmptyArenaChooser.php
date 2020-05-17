<?php

/**
 * This library was took from my SkyWars Pro plugin, can be found in 1vs1 plugin too (https://github.com/GamakCZ/1vs1)
 */

declare(strict_types=1);

namespace vixikhd\dragons;

use vixikhd\dragons\arena\Arena;

/**
 * Class EmptyArenaChooser
 * @package vixikhd\thebridge
 */
class EmptyArenaChooser {

    /** @var Dragons $plugin */
    public $plugin;

    /**
     * EmptyArenaChooser constructor.
     * @param Dragons $plugin
     */
    public function __construct(Dragons $plugin) {
        $this->plugin = $plugin;
    }



    /**
     * @return null|Arena
     *
     * 1. Choose all arenas
     * 2. Remove in-game arenas
     * 3. Sort arenas by players
     * 4. Sort arenas by rand()
     */
    public function getRandomArena(): ?Arena {
        // searching by players

        //1.

        /** @var Arena[] $availableArenas */
        $availableArenas = [];
        foreach ($this->plugin->arenas as $index => $arena) {
            $availableArenas[$index] = $arena;
        }

        //2.
        foreach ($availableArenas as $index => $arena) {
            if($arena->scheduler->phase !== 0) {
                unset($availableArenas[$index]);
            }
        }

        //3.
        $arenasByPlayers = [];
        foreach ($availableArenas as $index => $arena) {
            $arenasByPlayers[$index] = count($arena->players);
        }

        arsort($arenasByPlayers);
        $top = -1;
        $availableArenas = [];

        foreach ($arenasByPlayers as $index => $players) {
            if($top == -1) {
                $top = $players;
                $availableArenas[] = $index;
            }
            else {
                if($top == $players) {
                    $availableArenas[] = $index;
                }
            }
        }

        if(empty($availableArenas)) {
            return null;
        }

        return $this->plugin->arenas[$availableArenas[array_rand($availableArenas, 1)]];
    }
}