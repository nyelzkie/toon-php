<?php
namespace Toon\Decode;

use Toon\ResolvedDecodeOptions;
use Toon\Constants;
use Toon\Shared\Util;

class Decoder {
    public static function decodeValueFromLines(LineCursor $cursor, ResolvedDecodeOptions $options): mixed {
        $first = $cursor->peek();
        if ($first === null) {
            throw new \ReferenceError('No content to decode');
        }
        if (Parser::isArrayHeaderAfterHyphen($first['content'])) {
            $headerInfo = Parser::parseArrayHeaderLine($first['content'], Constants::DEFAULT_DELIMITER);
            if ($headerInfo) {
                $cursor->advance();
                return self::decodeArrayFromHeader($headerInfo['header'], $headerInfo['inlineValues'], $cursor, 0, $options);
            }
        }
        if ($cursor->getLength() === 1 && !self::isKeyValueLine($first)) {
            return Parser::parsePrimitiveToken(trim($first['content']));
        }
        return self::decodeObject($cursor, 0, $options);
    }

    private static function isKeyValueLine(array $line): bool {
        $content = $line['content'];
        if (str_starts_with($content, Constants::DOUBLE_QUOTE)) {
            $closingQuoteIndex = Util::findClosingQuote($content, 0);
            if ($closingQuoteIndex === -1) {
                return false;
            }
            return str_contains(substr($content, $closingQuoteIndex + 1), Constants::COLON);
        }
        return str_contains($content, Constants::COLON);
    }

    private static function decodeObject(LineCursor $cursor, int $baseDepth, ResolvedDecodeOptions $options): array {
        $obj = [];
        $quotedKeys = [];
        $computedDepth = null;
        while (!$cursor->atEnd()) {
            $line = $cursor->peek();
            if ($line === null || $line['depth'] < $baseDepth) {
                break;
            }
            if ($computedDepth === null && $line['depth'] >= $baseDepth) {
                $computedDepth = $line['depth'];
            }
            if ($line['depth'] === $computedDepth) {
                $cursor->advance();
                $decode = self::decodeKeyValue($line['content'], $cursor, $computedDepth, $options);
                $obj[$decode['key']] = $decode['value'];
                if ($decode['isQuoted'] && str_contains($decode['key'], Constants::DOT)) {
                    $quotedKeys[] = $decode['key'];
                }
            } else {
                break;
            }
        }
        if (!empty($quotedKeys)) {
            $obj[Expand::QUOTED_KEY_MARKER] = array_unique($quotedKeys);
        }
        return $obj;
    }

    private static function decodeKeyValue(string $content, LineCursor $cursor, int $baseDepth, ResolvedDecodeOptions $options): array {
        $arrayHeader = Parser::parseArrayHeaderLine($content, Constants::DEFAULT_DELIMITER);
        if ($arrayHeader && $arrayHeader['header']['key'] !== null) {
            $decodedValue = self::decodeArrayFromHeader($arrayHeader['header'], $arrayHeader['inlineValues'], $cursor, $baseDepth, $options);
            return ['key' => $arrayHeader['header']['key'], 'value' => $decodedValue, 'followDepth' => $baseDepth + 1, 'isQuoted' => false];
        }
        $parse = Parser::parseKeyToken($content, 0);
        $key = $parse['key'];
        $end = $parse['end'];
        $isQuoted = $parse['isQuoted'];
        $rest = trim(substr($content, $end));
        if ($rest === '') {
            $nextLine = $cursor->peek();
            if ($nextLine && $nextLine['depth'] > $baseDepth) {
                $nested = self::decodeObject($cursor, $baseDepth + 1, $options);
                return ['key' => $key, 'value' => $nested, 'followDepth' => $baseDepth + 1, 'isQuoted' => $isQuoted];
            }
            return ['key' => $key, 'value' => [], 'followDepth' => $baseDepth + 1, 'isQuoted' => $isQuoted];
        }
        $decodedValue = Parser::parsePrimitiveToken($rest);
        return ['key' => $key, 'value' => $decodedValue, 'followDepth' => $baseDepth + 1, 'isQuoted' => $isQuoted];
    }

    private static function decodeArrayFromHeader(array $header, ?string $inlineValues, LineCursor $cursor, int $baseDepth, ResolvedDecodeOptions $options): mixed {
        if ($inlineValues !== null) {
            return self::decodeInlinePrimitiveArray($header, $inlineValues, $options);
        }
        if (!empty($header['fields'])) {
            return self::decodeTabularArray($header, $cursor, $baseDepth, $options);
        }
        return self::decodeListArray($header, $cursor, $baseDepth, $options);
    }

    private static function decodeInlinePrimitiveArray(array $header, string $inlineValues, ResolvedDecodeOptions $options): array {
        $trimmed = trim($inlineValues);
        if ($trimmed === '') {
            DecodeValidation::assertExpectedCount(0, $header['length'], 'inline array items', $options);
            return [];
        }
        $values = Parser::parseDelimitedValues($trimmed, $header['delimiter']);
        $primitives = Parser::mapRowValuesToPrimitives($values);
        DecodeValidation::assertExpectedCount(count($primitives), $header['length'], 'inline array items', $options);
        return $primitives;
    }

    private static function decodeListArray(array $header, LineCursor $cursor, int $baseDepth, ResolvedDecodeOptions $options): array {
        $items = [];
        $itemDepth = $baseDepth + 1;
        $startLine = null;
        $endLine = null;
        while (!$cursor->atEnd() && count($items) < $header['length']) {
            $line = $cursor->peek();
            if ($line === null || $line['depth'] < $itemDepth) {
                break;
            }
            $isListItem = str_starts_with($line['content'], Constants::LIST_ITEM_PREFIX) || $line['content'] === '-';
            if ($line['depth'] === $itemDepth && $isListItem) {
                if ($startLine === null) {
                    $startLine = $line['lineNumber'];
                }
                $endLine = $line['lineNumber'];
                $item = self::decodeListItem($cursor, $itemDepth, $options);
                $items[] = $item;
                $currentLine = $cursor->current();
                if ($currentLine) {
                    $endLine = $currentLine['lineNumber'];
                }
            } else {
                break;
            }
        }
        DecodeValidation::assertExpectedCount(count($items), $header['length'], 'list array items', $options);
        if ($options->strict && $startLine !== null && $endLine !== null) {
            DecodeValidation::validateNoBlankLinesInRange($startLine, $endLine, $cursor->getBlankLines(), $options->strict, 'list array');
        }
        if ($options->strict) {
            DecodeValidation::validateNoExtraListItems($cursor, $itemDepth, $header['length']);
        }
        return $items;
    }

    private static function decodeTabularArray(array $header, LineCursor $cursor, int $baseDepth, ResolvedDecodeOptions $options): array {
        $objects = [];
        $rowDepth = $baseDepth + 1;
        $startLine = null;
        $endLine = null;
        while (!$cursor->atEnd() && count($objects) < $header['length']) {
            $line = $cursor->peek();
            if ($line === null || $line['depth'] < $rowDepth) {
                break;
            }
            if ($line['depth'] === $rowDepth) {
                if ($startLine === null) {
                    $startLine = $line['lineNumber'];
                }
                $endLine = $line['lineNumber'];
                $cursor->advance();
                $values = Parser::parseDelimitedValues($line['content'], $header['delimiter']);
                DecodeValidation::assertExpectedCount(count($values), count($header['fields']), 'tabular row values', $options);
                $primitives = Parser::mapRowValuesToPrimitives($values);
                $obj = [];
                foreach ($header['fields'] as $i => $field) {
                    $obj[$field] = $primitives[$i];
                }
                $objects[] = $obj;
            } else {
                break;
            }
        }
        DecodeValidation::assertExpectedCount(count($objects), $header['length'], 'tabular rows', $options);
        if ($options->strict && $startLine !== null && $endLine !== null) {
            DecodeValidation::validateNoBlankLinesInRange($startLine, $endLine, $cursor->getBlankLines(), $options->strict, 'tabular array');
        }
        if ($options->strict) {
            DecodeValidation::validateNoExtraTabularRows($cursor, $rowDepth, $header);
        }
        return $objects;
    }

    private static function decodeListItem(LineCursor $cursor, int $baseDepth, ResolvedDecodeOptions $options): mixed {
        $line = $cursor->next();
        if ($line === null) {
            throw new \ReferenceError('Expected list item');
        }
        if ($line['content'] === '-') {
            return [];
        } elseif (str_starts_with($line['content'], Constants::LIST_ITEM_PREFIX)) {
            $afterHyphen = substr($line['content'], strlen(Constants::LIST_ITEM_PREFIX));
        } else {
            throw new \SyntaxError('Expected list item to start with "' . Constants::LIST_ITEM_PREFIX . '"');
        }
        if (trim($afterHyphen) === '') {
            return [];
        }
        if (Parser::isArrayHeaderAfterHyphen($afterHyphen)) {
            $arrayHeader = Parser::parseArrayHeaderLine($afterHyphen, Constants::DEFAULT_DELIMITER);
            if ($arrayHeader) {
                return self::decodeArrayFromHeader($arrayHeader['header'], $arrayHeader['inlineValues'], $cursor, $baseDepth, $options);
            }
        }
        if (Parser::isObjectFirstFieldAfterHyphen($afterHyphen)) {
            return self::decodeObjectFromListItem($line, $cursor, $baseDepth, $options);
        }
        return Parser::parsePrimitiveToken($afterHyphen);
    }

    private static function decodeObjectFromListItem(array $firstLine, LineCursor $cursor, int $baseDepth, ResolvedDecodeOptions $options): array {
        $afterHyphen = substr($firstLine['content'], strlen(Constants::LIST_ITEM_PREFIX));
        $decode = self::decodeKeyValue($afterHyphen, $cursor, $baseDepth, $options);
        $obj = [$decode['key'] => $decode['value']];
        $quotedKeys = [];
        if ($decode['isQuoted'] && str_contains($decode['key'], Constants::DOT)) {
            $quotedKeys[] = $decode['key'];
        }
        $followDepth = $decode['followDepth'];
        while (!$cursor->atEnd()) {
            $line = $cursor->peek();
            if ($line === null || $line['depth'] < $followDepth) {
                break;
            }
            if ($line['depth'] === $followDepth && !str_starts_with($line['content'], Constants::LIST_ITEM_PREFIX)) {
                $cursor->advance();
                $dec = self::decodeKeyValue($line['content'], $cursor, $followDepth, $options);
                $obj[$dec['key']] = $dec['value'];
                if ($dec['isQuoted'] && str_contains($dec['key'], Constants::DOT)) {
                    $quotedKeys[] = $dec['key'];
                }
            } else {
                break;
            }
        }
        if (!empty($quotedKeys)) {
            $obj[Expand::QUOTED_KEY_MARKER] = array_unique($quotedKeys);
        }
        return $obj;
    }
}
