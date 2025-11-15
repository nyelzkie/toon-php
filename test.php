<?php
require_once 'vendor/autoload.php';

use Toon\Toon;
use Toon\EncodeOptions;
use Toon\DecodeOptions;

// =============== 1
$data = ['name' => 'Alice', 'age' => 30];
echo Toon::encode($data) . PHP_EOL;

$toonString = "name: Alice\nage: 30";
var_dump(Toon::decode($toonString));

// =============== 2
$data = ['data' => ['metadata' => ['items' => ['id' => 1]]]];
$options = new EncodeOptions(keyFolding: 'safe');
$encoded = Toon::encode($data, $options);
echo "Encoded: \n$encoded\n";

$decodeOptions = new DecodeOptions(expandPaths: 'safe');
$decoded = Toon::decode($encoded, $decodeOptions);
echo "Decoded: \n";
var_dump($decoded);

// =============== 3
$data = ['users' => [1, 2, 3, null, true, 'text']];
$options = new EncodeOptions(delimiter: '|');
$encoded = Toon::encode($data, $options);
echo "Encoded: \n$encoded\n";

$decoded = Toon::decode($encoded);
echo "Decoded: \n";
var_dump($decoded);


// =============== 4
$data = ['employees' => [
    ['id' => 1, 'name' => 'Alice', 'salary' => 50000],
    ['id' => 2, 'name' => 'Bob', 'salary' => 60000]
]];
$encoded = Toon::encode($data);
echo "Encoded: \n$encoded\n";

$decoded = Toon::decode($encoded);
echo "Decoded: \n";
var_dump($decoded);

// =============== 5
$data = ['mixed' => [42, ['key' => 'value'], [true, false], null]];
$encoded = Toon::encode($data);
echo "Encoded: \n$encoded\n";

$decoded = Toon::decode($encoded);
echo "Decoded: \n";
var_dump($decoded);

// =============== 6
$data = ['emptyObj' => [], 'emptyArr' => [], 'nullVal' => null];
$encoded = Toon::encode($data);
echo "Encoded: \n$encoded\n";

$decoded = Toon::decode($encoded);
echo "Decoded: \n";
var_dump($decoded);

// =============== 7
$data = ['key.with.dot' => "value\nwith\\escape\""];
$encoded = Toon::encode($data);
echo "Encoded: \n$encoded\n";

$decodeOptions = new DecodeOptions(expandPaths: 'off');
$decoded = Toon::decode($encoded, $decodeOptions);
echo "Decoded: \n";
var_dump($decoded);

// =============== 8
$data = ['a' => ['b' => ['c' => ['d' => ['e' => 5]]]]];
$options = new EncodeOptions(keyFolding: 'safe', flattenDepth: 3);
$encoded = Toon::encode($data, $options);
echo "Encoded: \n$encoded\n";

$decodeOptions = new DecodeOptions(expandPaths: 'safe');
$decoded = Toon::decode($encoded, $decodeOptions);
echo "Decoded: \n";
var_dump($decoded);

// =============== 9
$data = ['matrix' => [[1, 2], [3, 4], [5, 6]]];
$encoded = Toon::encode($data);
echo "Encoded: \n$encoded\n";

$decoded = Toon::decode($encoded);
echo "Decoded: \n";
var_dump($decoded);

// =============== 10
$data = ['flags' => ['active' => true, 'deleted' => false, 'empty' => null]];
$options = new EncodeOptions(indent: 4);
$encoded = Toon::encode($data, $options);
echo "Encoded: \n$encoded\n";

$decoded = Toon::decode($encoded);
echo "Decoded: \n";
var_dump($decoded);

// =============== 11
$toonString = "users[2]{id,name}:\n1,Alice\n2,Bob\n3,Extra";
$options = new DecodeOptions(strict: true);
try {
    $decoded = Toon::decode($toonString, $options);
    var_dump($decoded);
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
