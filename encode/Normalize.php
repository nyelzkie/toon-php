<?php
namespace Toon\Encode;

class Normalize {
    public static function normalizeValue(mixed $value): mixed {
        if ($value === null) {
            return null;
        }
        if (is_string($value) || is_bool($value)) {
            return $value;
        }
        if (is_numeric($value)) {
            if ($value == -0) {
                return 0;
            }
            if (!is_finite($value)) {
                return null;
            }
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTime::ISO8601);
        }
        if (is_array($value)) {
            if (array_is_list($value)) {
                return array_map([self::class, 'normalizeValue'], $value);
            } else {
                $normalized = [];
                foreach ($value as $k => $v) {
                    $normalized[$k] = self::normalizeValue($v);
                }
                return $normalized;
            }
        }
        if ($value instanceof \Traversable) {
            return array_map([self::class, 'normalizeValue'], iterator_to_array($value));
        }
        return null;
    }

    public static function isJsonPrimitive(mixed $value): bool {
        return $value === null || is_string($value) || is_numeric($value) || is_bool($value);
    }

    public static function isJsonArray(mixed $value): bool {
        return is_array($value) && array_is_list($value);
    }

    public static function isJsonObject(mixed $value): bool {
        return is_array($value) && !array_is_list($value);
    }

    public static function isEmptyObject(mixed $value): bool {
        return self::isJsonObject($value) && empty($value);
    }

    public static function isArrayOfPrimitives(array $value): bool {
        return array_reduce($value, fn($carry, $item) => $carry && self::isJsonPrimitive($item), true);
    }

    public static function isArrayOfArrays(array $value): bool {
        return array_reduce($value, fn($carry, $item) => $carry && self::isJsonArray($item), true);
    }

    public static function isArrayOfObjects(array $value): bool {
        return array_reduce($value, fn($carry, $item) => $carry && self::isJsonObject($item), true);
    }
}
