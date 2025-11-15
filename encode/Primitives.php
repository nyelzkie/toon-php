<?php
namespace Toon\Encode;

use Toon\Constants;
use Toon\Shared\Validation;
use Toon\Shared\Util;

class Primitives {
    public static function encodePrimitive(mixed $value, string $delimiter = Constants::DEFAULT_DELIMITER): string {
        if ($value === null) {
            return Constants::NULL_LITERAL;
        }
        if (is_bool($value)) {
            return $value ? Constants::TRUE_LITERAL : Constants::FALSE_LITERAL;
        }
        if (is_numeric($value)) {
            return (string) $value;
        }
        return self::encodeStringLiteral((string) $value, $delimiter);
    }

    public static function encodeStringLiteral(string $value, string $delimiter = Constants::DEFAULT_DELIMITER): string {
        if (Validation::isSafeUnquoted($value, $delimiter)) {
            return $value;
        }
        return Constants::DOUBLE_QUOTE . Util::escapeString($value) . Constants::DOUBLE_QUOTE;
    }

    public static function encodeKey(string $key): string {
        if (Validation::isValidUnquotedKey($key)) {
            return $key;
        }
        return Constants::DOUBLE_QUOTE . Util::escapeString($key) . Constants::DOUBLE_QUOTE;
    }

    public static function encodeAndJoinPrimitives(array $values, string $delimiter = Constants::DEFAULT_DELIMITER): string {
        return implode($delimiter, array_map(fn($v) => self::encodePrimitive($v, $delimiter), $values));
    }

    public static function formatHeader(int $length, ?string $key = null, ?array $fields = null, string $delimiter = Constants::DEFAULT_DELIMITER): string {
        $header = '';
        if ($key) {
            $header .= self::encodeKey($key);
        }
        $delimStr = $delimiter !== Constants::DEFAULT_DELIMITER ? $delimiter : '';
        $header .= '[' . $length . $delimStr . ']';
        if ($fields) {
            $quotedFields = array_map([self::class, 'encodeKey'], $fields);
            $header .= '{' . implode($delimiter, $quotedFields) . '}';
        }
        $header .= Constants::COLON;
        return $header;
    }
}
