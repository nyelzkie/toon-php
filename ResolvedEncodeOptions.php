<?php
namespace Toon;

class ResolvedEncodeOptions {
    public function __construct(
        public int $indent,
        public string $delimiter,
        public string $keyFolding,
        public int $flattenDepth
    ) {}
}
