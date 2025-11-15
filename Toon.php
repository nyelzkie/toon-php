<?php
/**
 * 
 * @project: toon-php
 * @author: Seyyed Ali Mohammadiyeh (Max Base)
 * @repository: https://github.com/BaseMax/toon-php
 * @license: MIT
 * 
 */

namespace Toon;

use Toon\ResolvedEncodeOptions;
use Toon\DecodeOptions;
use Toon\ResolvedDecodeOptions;
use Toon\Decode\Decoder;
use Toon\Decode\LineCursor;
use Toon\Decode\Scanner;
use Toon\Encode\Encoder;
use Toon\Encode\Normalize;
use Toon\Decode\Expand;

class ReferenceError extends \Exception {}
class SyntaxError extends \Exception {}

class Toon {
    public static function encode(mixed $input, ?EncodeOptions $options = null): string {
        $normalizedValue = Normalize::normalizeValue($input);
        $resolvedOptions = self::resolveEncodeOptions($options);
        return Encoder::encodeValue($normalizedValue, $resolvedOptions);
    }

    public static function decode(string $input, ?DecodeOptions $options = null): mixed {
        $resolvedOptions = self::resolveDecodeOptions($options);
        $scanResult = Scanner::toParsedLines($input, $resolvedOptions->indent, $resolvedOptions->strict);
        if (empty($scanResult['lines'])) {
            return [];
        }
        $cursor = new LineCursor($scanResult['lines'], $scanResult['blankLines']);
        $decodedValue = Decoder::decodeValueFromLines($cursor, $resolvedOptions);
        if ($resolvedOptions->expandPaths === 'safe') {
            return Expand::expandPathsSafe($decodedValue, $resolvedOptions->strict);
        }
        return $decodedValue;
    }

    private static function resolveEncodeOptions(?EncodeOptions $options): ResolvedEncodeOptions {
        return new ResolvedEncodeOptions(
            $options?->indent ?? 2,
            $options?->delimiter ?? Constants::DEFAULT_DELIMITER,
            $options?->keyFolding ?? 'off',
            $options?->flattenDepth ?? PHP_INT_MAX
        );
    }

    private static function resolveDecodeOptions(?DecodeOptions $options): ResolvedDecodeOptions {
        return new ResolvedDecodeOptions(
            $options?->indent ?? 2,
            $options?->strict ?? true,
            $options?->expandPaths ?? 'off'
        );
    }
}
