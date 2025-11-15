<?php
namespace Toon\Encode;

use Toon\ResolvedEncodeOptions;
use Toon\Constants;

class Encoder {
    public static function encodeValue(mixed $value, ResolvedEncodeOptions $options): string {
        if (Normalize::isJsonPrimitive($value)) {
            return Primitives::encodePrimitive($value, $options->delimiter);
        }
        $writer = new LineWriter($options->indent);
        if (Normalize::isJsonArray($value)) {
            self::encodeArray(null, $value, $writer, 0, $options);
        } elseif (Normalize::isJsonObject($value)) {
            self::encodeObject($value, $writer, 0, $options);
        }
        return (string) $writer;
    }

    public static function encodeObject(array $value, LineWriter $writer, int $depth, ResolvedEncodeOptions $options, ?array &$rootLiteralKeys = null, ?string $pathPrefix = null, ?int $remainingDepth = null): void {
        $keys = array_keys($value);
        if ($depth === 0 && $rootLiteralKeys === null) {
            $rootLiteralKeys = array_filter($keys, fn($k) => str_contains($k, Constants::DOT));
        }
        $effectiveFlattenDepth = $remainingDepth ?? $options->flattenDepth;
        foreach ($value as $key => $val) {
            self::encodeKeyValuePair($key, $val, $writer, $depth, $options, $keys, $rootLiteralKeys, $pathPrefix, $effectiveFlattenDepth);
        }
    }

    public static function encodeKeyValuePair(string $key, mixed $val, LineWriter $writer, int $depth, ResolvedEncodeOptions $options, ?array $siblings = null, ?array $rootLiteralKeys = null, ?string $pathPrefix = null, ?int $flattenDepth = null): void {
        $currentPath = $pathPrefix ? $pathPrefix . Constants::DOT . $key : $key;
        $effectiveFlattenDepth = $flattenDepth ?? $options->flattenDepth;
        if ($options->keyFolding === 'safe' && $siblings !== null) {
            $foldResult = Folding::tryFoldKeyChain($key, $val, $siblings, $options, $rootLiteralKeys, $pathPrefix, $effectiveFlattenDepth);
            if ($foldResult) {
                $foldedKey = $foldResult['foldedKey'];
                $remainder = $foldResult['remainder'];
                $leafValue = $foldResult['leafValue'];
                $segmentCount = $foldResult['segmentCount'];
                $encodedFoldedKey = Primitives::encodeKey($foldedKey);
                if ($remainder === null) {
                    if (Normalize::isJsonPrimitive($leafValue)) {
                        $writer->push($depth, $encodedFoldedKey . Constants::COLON . ' ' . Primitives::encodePrimitive($leafValue, $options->delimiter));
                        return;
                    } elseif (Normalize::isJsonArray($leafValue)) {
                        self::encodeArray($foldedKey, $leafValue, $writer, $depth, $options);
                        return;
                    } elseif (Normalize::isJsonObject($leafValue) && Normalize::isEmptyObject($leafValue)) {
                        $writer->push($depth, $encodedFoldedKey . Constants::COLON);
                        return;
                    }
                }
                if (Normalize::isJsonObject($remainder)) {
                    $writer->push($depth, $encodedFoldedKey . Constants::COLON);
                    $remainingDepth = $effectiveFlattenDepth - $segmentCount;
                    $foldedPath = $pathPrefix ? $pathPrefix . Constants::DOT . $foldedKey : $foldedKey;
                    self::encodeObject($remainder, $writer, $depth + 1, $options, $rootLiteralKeys, $foldedPath, $remainingDepth);
                    return;
                }
            }
        }
        $encodedKey = Primitives::encodeKey($key);
        if (Normalize::isJsonPrimitive($val)) {
            $writer->push($depth, $encodedKey . Constants::COLON . ' ' . Primitives::encodePrimitive($val, $options->delimiter));
        } elseif (Normalize::isJsonArray($val)) {
            self::encodeArray($key, $val, $writer, $depth, $options);
        } elseif (Normalize::isJsonObject($val)) {
            $writer->push($depth, $encodedKey . Constants::COLON);
            if (!Normalize::isEmptyObject($val)) {
                self::encodeObject($val, $writer, $depth + 1, $options, $rootLiteralKeys, $currentPath, $effectiveFlattenDepth);
            }
        }
    }

    public static function encodeArray(?string $key, array $value, LineWriter $writer, int $depth, ResolvedEncodeOptions $options): void {
        $length = count($value);
        if ($length === 0) {
            $header = Primitives::formatHeader($length, $key, null, $options->delimiter);
            $writer->push($depth, $header);
            return;
        }
        if (Normalize::isArrayOfPrimitives($value)) {
            $arrayLine = self::encodeInlineArrayLine($value, $options->delimiter, $key);
            $writer->push($depth, $arrayLine);
            return;
        }
        if (Normalize::isArrayOfArrays($value)) {
            $allPrimitiveArrays = array_reduce($value, fn($carry, $arr) => $carry && Normalize::isArrayOfPrimitives($arr), true);
            if ($allPrimitiveArrays) {
                self::encodeArrayOfArraysAsListItems($key, $value, $writer, $depth, $options);
                return;
            }
        }
        if (Normalize::isArrayOfObjects($value)) {
            $header = self::extractTabularHeader($value);
            if ($header) {
                self::encodeArrayOfObjectsAsTabular($key, $value, $header, $writer, $depth, $options);
            } else {
                self::encodeMixedArrayAsListItems($key, $value, $writer, $depth, $options);
            }
            return;
        }
        self::encodeMixedArrayAsListItems($key, $value, $writer, $depth, $options);
    }

    private static function encodeArrayOfArraysAsListItems(?string $prefix, array $values, LineWriter $writer, int $depth, ResolvedEncodeOptions $options): void {
        $header = Primitives::formatHeader(count($values), $prefix, null, $options->delimiter);
        $writer->push($depth, $header);
        foreach ($values as $arr) {
            if (Normalize::isArrayOfPrimitives($arr)) {
                $arrayLine = self::encodeInlineArrayLine($arr, $options->delimiter);
                $writer->pushListItem($depth + 1, $arrayLine);
            }
        }
    }

    private static function encodeInlineArrayLine(array $values, string $delimiter, ?string $prefix = null): string {
        $header = Primitives::formatHeader(count($values), $prefix, null, $delimiter);
        $joinedValue = Primitives::encodeAndJoinPrimitives($values, $delimiter);
        if (count($values) === 0) {
            return $header;
        }
        return $header . ' ' . $joinedValue;
    }

    private static function encodeArrayOfObjectsAsTabular(?string $prefix, array $rows, array $header, LineWriter $writer, int $depth, ResolvedEncodeOptions $options): void {
        $formattedHeader = Primitives::formatHeader(count($rows), $prefix, $header, $options->delimiter);
        $writer->push($depth, $formattedHeader);
        self::writeTabularRows($rows, $header, $writer, $depth + 1, $options);
    }

    private static function extractTabularHeader(array $rows): ?array {
        if (empty($rows)) {
            return null;
        }
        $firstRow = $rows[0];
        $firstKeys = array_keys($firstRow);
        if (empty($firstKeys)) {
            return null;
        }
        if (self::isTabularArray($rows, $firstKeys)) {
            return $firstKeys;
        }
        return null;
    }

    private static function isTabularArray(array $rows, array $header): bool {
        foreach ($rows as $row) {
            $keys = array_keys($row);
            if (count($keys) !== count($header)) {
                return false;
            }
            foreach ($header as $key) {
                if (!array_key_exists($key, $row)) {
                    return false;
                }
                if (!Normalize::isJsonPrimitive($row[$key])) {
                    return false;
                }
            }
        }
        return true;
    }

    private static function writeTabularRows(array $rows, array $header, LineWriter $writer, int $depth, ResolvedEncodeOptions $options): void {
        foreach ($rows as $row) {
            $values = array_map(fn($key) => $row[$key], $header);
            $joinedValue = Primitives::encodeAndJoinPrimitives($values, $options->delimiter);
            $writer->push($depth, $joinedValue);
        }
    }

    private static function encodeMixedArrayAsListItems(?string $prefix, array $items, LineWriter $writer, int $depth, ResolvedEncodeOptions $options): void {
        $header = Primitives::formatHeader(count($items), $prefix, null, $options->delimiter);
        $writer->push($depth, $header);
        foreach ($items as $item) {
            self::encodeListItemValue($item, $writer, $depth + 1, $options);
        }
    }

    private static function encodeObjectAsListItem(array $obj, LineWriter $writer, int $depth, ResolvedEncodeOptions $options): void {
        if (Normalize::isEmptyObject($obj)) {
            $writer->push($depth, Constants::LIST_ITEM_MARKER);
            return;
        }
        $entries = $obj;
        $firstKey = array_key_first($entries);
        $firstValue = $entries[$firstKey];
        $encodedKey = Primitives::encodeKey($firstKey);
        if (Normalize::isJsonPrimitive($firstValue)) {
            $writer->pushListItem($depth, $encodedKey . Constants::COLON . ' ' . Primitives::encodePrimitive($firstValue, $options->delimiter));
        } elseif (Normalize::isJsonArray($firstValue)) {
            if (Normalize::isArrayOfPrimitives($firstValue)) {
                $arrayPropertyLine = self::encodeInlineArrayLine($firstValue, $options->delimiter, $firstKey);
                $writer->pushListItem($depth, $arrayPropertyLine);
            } elseif (Normalize::isArrayOfObjects($firstValue)) {
                $header = self::extractTabularHeader($firstValue);
                if ($header) {
                    $formattedHeader = Primitives::formatHeader(count($firstValue), $firstKey, $header, $options->delimiter);
                    $writer->pushListItem($depth, $formattedHeader);
                    self::writeTabularRows($firstValue, $header, $writer, $depth + 1, $options);
                } else {
                    $writer->pushListItem($depth, $encodedKey . '[' . count($firstValue) . ']' . Constants::COLON);
                    foreach ($firstValue as $item) {
                        self::encodeObjectAsListItem($item, $writer, $depth + 1, $options);
                    }
                }
            } else {
                $writer->pushListItem($depth, $encodedKey . '[' . count($firstValue) . ']' . Constants::COLON);
                foreach ($firstValue as $item) {
                    self::encodeListItemValue($item, $writer, $depth + 1, $options);
                }
            }
        } elseif (Normalize::isJsonObject($firstValue)) {
            $writer->pushListItem($depth, $encodedKey . Constants::COLON);
            if (!Normalize::isEmptyObject($firstValue)) {
                self::encodeObject($firstValue, $writer, $depth + 2, $options);
            }
        }
        $remaining = array_slice($entries, 1, null, true);
        foreach ($remaining as $key => $value) {
            self::encodeKeyValuePair($key, $value, $writer, $depth + 1, $options);
        }
    }

    private static function encodeListItemValue(mixed $value, LineWriter $writer, int $depth, ResolvedEncodeOptions $options): void {
        if (Normalize::isJsonPrimitive($value)) {
            $writer->pushListItem($depth, Primitives::encodePrimitive($value, $options->delimiter));
        } elseif (Normalize::isJsonArray($value) && Normalize::isArrayOfPrimitives($value)) {
            $arrayLine = self::encodeInlineArrayLine($value, $options->delimiter);
            $writer->pushListItem($depth, $arrayLine);
        } elseif (Normalize::isJsonObject($value)) {
            self::encodeObjectAsListItem($value, $writer, $depth, $options);
        }
    }
}
