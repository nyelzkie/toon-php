<?php
namespace Toon\Encode;

use Toon\Constants;

class LineWriter {
    private array $lines = [];
    private string $indentationString;

    public function __construct(int $indentSize) {
        $this->indentationString = str_repeat(Constants::SPACE, $indentSize);
    }

    public function push(int $depth, string $content): void {
        $indent = str_repeat($this->indentationString, $depth);
        $this->lines[] = $indent . $content;
    }

    public function pushListItem(int $depth, string $content): void {
        $this->push($depth, Constants::LIST_ITEM_PREFIX . $content);
    }

    public function __toString(): string {
        return implode(Constants::NEWLINE, $this->lines);
    }
}
