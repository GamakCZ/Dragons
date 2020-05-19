<?php

declare(strict_types=1);

namespace vixikhd\dragons;

/**
 * Class Lang
 * @package vixikhd\dragons
 */
class Lang {

    /** @var Dragons $plugin */
    public $plugin;
    /** @var array $messages */
    private static $messages = [];

    /**
     * Lang constructor.
     * @param Dragons $plugin
     */
    public function __construct(Dragons $plugin) {
        $this->plugin = $plugin;

        self::$messages = $this->plugin->config["messages"];
    }

    /**
     * @param string $index
     * @param array $params
     * @return string
     */
    public static function getMessage(string $index, array $params = []): string {
        $message = self::$messages[$index] ?? "unknown";
        foreach ($params as $i => $param) {
            $message = str_replace("{%$i}", $param, $message);
        }

        return $message;
    }

    /**
     * @return string
     */
    public static function getPrefix(): string {
        return self::$messages["prefix"] . " ";
    }

    /**
     * @return string
     */
    public static function getGamePrefix(): string {
        return self::$messages["prefix-game"] . " ";
    }

    /**
     * @return string
     */
    public static function getKitsPrefix(): string {
        return self::$messages["prefix-kits"] . " ";
    }
}