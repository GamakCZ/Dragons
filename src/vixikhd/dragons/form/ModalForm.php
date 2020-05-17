<?php

declare(strict_types=1);

namespace vixikhd\dragons\form;

/**
 * Class ModalForm
 * @package vixikhd\bpcore\form
 */
class ModalForm extends Form {

    /**
     * ModalForm constructor.
     * @param string $title
     * @param string $content
     */
    public function __construct(string $title = "TITLE", string $content = "Content") {
        $this->data["type"] = "modal";
        $this->data["title"] = $title;
        $this->data["content"] = $content;
    }

    /**
     * @param string $text
     */
    public function setFirstButton(string $text) : void {
        $this->data["button1"] = $text;
    }

    /**
     * @param string $text
     */
    public function setSecondButton(string $text) : void {
        $this->data["button2"] = $text;
    }
}