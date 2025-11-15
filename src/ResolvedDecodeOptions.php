<?php
namespace Toon;

class ResolvedDecodeOptions {
    public function __construct(
        public int $indent,
        public bool $strict,
        public string $expandPaths
    ) {}
}
