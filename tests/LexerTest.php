<?php

declare(strict_types=1);

namespace Serhii\Tests;

use Serhii\GoodbyeHtml\Lexer\Lexer;
use Serhii\GoodbyeHtml\Token\Token;
use Serhii\GoodbyeHtml\Token\TokenType;

class LexerTest extends TestCase
{
    public function testLexer(): void
    {
        $input = <<<HTML
        <div class="{{ \$show ? 'container' : '' }}">
        <h1 {{ \$classes }}>{{ \$heading }}</h1>
        <ul>
        {{ loop 1, 3 }}
        <li>{{ \$index }}</li>
        {{ end }}
        </ul>
        {{ if \$isOld }}
        <h1>I'm not a pro but it's only a matter of time</h1>
        {{ end }}
        </div>
        HTML;

        $tests = [
            new Token(TokenType::HTML, "<div class=\""),

            // Coalescing operator
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::VARIABLE, "show"),
            new Token(TokenType::QUESTION_MARK, "?"),
            new Token(TokenType::STRING, "container"),
            new Token(TokenType::COLON, ":"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            // End coalescing operator

            new Token(TokenType::HTML, "\">\n<h1 "),
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::VARIABLE, "classes"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            new Token(TokenType::HTML, ">"),
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::VARIABLE, "heading"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            new Token(TokenType::HTML, "</h1>\n<ul>\n"),

            // Loop statement
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::IDENTIFIER, "loop"),
            new Token(TokenType::INTEGER, "1"),
            new Token(TokenType::COMMA, ","),
            new Token(TokenType::INTEGER, "3"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            new Token(TokenType::HTML, "<li>"),
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::VARIABLE, "index"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            new Token(TokenType::HTML, "</li>\n"),
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::IDENTIFIER, "end"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            // End loop statement

            new Token(TokenType::HTML, "</ul>\n"),

            // If statement
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::IDENTIFIER, "if"),
            new Token(TokenType::VARIABLE, "isOld"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            new Token(TokenType::HTML, "<h1>I'm not a pro but it's only a matter of time</h1>\n"),
            new Token(TokenType::LEFT_BRACES, "{{"),
            new Token(TokenType::IDENTIFIER, "end"),
            new Token(TokenType::RIGHT_BRACES, "}}"),
            // End if statement

            new Token(TokenType::HTML, "</div>"),
            new Token(TokenType::EOF, ''),
        ];

        $lexer = new Lexer($input);

        foreach ($tests as $test) {
            $token = $lexer->nextToken();
            $this->assertEquals($test->literal, $token->literal);
            $this->assertEquals($test->type, $token->type);
        }
    }
}
