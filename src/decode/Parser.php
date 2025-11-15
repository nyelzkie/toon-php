<?php
namespace Toon\Decode;

use Toon\Constants;
use Toon\Shared\LiteralUtils;
use Toon\Shared\Util;

class Parser {
    public static function parseArrayHeaderLine(string $content, string $defaultDelimiter): ?array {
        $trimmed = ltrim($content);
        $bracketStart = -1;
        if (str_starts_with($trimmed, Constants::DOUBLE_QUOTE)) {
            $closingQuoteIndex = Util::findClosingQuote($trimmed, 0);
            if ($closingQuoteIndex === -1) {
                return null;
            }
            $afterQuote = substr($trimmed, $closingQuoteIndex + 1);
            if (!str_starts_with($afterQuote, Constants::OPEN_BRACKET)) {
                return null;
            }
            $leadingWhitespace = strlen($content) - strlen($trimmed);
            $keyEndIndex = $leadingWhitespace + $closingQuoteIndex + 1;
            $bracketStart = strpos($content, Constants::OPEN_BRACKET, $keyEndIndex);
        } else {
            $bracketStart = strpos($content, Constants::OPEN_BRACKET);
        }
        if ($bracketStart === false) {
            return null;
        }
        $bracketEnd = strpos($content, Constants::CLOSE_BRACKET, $bracketStart);
        if ($bracketEnd === false) {
            return null;
        }
        $colonIndex = strpos($content, Constants::COLON, $bracketEnd);
        if ($colonIndex === false) {
            return null;
        }
        $braceEnd = $colonIndex;
        $braceStart = strpos($content, Constants::OPEN_BRACE, $bracketEnd);
        if ($braceStart !== false && $braceStart < $colonIndex) {
            $foundBraceEnd = strpos($content, Constants::CLOSE_BRACE, $braceStart);
            if ($foundBraceEnd !== false) {
                $braceEnd = $foundBraceEnd + 1;
            }
        }
        $colonIndex = strpos($content, Constants::COLON, max($bracketEnd, $braceEnd));
        if ($colonIndex === false) {
            return null;
        }
        $key = null;
        if ($bracketStart > 0) {
            $rawKey = trim(substr($content, 0, $bracketStart));
            $key = str_starts_with($rawKey, Constants::DOUBLE_QUOTE) ? self::parseStringLiteral($rawKey) : $rawKey;
        }
        $afterColon = trim(substr($content, $colonIndex + 1));
        $bracketContent = substr($content, $bracketStart + 1, $bracketEnd - $bracketStart - 1);
        try {
            $parsedBracket = self::parseBracketSegment($bracketContent, $defaultDelimiter);
        } catch (\Exception $e) {
            return null;
        }
        $length = $parsedBracket['length'];
        $delimiter = $parsedBracket['delimiter'];
        $fields = null;
        if ($braceStart !== false && $braceStart < $colonIndex) {
            $foundBraceEnd = strpos($content, Constants::CLOSE_BRACE, $braceStart);
            if ($foundBraceEnd !== false && $foundBraceEnd < $colonIndex) {
                $fieldsContent = substr($content, $braceStart + 1, $foundBraceEnd - $braceStart - 1);
                $fields = array_map(fn($field) => self::parseStringLiteral(trim($field)), self::parseDelimitedValues($fieldsContent, $delimiter));
            }
        }
        return [
            'header' => [
                'key' => $key,
                'length' => $length,
                'delimiter' => $delimiter,
                'fields' => $fields,
            ],
            'inlineValues' => $afterColon ?: null,
        ];
    }

    public static function parseBracketSegment(string $seg, string $defaultDelimiter): array {
        $content = $seg;
        $delimiter = $defaultDelimiter;
        if (str_ends_with($content, Constants::TAB)) {
            $delimiter = Constants::DELIMITERS['tab'];
            $content = substr($content, 0, -1);
        } elseif (str_ends_with($content, Constants::PIPE)) {
            $delimiter = Constants::DELIMITERS['pipe'];
            $content = substr($content, 0, -1);
        }
        $length = (int) $content;
        if (is_nan($length)) {
            throw new \TypeError("Invalid array length: $seg");
        }
        return ['length' => $length, 'delimiter' => $delimiter];
    }

    public static function parseDelimitedValues(string $input, string $delimiter): array {
        $values = [];
        $valueBuffer = '';
        $inQuotes = false;
        $i = 0;
        $length = strlen($input);
        while ($i < $length) {
            $char = $input[$i];
            if ($char === Constants::BACKSLASH && $i + 1 < $length && $inQuotes) {
                $valueBuffer .= $char . $input[$i + 1];
                $i += 2;
                continue;
            }
            if ($char === Constants::DOUBLE_QUOTE) {
                $inQuotes = !$inQuotes;
                $valueBuffer .= $char;
                $i++;
                continue;
            }
            if ($char === $delimiter && !$inQuotes) {
                $values[] = trim($valueBuffer);
                $valueBuffer = '';
                $i++;
                continue;
            }
            $valueBuffer .= $char;
            $i++;
        }
        if ($valueBuffer !== '' || !empty($values)) {
            $values[] = trim($valueBuffer);
        }
        return $values;
    }

    public static function mapRowValuesToPrimitives(array $values): array {
        return array_map([self::class, 'parsePrimitiveToken'], $values);
    }

    public static function parsePrimitiveToken(string $token): mixed {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return '';
        }
        if (str_starts_with($trimmed, Constants::DOUBLE_QUOTE)) {
            return self::parseStringLiteral($trimmed);
        }
        if (LiteralUtils::isBooleanOrNullLiteral($trimmed)) {
            if ($trimmed === Constants::TRUE_LITERAL) {
                return true;
            }
            if ($trimmed === Constants::FALSE_LITERAL) {
                return false;
            }
            if ($trimmed === Constants::NULL_LITERAL) {
                return null;
            }
        }
        if (LiteralUtils::isNumericLiteral($trimmed)) {
            $parsedNumber = (float) $trimmed;
            return $parsedNumber === -0.0 ? 0 : $parsedNumber;
        }
        return $trimmed;
    }

    public static function parseStringLiteral(string $token): string {
        $trimmedToken = trim($token);
        if (str_starts_with($trimmedToken, Constants::DOUBLE_QUOTE)) {
            $closingQuoteIndex = Util::findClosingQuote($trimmedToken, 0);
            if ($closingQuoteIndex === -1) {
                throw new \SyntaxError('Unterminated string: missing closing quote');
            }
            if ($closingQuoteIndex !== strlen($trimmedToken) - 1) {
                throw new \SyntaxError('Unexpected characters after closing quote');
            }
            $content = substr($trimmedToken, 1, $closingQuoteIndex - 1);
            return Util::unescapeString($content);
        }
        return $trimmedToken;
    }

    public static function parseUnquotedKey(string $content, int $start): array {
        $parsePosition = $start;
        while ($parsePosition < strlen($content) && $content[$parsePosition] !== Constants::COLON) {
            $parsePosition++;
        }
        if ($parsePosition >= strlen($content) || $content[$parsePosition] !== Constants::COLON) {
            throw new \SyntaxError('Missing colon after key');
        }
        $key = trim(substr($content, $start, $parsePosition - $start));
        $parsePosition++;
        return ['key' => $key, 'end' => $parsePosition];
    }

    public static function parseQuotedKey(string $content, int $start): array {
        $closingQuoteIndex = Util::findClosingQuote($content, $start);
        if ($closingQuoteIndex === -1) {
            throw new \SyntaxError('Unterminated quoted key');
        }
        $keyContent = substr($content, $start + 1, $closingQuoteIndex - $start - 1);
        $key = Util::unescapeString($keyContent);
        $parsePosition = $closingQuoteIndex + 1;
        if ($parsePosition >= strlen($content) || $content[$parsePosition] !== Constants::COLON) {
            throw new \SyntaxError('Missing colon after key');
        }
        $parsePosition++;
        return ['key' => $key, 'end' => $parsePosition];
    }

    public static function parseKeyToken(string $content, int $start): array {
        $isQuoted = $content[$start] === Constants::DOUBLE_QUOTE;
        $result = $isQuoted ? self::parseQuotedKey($content, $start) : self::parseUnquotedKey($content, $start);
        return array_merge($result, ['isQuoted' => $isQuoted]);
    }

    public static function isArrayHeaderAfterHyphen(string $content): bool {
        return str_starts_with(trim($content), Constants::OPEN_BRACKET) && Util::findUnquotedChar($content, Constants::COLON) !== -1;
    }

    public static function isObjectFirstFieldAfterHyphen(string $content): bool {
        return Util::findUnquotedChar($content, Constants::COLON) !== -1;
    }
}
