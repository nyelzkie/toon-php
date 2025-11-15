<?php
namespace Toon;

final class Decoder
{
    private DecodeOptions $opts;

    public function __construct(?DecodeOptions $opts = null)
    {
        $this->opts = $opts ?? new DecodeOptions();
    }

    public function decode(string $input): mixed
    {
        $lines = preg_split("/\r?\n/", $input);
        $lexer = new Lexer($lines, $this->opts->indent);
        $node = $this->parseDocument($lexer);
        if ($this->opts->expandPaths === 'safe') {
            $node = KeyFolder::expand($node);
        }
        return $node;
    }

    private function parseDocument(Lexer $lexer): mixed
    {
        $result = [];
        while ($lexer->hasNext()) {
            $hdr = $lexer->peekHeader();
            if ($hdr) {
                $block = $this->parseBlock($lexer);
                $result = array_merge($result, $block);
            } else {
                $val = $this->parseValue($lexer->nextLine());
                $result[] = $val;
            }
        }
        return $result;
    }
}
