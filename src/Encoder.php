<?php
namespace Toon;

final class Encoder
{
    private EncodeOptions $opts;

    public function __construct(?EncodeOptions $opts = null)
    {
        $this->opts = $opts ?? new EncodeOptions();
    }

    public function encode(mixed $value): string
    {
        $lines = $this->encodeValue($value, 0);
        return implode("\n", $lines);
    }

    private function encodeValue(mixed $v, int $level): array
    {
        if (is_array($v) && self::isAssoc($v)) {
            $out = [];
            foreach ($v as $k => $val) {
                $header = $this->formatKey($k) . ':';
                $out[] = $this->indent($level) . $header;
                $body = $this->encodeValue($val, $level + 1);
                foreach ($body as $ln) $out[] = $ln;
            }
            return $out;
        }

        if (is_array($v) && self::isIndexed($v) && self::isTabular($v)) {
            $fields = array_keys($v[0]);
            $count = count($v);
            $header = sprintf('%s[%d]{%s}:', $this->formatKey('items'), $count, implode(',', $fields));
            $out[] = $this->indent($level) . $header;
            foreach ($v as $row) {
                $rowvals = array_map(fn($f) => $this->formatValue($row[$f]), $fields);
                $out[] = $this->indent($level + 1) . implode($this->opts->delimiter, $rowvals);
            }
            return $out;
        }

        if (is_array($v) && self::isIndexed($v)) {
            $count = count($v);
            $out[] = $this->indent($level) . sprintf('[%d]:', $count);
            foreach ($v as $item) {
                $out = array_merge($out, array_map(fn($l)=>$this->indent($level+1).'- '.$l, $this->encodeValue($item, $level+1)));
            }
            return $out;
        }

        return [$this->indent($level) . $this->formatValue($v)];
    }

    private static function isAssoc(array $arr): bool { /* */ }
    private static function isIndexed(array $arr): bool { /* */ }
    private function isTabular(array $arr): bool { /* */ }
    private function formatValue(mixed $v): string { /* */ }
    private function formatKey(string $k): string { return $k; }
    private function indent(int $lvl): string { return str_repeat(' ', $lvl * $this->opts->indent); }
}
