<?php

namespace Purestruct;

use ReflectionFunction;

function curry(callable $f): callable
{
    return curryHelp($f, (new ReflectionFunction($f))->getNumberOfParameters());
}

function curryHelp(callable $f, int $numberOfParameters): callable
{
    return match ($numberOfParameters) {
        0, 1 => $f,
        default => static fn (mixed $a) => curryHelp(
            static fn (mixed ...$rest) => $f($a, ...$rest),
            $numberOfParameters - 1,
        ),
    };
}

function dd(mixed ...$xs)
{
    foreach ($xs as $x) {
        var_dump($x);
    }

    die();
}
