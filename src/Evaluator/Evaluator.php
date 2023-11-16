<?php

declare(strict_types=1);

namespace Serhii\GoodbyeHtml\Evaluator;

use Serhii\GoodbyeHtml\Ast\Expressions\InfixExpression;
use Serhii\GoodbyeHtml\Ast\Expressions\PrefixExpression;
use Serhii\GoodbyeHtml\Ast\Expressions\TernaryExpression;
use Serhii\GoodbyeHtml\Ast\Expressions\VariableExpression;
use Serhii\GoodbyeHtml\Ast\Literals\BooleanLiteral;
use Serhii\GoodbyeHtml\Ast\Literals\FloatLiteral;
use Serhii\GoodbyeHtml\Ast\Literals\IntegerLiteral;
use Serhii\GoodbyeHtml\Ast\Literals\NullLiteral;
use Serhii\GoodbyeHtml\Ast\Literals\StringLiteral;
use Serhii\GoodbyeHtml\Ast\Node;
use Serhii\GoodbyeHtml\Ast\Statements\BlockStatement;
use Serhii\GoodbyeHtml\Ast\Statements\ExpressionStatement;
use Serhii\GoodbyeHtml\Ast\Statements\HtmlStatement;
use Serhii\GoodbyeHtml\Ast\Statements\IfStatement;
use Serhii\GoodbyeHtml\Ast\Statements\LoopStatement;
use Serhii\GoodbyeHtml\Ast\Statements\Program;
use Serhii\GoodbyeHtml\Obj\BlockObj;
use Serhii\GoodbyeHtml\Obj\BooleanObj;
use Serhii\GoodbyeHtml\Obj\Env;
use Serhii\GoodbyeHtml\Obj\ErrorObj;
use Serhii\GoodbyeHtml\Obj\FloatObj;
use Serhii\GoodbyeHtml\Obj\HtmlObj;
use Serhii\GoodbyeHtml\Obj\IntegerObj;
use Serhii\GoodbyeHtml\Obj\NullObj;
use Serhii\GoodbyeHtml\Obj\Obj;
use Serhii\GoodbyeHtml\Obj\ObjType;
use Serhii\GoodbyeHtml\Obj\StringObj;

readonly class Evaluator
{
    public function eval(Node $node, Env $env): Obj
    {
        return match (get_class($node)) {
            IntegerLiteral::class => new IntegerObj($node->value),
            FloatLiteral::class => new FloatObj($node->value),
            StringLiteral::class => new StringObj($node->value),
            BooleanLiteral::class => new BooleanObj($node->value),
            NullLiteral::class => new NullObj(),
            HtmlStatement::class => new HtmlObj($node->string()),
            IfStatement::class => $this->evalIfStatement($node, $env),
            BlockStatement::class => $this->evalBlockStatement($node, $env),
            LoopStatement::class => $this->evalLoopStatement($node, $env),
            ExpressionStatement::class => $this->eval($node->expression, $env),
            PrefixExpression::class => $this->evalPrefixExpression($node, $env),
            InfixExpression::class => $this->evalInfixExpression($node, $env),
            VariableExpression::class => $this->evalVariableExpression($node, $env),
            TernaryExpression::class => $this->evalTernaryExpression($node, $env),
            Program::class => $this->evalProgram($node, $env),
            ErrorObj::class => $node,
            default => EvalError::unknownType($node),
        };
    }

    private function evalProgram(Program $program, Env $env): Obj
    {
        $html = '';

        foreach ($program->statements as $stmt) {
            $stmtObj = $this->eval($stmt, $env);

            if ($stmtObj instanceof ErrorObj) {
                return $stmtObj;
            }

            $html .= $stmtObj->value();
        }

        return new HtmlObj($html);
    }

    private function evalPrefixExpression(PrefixExpression $node, Env $env): Obj
    {
        $right = $this->eval($node->right, $env);

        if ($right instanceof ErrorObj) {
            return $right;
        }

        return match ($node->operator) {
            '-' => $this->evalMinusPrefixOperatorExpression($right),
            '!' => new BooleanObj(! $right->value()),
            default => EvalError::operatorNotAllowed($node->operator, $right),
        };
    }

    private function evalInfixExpression(InfixExpression $node, Env $env): Obj
    {
        $right = $this->eval($node->right, $env);

        if ($right instanceof ErrorObj) {
            return $right;
        }

        $left = $this->eval($node->left, $env);

        if ($left instanceof ErrorObj) {
            return $left;
        }

        return match ($node->operator) {
            '.' => new StringObj($left->value() . $right->value()),
            '+' => $this->numberObject($left->value() + $right->value()),
            '-' => $this->numberObject($left->value() - $right->value()),
            '*' => $this->numberObject($left->value() * $right->value()),
            '/' => $this->numberObject($left->value() / $right->value()),
            '%' => $this->numberObject($left->value() % $right->value()),
            default => EvalError::operatorNotAllowed($node->operator, $right),
        };
    }

    private function numberObject(int|float $num): Obj
    {
        return is_int($num) ? new IntegerObj($num) : new FloatObj($num);
    }

    private function evalVariableExpression(VariableExpression $node, Env $env): Obj
    {
        $val = $env->get($node->value);

        return $val ?? EvalError::variableIsUndefined($node);
    }

    private function evalIfStatement(IfStatement $node, Env $env): Obj
    {
        $condition = $this->eval($node->condition, $env);

        if ($condition instanceof ErrorObj) {
            return $condition;
        }

        if ($condition->value()) {
            return $this->eval($node->consequence, $env);
        }

        return $this->eval($node->alternative, $env);
    }

    private function evalTernaryExpression(TernaryExpression $node, Env $env): Obj
    {
        $condition = $this->eval($node->condition, $env);

        if ($condition instanceof ErrorObj) {
            return $condition;
        }

        if ($condition->value()) {
            return $this->eval($node->consequence, $env);
        }

        return $this->eval($node->alternative, $env);
    }

    private function evalBlockStatement(BlockStatement $node, Env $env): Obj
    {
        /** @var Obj[] $elements */
        $elements = [];

        foreach ($node->statements as $stmt) {
            $stmtObj = $this->eval($stmt, $env);

            if ($stmtObj instanceof ErrorObj) {
                return $stmtObj;
            }

            $elements[] = $stmtObj;
        }

        return new BlockObj($elements);
    }

    private function evalLoopStatement(LoopStatement $node, Env $env): Obj
    {
        $html = '';

        $from = $this->eval($node->from, $env);

        if (!$from instanceof IntegerObj) {
            return EvalError::wrongArgumentType('loop', ObjType::INTEGER_OBJ, $from);
        }

        $to = $this->eval($node->to, $env);

        if (!$to instanceof IntegerObj) {
            return EvalError::wrongArgumentType('loop', ObjType::INTEGER_OBJ, $to);
        }

        for ($i = $from->value; $i <= $to->value; $i++) {
            $env->set('index', new IntegerObj($i));

            $block = $this->eval($node->body, $env);

            if ($block instanceof ErrorObj) {
                return $block;
            }

            $html .= $block->value();
        }

        return new HtmlObj($html);
    }

    private function evalMinusPrefixOperatorExpression(Obj $right): Obj
    {
        return match ($right->type()) {
            ObjType::INTEGER_OBJ => new IntegerObj(-$right->value()),
            ObjType::FLOAT_OBJ => new FloatObj(-$right->value()),
            default => EvalError::operatorNotAllowed('-', $right),
        };
    }
}
