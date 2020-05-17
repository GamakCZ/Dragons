<?php

declare(strict_types=1);

namespace vixikhd\dragons\form;

/**
 * Class CustomForm
 * @package vixikhd\dragons\form
 */
class CustomForm extends Form {

    /**
     * CustomForm constructor.
     * @param string $title
     */
    public function __construct(string $title = "TITLE") {
        $this->data["type"] = "custom_form";
        $this->data["title"] = $title;
        $this->data["content"] = [];
    }

    /**
     * @param string $text
     */
    public function addInput(string $text) {
        $this->data["content"][] = ["type" => "input", "text" => $text];
    }

    /**
     * @param string $text
     */
    public function addLabel(string $text) {
        $this->data["content"][] = ["type" => "label", "text" => $text];
    }

    /**
     * @param string $text
     * @param bool|null $default
     */
    public function addToggle(string $text, ?bool $default = null) {
        if($default!== null) {
            $this->data["content"][] = ["type" => "toggle", "text" => $text, "default" => $default];
            return;
        }
        $this->data["content"][] = ["type" => "toggle", "text" => $text];
    }

    /**
     * @param string $text
     * @param array $options
     */
    public function addDropdown(string $text, array $options) {
        $this->data["content"][] = ["type" => "dropdown", "text" => $text, "options" => $options];
    }
}