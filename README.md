# TOON-PHP: PHP Implementation of Token-Oriented Object Notation

**TOON** (Token-Oriented Object Notation) is a compact, human-readable serialization format designed specifically for representing JSON-like data in Large Language Model (LLM) prompts. It preserves the full structure of objects, arrays, and primitives from JSON while optimizing for token efficiency and parseability by AI models. By blending YAML's indentation-based nesting for hierarchical data with CSV-inspired tabular layouts for uniform arrays, TOON strikes a balance between brevity and clarity making it ideal for feeding structured data to LLMs without the verbosity of full JSON.

This PHP library is a complete, object-oriented port of the original TypeScript implementation ([toon-format/toon](https://github.com/toon-format/toon)). It supports lossless encoding from PHP data structures (arrays, objects, primitives) to TOON strings and decoding back, with advanced features like safe key folding, path expansion, custom delimiters, and strict validation modes.

## Key Features
- **Token-Optimized Syntax**: Minimizes LLM input tokens while remaining easy for models (and humans) to read and parse.
- **Hybrid Structure**:
  - Indentation for nested objects (YAML-like).
  - Tabular rows for uniform arrays of objects (CSV-like, with explicit headers for reliability).
- **Lossless Round-Tripping**: Encode PHP data to TOON and decode back without data loss, pairing well with JSON for programmatic use.
- **Advanced Options**:
  - Key folding to collapse single-key chains into dotted paths (e.g., `data.metadata.items`).
  - Path expansion during decoding to reconstruct nested structures from dotted keys.
  - Custom indentation, delimiters (comma, tab, pipe), and flattening depth.
  - Strict mode for validation (e.g., array lengths, no extra rows/items).
- **Edge Case Handling**: Supports quoted keys/values, escapes, booleans/null/numerics, empty structures, and mixed arrays.
- **PHP-Specific**: Built for PHP 8+, with PSR-4 autoloading via Composer. No external dependencies.

TOON shines for uniform datasets (e.g., lists of records with consistent fields), achieving CSV compactness while adding LLM-friendly structure. For deeply irregular or nested data, traditional JSON may be more token-efficient. Think of TOON as a "prompt-friendly JSON translator": use JSON in code, TOON in AI interactions.

## Installation

Install via Composer:

```
composer require basemax/toon-php
```

## Quick Usage
```php
<?php
require 'vendor/autoload.php';

use Toon\Toon;
use Toon\EncodeOptions;

$data = ['users' => [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']]];
$options = new EncodeOptions(indent: 4, delimiter: '|', keyFolding: 'safe');
$toon = Toon::decode($toon);
```

## Why TOON?

- **LLM Efficiency**: Reduces prompt size, helping models focus on logic over parsing.
- **Familiarity**: Borrows from CSV/YAML for quick adoption.
- **Flexibility**: Handles complex JSON equivalents reliably.
- **Open Source**: MIT-licensed, community-driven evolution of the TOON spec.

Contributions welcome! See the original spec for details, or open issues for PHP-specific features.

## Test

```bash
php test.php
```

## Example

```php
$data = ['name' => 'Alice', 'age' => 30];
echo Toon::encode($data) . PHP_EOL;

$toonString = "name: Alice\nage: 30";
var_dump(Toon::decode($toonString));
```

Copyright 2025, Seyyed Ali Mohammadiyeh (Max Base)

