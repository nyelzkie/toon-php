<?php
namespace Toon;

class DecodeOptions {
    public function __construct(
        public ?int $indent = null,
        public ?bool $strict = null,
        public ?string $expandPaths = null
    ) {}
}

