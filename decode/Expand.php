<?php
namespace Toon\Decode;

use Toon\Constants;
use Toon\Encode\Normalize;
use Toon\Shared\Validation;

class Expand {
    public const QUOTED_KEY_MARKER = 'quotedKey';

    public static function expandPathsSafe(mixed $value, bool $strict): mixed {
        if (is_array($value) && array_is_list($value)) {
            return array_map(fn($item) => self::expandPathsSafe($item, $strict), $value);
        }
        if (Normalize::isJsonObject($value)) {
            $expandedObject = [];
            $quotedKeys = $value[self::QUOTED_KEY_MARKER] ?? [];
            unset($value[self::QUOTED_KEY_MARKER]);
            foreach ($value as $key => $keyValue) {
                $isQuoted = in_array($key, $quotedKeys);
                if (str_contains($key, Constants::DOT) && !$isQuoted) {
                    $segments = explode(Constants::DOT, $key);
                    if (array_reduce($segments, fn($carry, $seg) => $carry && Validation::isIdentifierSegment($seg), true)) {
                        $expandedValue = self::expandPathsSafe($keyValue, $strict);
                        self::insertPathSafe($expandedObject, $segments, $expandedValue, $strict);
                        continue;
                    }
                }
                $expandedValue = self::expandPathsSafe($keyValue, $strict);
                if (array_key_exists($key, $expandedObject)) {
                    $conflictingValue = $expandedObject[$key];
                    if (self::canMerge($conflictingValue, $expandedValue)) {
                        self::mergeObjects($expandedObject[$key], $expandedValue, $strict);
                    } else {
                        if ($strict) {
                            throw new \TypeError("Path expansion conflict at key \"{$key}\": cannot merge " . gettype($conflictingValue) . ' with ' . gettype($expandedValue));
                        }
                        $expandedObject[$key] = $expandedValue;
                    }
                } else {
                    $expandedObject[$key] = $expandedValue;
                }
            }
            return $expandedObject;
        }
        return $value;
    }

    private static function insertPathSafe(array &$target, array $segments, mixed $value, bool $strict): void {
        $currentNode = &$target;
        for ($i = 0; $i < count($segments) - 1; $i++) {
            $currentSegment = $segments[$i];
            if (!array_key_exists($currentSegment, $currentNode)) {
                $currentNode[$currentSegment] = [];
            } elseif (Normalize::isJsonObject($currentNode[$currentSegment])) {
                // continue
            } else {
                if ($strict) {
                    throw new \TypeError("Path expansion conflict at segment \"{$currentSegment}\": expected object but found " . gettype($currentNode[$currentSegment]));
                }
                $currentNode[$currentSegment] = [];
            }
            $currentNode = &$currentNode[$currentSegment];
        }
        $lastSeg = $segments[count($segments) - 1];
        if (!array_key_exists($lastSeg, $currentNode)) {
            $currentNode[$lastSeg] = $value;
        } elseif (self::canMerge($currentNode[$lastSeg], $value)) {
            self::mergeObjects($currentNode[$lastSeg], $value, $strict);
        } else {
            if ($strict) {
                throw new \TypeError("Path expansion conflict at key \"{$lastSeg}\": cannot merge " . gettype($currentNode[$lastSeg]) . ' with ' . gettype($value));
            }
            $currentNode[$lastSeg] = $value;
        }
    }

    private static function mergeObjects(array &$target, array $source, bool $strict): void {
        foreach ($source as $key => $sourceValue) {
            if (!array_key_exists($key, $target)) {
                $target[$key] = $sourceValue;
            } elseif (self::canMerge($target[$key], $sourceValue)) {
                self::mergeObjects($target[$key], $sourceValue, $strict);
            } else {
                if ($strict) {
                    throw new \TypeError("Path expansion conflict at key \"{$key}\": cannot merge " . gettype($target[$key]) . ' with ' . gettype($sourceValue));
                }
                $target[$key] = $sourceValue;
            }
        }
    }

    private static function canMerge(mixed $a, mixed $b): bool {
        return Normalize::isJsonObject($a) && Normalize::isJsonObject($b);
    }
}
