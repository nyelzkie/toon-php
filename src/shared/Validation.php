<?php
namespace Toon\Shared;

use Toon\Constants;

class Validation {
    public static function isValidUnquotedKey(string $key): bool {
        return (bool) preg_match('/^[A-Z_][\\w.]*$/i', $key);
    }

    public static function isIdentifierSegment(string $key): bool {
        return (bool) preg_match('/^[A-Z_]\\w*$/i', $key);
    }

    public static function isSafeUnquoted(string $value, string $delimiter = Constants::DEFAULT_DELIMITER): bool {
        if (empty($value)) {
            return false;
        }
        if ($value !== trim($value)) {
            return false;
        }
        if (LiteralUtils::isBooleanOrNullLiteral($value) || self::isNumericLike($value)) {
            return false;
        }
        if (str_contains($value, ':')) {
            return false;
        }
        if (str_contains($value, '"') || str_contains($value, '\\')) {
            return false;
        }
        if (preg_match('/[[\\]{}]/', $value)) {
            return false;
        }
        if (preg_match('/[\\n\\r\\t]/', $value)) {
            return false;
        }
        if (str_contains($value, $delimiter)) {
            return false;
        }
        if (str_starts_with($value, Constants::LIST_ITEM_MARKER)) {
            return false;
        }
        return true;
    }

    private static function isNumericLike(string $value): bool {
        return (bool) preg_match('/^-?\\d+(?:\\.\\d+)?(?:e[+-]?\\d+)?$/i', $value) || (bool) preg_match('/^0\\d+$/', $value);
    }
}
