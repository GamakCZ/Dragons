<?php

declare(strict_types=1);

namespace vixikhd\dragons\form;

/**
 * Class SimpleForm
 * @package vixikhd\dragons\form
 */
class SimpleForm extends Form {

    /**
     * SimpleForm constructor.
     * @param string $title
     * @param string $content
     */
    public function __construct(string $title = "TITLE", string $content = "Content") {
        $this->data["type"] = "form";
        $this->data["title"] = $title;
        $this->data["content"] = $content;
    }

    /**
     * @param string $text
     */
    public function addButton(string $text) {
        $this->data["buttons"][] = ["text" => $text];
    }
}