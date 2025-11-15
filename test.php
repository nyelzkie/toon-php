<?php
require_once 'vendor/autoload.php';

use Toon\Toon;

$data = ['name' => 'Alice', 'age' => 30];
echo Toon::encode($data) . PHP_EOL;

$toonString = "name: Alice\nage: 30";
var_dump(Toon::decode($toonString));
