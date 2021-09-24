<?php

namespace Purestruct;

trait Applicative
{
    use Functor, Apply;

    abstract public static function pure(mixed $value): static;

    public function liftA1(callable $f): static
    {
        return static::pure($f)->apply($this);
    }

    public function map(callable $f): static
    {
        return $this->liftA1($f);
    }
}
