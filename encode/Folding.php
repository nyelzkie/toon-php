<?php
namespace Toon\Encode;

use Toon\ResolvedEncodeOptions;
use Toon\Constants;
use Toon\Shared\Validation;

class Folding {
    public static function tryFoldKeyChain(string $key, mixed $value, array $siblings, ResolvedEncodeOptions $options, ?array $rootLiteralKeys = null, ?string $pathPrefix = null, ?int $flattenDepth = null): ?array {
        if ($options->keyFolding !== 'safe') {
            return null;
        }
        if (!Normalize::isJsonObject($value)) {
            return null;
        }
        $effectiveFlattenDepth = $flattenDepth ?? $options->flattenDepth;
        $collect = self::collectSingleKeyChain($key, $value, $effectiveFlattenDepth);
        $segments = $collect['segments'];
        $tail = $collect['tail'];
        $leafValue = $collect['leafValue'];
        if (count($segments) < 2) {
            return null;
        }
        if (!array_reduce($segments, fn($carry, $seg) => $carry && Validation::isIdentifierSegment($seg), true)) {
            return null;
        }
        $foldedKey = implode(Constants::DOT, $segments);
        $absolutePath = $pathPrefix ? $pathPrefix . Constants::DOT . $foldedKey : $foldedKey;
        if (in_array($foldedKey, $siblings)) {
            return null;
        }
        if ($rootLiteralKeys && in_array($absolutePath, $rootLiteralKeys)) {
            return null;
        }
        return [
            'foldedKey' => $foldedKey,
            'remainder' => $tail,
            'leafValue' => $leafValue,
            'segmentCount' => count($segments),
        ];
    }

    private static function collectSingleKeyChain(string $startKey, mixed $startValue, int $maxDepth): array {
        $segments = [$startKey];
        $currentValue = $startValue;
        while (count($segments) < $maxDepth) {
            if (!Normalize::isJsonObject($currentValue)) {
                break;
            }
            $keys = array_keys($currentValue);
            if (count($keys) !== 1) {
                break;
            }
            $nextKey = $keys[0];
            $nextValue = $currentValue[$nextKey];
            $segments[] = $nextKey;
            $currentValue = $nextValue;
        }
        $tail = (!Normalize::isJsonObject($currentValue) || Normalize::isEmptyObject($currentValue)) ? null : $currentValue;
        return ['segments' => $segments, 'tail' => $tail, 'leafValue' => $currentValue];
    }
}
