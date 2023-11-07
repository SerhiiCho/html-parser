<?php

declare(strict_types=1);

namespace Serhii\GoodbyeHtml\Evaluator;

use Serhii\GoodbyeHtml\Ast\ExpressionStatement;
use Serhii\GoodbyeHtml\Ast\IntegerLiteral;
use Serhii\GoodbyeHtml\Obj\Env;
use Serhii\GoodbyeHtml\Obj\Obj;
use Serhii\GoodbyeHtml\Ast\Node;
use Serhii\GoodbyeHtml\Ast\PrefixExpression;
use Serhii\GoodbyeHtml\Ast\Program;
use Serhii\GoodbyeHtml\Obj\Err;
use Serhii\GoodbyeHtml\Obj\Integer;
use Serhii\GoodbyeHtml\Obj\ObjType;

readonly class Evaluator
{
    public function eval(Node $node, Env $env): Obj|null
    {
        if ($node instanceof Program) {
            return $this->evalProgram($node, $env);
        } elseif ($node instanceof ExpressionStatement) {
            return $this->eval($node->expression, $env);
        } elseif ($node instanceof IntegerLiteral) {
            return new Integer($node->value);
        } elseif ($node instanceof PrefixExpression) {
            $right = $this->eval($node->right, $env);

            if ($right instanceof Err) {
                return $right;
            }

            return $this->evalPrefixExpression($node->operator, $right);
        }

        return null;
    }

    private function evalProgram(Program $program, Env $env): Obj
    {
        $result = null;

        foreach ($program->statements as $stmt) {
            $result = $this->eval($stmt, $env);

            if ($result instanceof Err) {
                return $result;
            }
        }

        return $result;
    }

    private function evalPrefixExpression(string $operator, Obj $right): Obj
    {
        if ($operator === '-') {
            return $this->evalMinusPrefixOperatorExpression($right);
        }

        return new Err(sprintf('Unknown operator: %s%s', $operator, $right->type()));
    }

    private function evalMinusPrefixOperatorExpression(Obj $right): Obj
    {
        if ($right->type() !== ObjType::INTEGER_OBJ) {
            return new Err(sprintf('Unknown operator: -%s', $right->type()));
        }

        return new Integer(-$right->value);
    }
}
