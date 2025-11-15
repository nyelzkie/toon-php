<?php
namespace Toon\Decode;

use Toon\ResolvedDecodeOptions;
use Toon\Constants;

class DecodeValidation {
    public static function assertExpectedCount(int $actual, int $expected, string $itemType, ResolvedDecodeOptions $options): void {
        if ($options->strict && $actual !== $expected) {
            throw new \RangeException("Expected $expected $itemType, but got $actual");
        }
    }

    public static function validateNoExtraListItems(LineCursor $cursor, int $itemDepth, int $expectedCount): void {
        $nextLine = $cursor->peek();
        if ($nextLine && $nextLine['depth'] === $itemDepth && str_starts_with($nextLine['content'], Constants::LIST_ITEM_PREFIX)) {
            throw new \RangeException("Expected $expectedCount list array items, but found more");
        }
    }

    public static function validateNoExtraTabularRows(LineCursor $cursor, int $rowDepth, array $header): void {
        $nextLine = $cursor->peek();
        if ($nextLine && $nextLine['depth'] === $rowDepth && !str_starts_with($nextLine['content'], Constants::LIST_ITEM_PREFIX) && self::isDataRow($nextLine['content'], $header['delimiter'])) {
            throw new \RangeException("Expected {$header['length']} tabular rows, but found more");
        }
    }

    public static function validateNoBlankLinesInRange(int $startLine, int $endLine, array $blankLines, bool $strict, string $context): void {
        if (!$strict) {
            return;
        }
        foreach ($blankLines as $blank) {
            if ($blank['lineNumber'] > $startLine && $blank['lineNumber'] < $endLine) {
                throw new \SyntaxError("Line {$blank['lineNumber']}: Blank lines inside $context are not allowed in strict mode");
            }
        }
    }

    private static function isDataRow(string $content, string $delimiter): bool {
        $colonPos = strpos($content, Constants::COLON);
        $delimiterPos = strpos($content, $delimiter);
        if ($colonPos === false) {
            return true;
        }
        if ($delimiterPos !== false && $delimiterPos < $colonPos) {
            return true;
        }
        return false;
    }
}
