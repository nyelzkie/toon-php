<?php
namespace Toon\Shared;

use Toon\Constants;

class LiteralUtils {
    public static function isBooleanOrNullLiteral(string $token): bool {
        return $token === Constants::TRUE_LITERAL || $token === Constants::FALSE_LITERAL || $token === Constants::NULL_LITERAL;
    }

    public static function isNumericLiteral(string $token): bool {
        if (empty($token)) {
            return false;
        }
        if (strlen($token) > 1 && $token[0] === '0' && $token[1] !== '.') {
            return false;
        }
        $numericValue = filter_var($token, FILTER_VALIDATE_FLOAT);
        return $numericValue !== false && is_finite($numericValue);
    }
}
