<?php

declare(strict_types=1);

namespace App\Services\Payroll;

use App\Exceptions\PayrollException;

/**
 * A deliberately tiny, safe arithmetic evaluator for salary-component formulas.
 *
 * Supports: decimal numbers, the operators + - * / and unary minus, parentheses,
 * whitelisted variables (resolved from the supplied context) and a fixed set of
 * pure functions (min, max, round, floor, ceil, abs). NOTHING else — no PHP
 * `eval`, no function references, no property/array access, no assignment.
 * Anything outside the grammar throws {@see PayrollException}.
 */
class FormulaEvaluator
{
    private const FUNCTIONS = ['min', 'max', 'round', 'floor', 'ceil', 'abs'];

    /** @var list<array{type: string, value: string}> */
    private array $tokens = [];

    private int $pos = 0;

    /** @var array<string, float> */
    private array $variables = [];

    /**
     * @param  array<string, int|float>  $variables
     */
    public function evaluate(string $expression, array $variables = []): float
    {
        $this->variables = array_map(static fn ($v): float => (float) $v, $variables);
        $this->tokens = $this->tokenize($expression);
        $this->pos = 0;

        if ($this->tokens === []) {
            return 0.0;
        }

        $result = $this->parseExpression();

        if ($this->pos < count($this->tokens)) {
            throw new PayrollException("Unexpected token in formula near \"{$this->tokens[$this->pos]['value']}\".");
        }

        return $result;
    }

    /**
     * @return list<array{type: string, value: string}>
     */
    private function tokenize(string $expression): array
    {
        $tokens = [];
        $length = strlen($expression);
        $i = 0;

        while ($i < $length) {
            $char = $expression[$i];

            if (ctype_space($char)) {
                $i++;

                continue;
            }

            if (str_contains('+-*/(),', $char)) {
                $tokens[] = ['type' => 'op', 'value' => $char];
                $i++;

                continue;
            }

            if (ctype_digit($char) || $char === '.') {
                $number = '';
                while ($i < $length && (ctype_digit($expression[$i]) || $expression[$i] === '.')) {
                    $number .= $expression[$i++];
                }
                $tokens[] = ['type' => 'number', 'value' => $number];

                continue;
            }

            if (ctype_alpha($char) || $char === '_') {
                $name = '';
                while ($i < $length && (ctype_alnum($expression[$i]) || $expression[$i] === '_')) {
                    $name .= $expression[$i++];
                }
                $tokens[] = ['type' => 'name', 'value' => $name];

                continue;
            }

            throw new PayrollException("Illegal character \"{$char}\" in formula.");
        }

        return $tokens;
    }

    private function parseExpression(): float
    {
        $value = $this->parseTerm();

        while ($this->match('+') || $this->match('-')) {
            $operator = $this->tokens[$this->pos - 1]['value'];
            $right = $this->parseTerm();
            $value = $operator === '+' ? $value + $right : $value - $right;
        }

        return $value;
    }

    private function parseTerm(): float
    {
        $value = $this->parseFactor();

        while ($this->match('*') || $this->match('/')) {
            $operator = $this->tokens[$this->pos - 1]['value'];

            $right = $this->parseFactor();

            if ($operator === '/') {
                if ($right === 0.0) {
                    throw new PayrollException('Division by zero in formula.');
                }
                $value /= $right;
            } else {
                $value *= $right;
            }
        }

        return $value;
    }

    private function parseFactor(): float
    {
        if ($this->match('-')) {
            return -$this->parseFactor();
        }

        if ($this->match('+')) {
            return $this->parseFactor();
        }

        if ($this->match('(')) {
            $value = $this->parseExpression();
            $this->expect(')');

            return $value;
        }

        $token = $this->tokens[$this->pos] ?? null;

        if ($token === null) {
            throw new PayrollException('Unexpected end of formula.');
        }

        if ($token['type'] === 'number') {
            $this->pos++;

            return (float) $token['value'];
        }

        if ($token['type'] === 'name') {
            $this->pos++;

            if ($this->match('(')) {
                return $this->parseFunction(strtolower($token['value']));
            }

            $name = $token['value'];

            if (! array_key_exists($name, $this->variables)) {
                throw new PayrollException("Unknown variable \"{$name}\" in formula.");
            }

            return $this->variables[$name];
        }

        throw new PayrollException("Unexpected token \"{$token['value']}\" in formula.");
    }

    private function parseFunction(string $name): float
    {
        if (! in_array($name, self::FUNCTIONS, true)) {
            throw new PayrollException("Unknown function \"{$name}()\" in formula.");
        }

        $args = [];

        if (! $this->check(')')) {
            do {
                $args[] = $this->parseExpression();
            } while ($this->match(','));
        }

        $this->expect(')');

        if ($args === []) {
            throw new PayrollException("Function \"{$name}()\" needs at least one argument.");
        }

        return match ($name) {
            'min' => min($args),
            'max' => max($args),
            'round' => round($args[0], (int) ($args[1] ?? 0)),
            'floor' => floor($args[0]),
            'ceil' => ceil($args[0]),
            'abs' => abs($args[0]),
        };
    }

    private function match(string $value): bool
    {
        if ($this->check($value)) {
            $this->pos++;

            return true;
        }

        return false;
    }

    private function check(string $value): bool
    {
        return ($this->tokens[$this->pos]['value'] ?? null) === $value;
    }

    private function expect(string $value): void
    {
        if (! $this->match($value)) {
            throw new PayrollException("Expected \"{$value}\" in formula.");
        }
    }
}
