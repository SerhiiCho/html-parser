<?php

declare(strict_types=1);

namespace Serhii\GoodbyeHtml\Lexer;

use Serhii\GoodbyeHtml\Token\Token;
use Serhii\GoodbyeHtml\Token\TokenType;

class Lexer
{
    private readonly string $input;
    private int $position = 0;
    private int $nextPosition = 0;
    private string|int $char = '';
    private bool $isHtml = true;

    public function __construct(string $input)
    {
        $this->input = $input;
        $this->advanceChar();
    }

    public function nextToken(): Token
    {
        if (!$this->isHtml) {
            $this->skipWhitespace();
        }

        if ($this->char === 0) {
            return new Token(TokenType::EOF, '');
        } elseif ($this->areOpeningBraces()) {
            $this->isHtml = false;
            $this->advanceChar();
            $this->advanceChar();
            return new Token(TokenType::OPENING_BRACES, '{{');
        } elseif ($this->areClosingBraces()) {
            $this->isHtml = true;
            $this->advanceChar();
            $this->advanceChar();
            return new Token(TokenType::CLOSING_BRACES, '}}');
        }

        return $this->isHtml
            ? $this->readHtmlToken()
            : $this->readProgramToken();
    }

    private function readProgramToken(): Token
    {
        switch ($this->char) {
            case '-':
                return $this->createTokenAndAdvanceChar(TokenType::MINUS, $this->char);
            case ',':
                return $this->createTokenAndAdvanceChar(TokenType::COMMA, $this->char);
            case '?':
                return $this->createTokenAndAdvanceChar(TokenType::QUESTION_MARK, $this->char);
            case ':':
                return $this->createTokenAndAdvanceChar(TokenType::COLON, $this->char);
            case '!':
                return $this->createTokenAndAdvanceChar(TokenType::NOT, $this->char);
        }

        if ($this->isVariableStart()) {
            $this->advanceChar();
            return new Token(TokenType::VARIABLE, $this->readIdentifier());
        }

        if ($this->isStringStart()) {
            return $this->createTokenAndAdvanceChar(TokenType::STRING, $this->readString());
        }

        if ($this->isLetter($this->char)) {
            $ident = $this->readIdentifier();
            return new Token(TokenType::lookupIdentifier($ident), $ident);
        }

        if ($this->isNumber($this->char)) {
            $num = $this->readNumber();
            return new Token($this->readNumberTokenType($num), $num);
        }

        return $this->createTokenAndAdvanceChar(TokenType::ILLEGAL, $this->char);
    }

    private function isStringStart(): bool
    {
        return $this->char === "'" || $this->char === '"';
    }

    private function isVariableStart(): bool
    {
        return $this->char === '$' && $this->isLetter($this->peekChar());
    }

    private function createTokenAndAdvanceChar(TokenType $type, string $char): Token
    {
        $this->advanceChar();
        return new Token($type, $char);
    }

    private function readNumberTokenType(string $num): TokenType
    {
        // If number contains more then one dot, the token is ILLEGAL
        if (substr_count($num, '.') > 1) {
            return TokenType::ILLEGAL;
        }

        return str_contains($num, '.') ? TokenType::FLOAT : TokenType::INTEGER;
    }

    private function readHtmlToken(): Token
    {
        if ($this->char === 0) {
            $token = new Token(TokenType::EOF, 'EOF');
        } else {
            $token = new Token(TokenType::HTML, $this->readHtml());
        }

        $this->advanceChar();

        return $token;
    }

    private function areOpeningBraces(): bool
    {
        return $this->char === '{' && $this->peekChar() === '{';
    }

    private function areClosingBraces(): bool
    {
        return $this->char === '}' && $this->peekChar() === '}';
    }

    private function advanceChar(): void
    {
        if ($this->nextPosition >= strlen($this->input)) {
            $this->char = 0;
        } else {
            $this->char = $this->input[$this->nextPosition];
        }

        $this->position = $this->nextPosition;
        $this->nextPosition += 1;
    }

    private function peekChar(): string|int
    {
        if ($this->nextPosition >= strlen($this->input)) {
            return 0;
        }

        return $this->input[$this->nextPosition];
    }

    private function isLetter(string $letter): bool
    {
        return preg_match('/[_a-zA-Z]/', $letter) === 1;
    }

    private function isInteger(int|string $number): bool
    {
        if ($number === 0) {
            return false;
        }

        // The number must not contain a dot
        if (str_contains((string) $number, '.')) {
            return false;
        }

        return preg_match('/[0-9]/', $number) === 1;
    }

    private function isNumber(string $number): bool
    {
        if ($number === 0) {
            return false;
        }

        return preg_match('/[0-9.]/', $number) === 1;
    }

    private function readIdentifier(): string
    {
        $position = $this->position;

        while ($this->isLetter($this->char) || $this->isInteger($this->char)) {
            $this->advanceChar();
        }

        return substr($this->input, $position, $this->position - $position);
    }

    private function readNumber(): string
    {
        $position = $this->position;

        while ($this->isInteger($this->char) || $this->char === '.') {
            $this->advanceChar();
        }

        return substr($this->input, $position, $this->position - $position);
    }

    private function readHtml(): string
    {
        $result = '';

        while ($this->isHtml && ($this->char !== '{' && $this->peekChar() !== '{')) {
            if ($this->char === 0) {
                break;
            }

            $result .= $this->char;

            $this->advanceChar();
        }

        if ($this->char !== 0) {
            $result .= $this->char;
        }

        return $result;
    }

    private function isWhitespace(): bool
    {
        return $this->char === "\n" || $this->char === "\r" || $this->char === " " || $this->char === "\t";
    }

    private function skipWhitespace(): void
    {
        while ($this->isWhitespace()) {
            $this->advanceChar();
        }
    }

    private function readString(): string
    {
        $quote = $this->char;

        $this->advanceChar();

        if ($this->char === $quote) {
            return '';
        }

        $position = $this->position;

        while (true) {
            $prevChar = $this->char;

            $this->advanceChar();

            if ($this->char === $quote && $prevChar !== '\\') {
                break;
            }
        }

        $result = substr($this->input, $position, $this->position - $position);

        // remove slashes before quotes
        $result = str_replace('\\' . $quote, $quote, $result);

        return $result;
    }
}
