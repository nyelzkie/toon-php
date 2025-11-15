<?php
namespace Toon\Shared;

use Toon\Constants;

class Util {
    public static function escapeString(string $value): string {
        $value = str_replace('\\', '\\\\', $value);
        $value = str_replace('"', '\\"', $value);
        $value = str_replace("\n", '\\n', $value);
        $value = str_replace("\r", '\\r', $value);
        $value = str_replace("\t", '\\t', $value);
        return $value;
    }

    public static function unescapeString(string $value): string {
        $unescaped = '';
        $i = 0;
        $length = strlen($value);
        while ($i < $length) {
            if ($value[$i] === Constants::BACKSLASH) {
                if ($i + 1 >= $length) {
                    throw new \SyntaxError('Invalid escape sequence: backslash at end of string');
                }
                $next = $value[$i + 1];
                switch ($next) {
                    case 'n':
                        $unescaped .= Constants::NEWLINE;
                        break;
                    case 't':
                        $unescaped .= Constants::TAB;
                        break;
                    case 'r':
                        $unescaped .= Constants::CARRIAGE_RETURN;
                        break;
                    case Constants::BACKSLASH:
                        $unescaped .= Constants::BACKSLASH;
                        break;
                    case Constants::DOUBLE_QUOTE:
                        $unescaped .= Constants::DOUBLE_QUOTE;
                        break;
                    default:
                        throw new \SyntaxError("Invalid escape sequence: \\$next");
                }
                $i += 2;
                continue;
            }
            $unescaped .= $value[$i];
            $i++;
        }
        return $unescaped;
    }

    public static function findClosingQuote(string $content, int $start): int {
        $i = $start + 1;
        $length = strlen($content);
        while ($i < $length) {
            if ($content[$i] === Constants::BACKSLASH && $i + 1 < $length) {
                $i += 2;
                continue;
            }
            if ($content[$i] === Constants::DOUBLE_QUOTE) {
                return $i;
            }
            $i++;
        }
        return -1;
    }

    public static function findUnquotedChar(string $content, string $char, int $start = 0): int {
        $inQuotes = false;
        $i = $start;
        $length = strlen($content);
        while ($i < $length) {
            if ($content[$i] === Constants::BACKSLASH && $i + 1 < $length && $inQuotes) {
                $i += 2;
                continue;
            }
            if ($content[$i] === Constants::DOUBLE_QUOTE) {
                $inQuotes = !$inQuotes;
                $i++;
                continue;
            }
            if ($content[$i] === $char && !$inQuotes) {
                return $i;
            }
            $i++;
        }
        return -1;
    }
}
