<?php

declare(strict_types=1);

use App\Exceptions\PayrollException;
use App\Services\Payroll\FormulaEvaluator;

beforeEach(fn () => $this->eval = new FormulaEvaluator);

it('evaluates arithmetic with correct precedence', function () {
    expect($this->eval->evaluate('2 + 3 * 4'))->toBe(14.0)
        ->and($this->eval->evaluate('(2 + 3) * 4'))->toBe(20.0)
        ->and($this->eval->evaluate('10 / 4'))->toBe(2.5)
        ->and($this->eval->evaluate('-5 + 2'))->toBe(-3.0)
        ->and($this->eval->evaluate('2.5 * 2'))->toBe(5.0);
});

it('resolves whitelisted variables', function () {
    expect($this->eval->evaluate('basic_salary * 0.15', ['basic_salary' => 100000]))->toBe(15000.0)
        ->and($this->eval->evaluate('overtime_hours * overtime_rate', ['overtime_hours' => 10, 'overtime_rate' => 250]))->toBe(2500.0);
});

it('supports the whitelisted functions', function () {
    expect($this->eval->evaluate('min(100, 80)'))->toBe(80.0)
        ->and($this->eval->evaluate('max(100, 80, 120)'))->toBe(120.0)
        ->and($this->eval->evaluate('round(10.567, 2)'))->toBe(10.57)
        ->and($this->eval->evaluate('floor(9.9)'))->toBe(9.0)
        ->and($this->eval->evaluate('abs(0 - 7)'))->toBe(7.0);
});

it('rejects an unknown variable', function () {
    $this->eval->evaluate('salary * 2', ['basic_salary' => 1]);
})->throws(PayrollException::class, 'Unknown variable');

it('rejects an unknown function', function () {
    $this->eval->evaluate('system("ls")');
})->throws(PayrollException::class);

it('rejects arbitrary PHP / illegal characters', function () {
    $this->eval->evaluate('phpinfo();');
})->throws(PayrollException::class);

it('rejects assignment attempts', function () {
    $this->eval->evaluate('basic_salary = 999', ['basic_salary' => 1]);
})->throws(PayrollException::class);

it('rejects division by zero', function () {
    $this->eval->evaluate('10 / 0');
})->throws(PayrollException::class, 'Division by zero');

it('rejects a dangling operator', function () {
    $this->eval->evaluate('5 +');
})->throws(PayrollException::class);
