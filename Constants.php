<?php
namespace Toon;

class Constants {
    public const LIST_ITEM_MARKER = '-';
    public const LIST_ITEM_PREFIX = '- ';
    public const COMMA = ',';
    public const COLON = ':';
    public const SPACE = ' ';
    public const PIPE = '|';
    public const DOT = '.';
    public const OPEN_BRACKET = '[';
    public const CLOSE_BRACKET = ']';
    public const OPEN_BRACE = '{';
    public const CLOSE_BRACE = '}';
    public const NULL_LITERAL = 'null';
    public const TRUE_LITERAL = 'true';
    public const FALSE_LITERAL = 'false';
    public const BACKSLASH = '\\';
    public const DOUBLE_QUOTE = '"';
    public const NEWLINE = "\n";
    public const CARRIAGE_RETURN = "\r";
    public const TAB = "\t";
    public const DELIMITERS = [
        'comma' => self::COMMA,
        'tab' => self::TAB,
        'pipe' => self::PIPE,
    ];
    public const DEFAULT_DELIMITER = self::COMMA;
}
