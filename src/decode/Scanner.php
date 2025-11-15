<?php
namespace Toon\Decode;

use Toon\Constants;

class Scanner {
    public static function toParsedLines(string $source, int $indentSize, bool $strict): array {
        if (trim($source) === '') {
            return ['lines' => [], 'blankLines' => []];
        }
        $lines = explode("\n", $source);
        $parsed = [];
        $blankLines = [];
        for ($i = 0; $i < count($lines); $i++) {
            $raw = $lines[$i];
            $lineNumber = $i + 1;
            $indent = 0;
            while ($indent < strlen($raw) && $raw[$indent] === Constants::SPACE) {
                $indent++;
            }
            $content = substr($raw, $indent);
            if (trim($content) === '') {
                $depth = self::computeDepthFromIndent($indent, $indentSize);
                $blankLines[] = ['lineNumber' => $lineNumber, 'indent' => $indent, 'depth' => $depth];
                continue;
            }
            $depth = self::computeDepthFromIndent($indent, $indentSize);
            if ($strict) {
                $whitespaceEndIndex = 0;
                while ($whitespaceEndIndex < strlen($raw) && ($raw[$whitespaceEndIndex] === Constants::SPACE || $raw[$whitespaceEndIndex] === Constants::TAB)) {
                    $whitespaceEndIndex++;
                }
                if (strpos(substr($raw, 0, $whitespaceEndIndex), Constants::TAB) !== false) {
                    throw new \SyntaxError("Line $lineNumber: Tabs are not allowed in indentation in strict mode");
                }
                if ($indent > 0 && $indent % $indentSize !== 0) {
                    throw new \SyntaxError("Line $lineNumber: Indentation must be exact multiple of $indentSize, but found $indent spaces");
                }
            }
            $parsed[] = ['raw' => $raw, 'indent' => $indent, 'content' => $content, 'depth' => $depth, 'lineNumber' => $lineNumber];
        }
        return ['lines' => $parsed, 'blankLines' => $blankLines];
    }

    private static function computeDepthFromIndent(int $indentSpaces, int $indentSize): int {
        return (int) floor($indentSpaces / $indentSize);
    }
}

class LineCursor {
    private array $lines;
    private int $index = 0;
    private array $blankLines;

    public function __construct(array $lines, array $blankLines = []) {
        $this->lines = $lines;
        $this->blankLines = $blankLines;
    }

    public function getBlankLines(): array {
        return $this->blankLines;
    }

    public function peek(): ?array {
        return $this->lines[$this->index] ?? null;
    }

    public function next(): ?array {
        $line = $this->peek();
        $this->advance();
        return $line;
    }

    public function current(): ?array {
        return $this->index > 0 ? $this->lines[$this->index - 1] ?? null : null;
    }

    public function advance(): void {
        $this->index++;
    }

    public function atEnd(): bool {
        return $this->index >= count($this->lines);
    }

    public function getLength(): int {
        return count($this->lines);
    }

    public function peekAtDepth(int $targetDepth): ?array {
        $line = $this->peek();
        return ($line && $line['depth'] === $targetDepth) ? $line : null;
    }
}
