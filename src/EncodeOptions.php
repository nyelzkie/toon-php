<?php
namespace Toon;

class EncodeOptions {
    public function __construct(
        public ?int $indent = null,
        public ?string $delimiter = null,
        public ?string $keyFolding = null,
        public ?int $flattenDepth = null
    ) {}
}
