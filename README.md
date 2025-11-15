# toon-php

**Token-Oriented Object Notation** is a compact, human-readable format for serializing JSON data in LLM prompts. It represents the same objects, arrays, and primitives as JSON, but in a syntax that minimizes tokens and makes structure easy for models to follow.

**TOON** combines YAML's indentation-based structure for nested objects with a CSV-style tabular layout for uniform arrays. TOON's sweet spot is uniform arrays of objects (multiple fields per row, same structure across items), achieving CSV-like compactness while adding explicit structure that helps LLMs parse and validate data reliably. For deeply nested or non-uniform data, JSON may be more efficient.

The similarity to CSV is intentional: CSV is simple and ubiquitous, and TOON aims to keep that familiarity while remaining a lossless, drop-in representation of JSON for Large Language Models.

Think of it as a translation layer: use JSON programmatically, and encode it as TOON for LLM input.

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
