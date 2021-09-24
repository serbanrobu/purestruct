<?php

namespace Purestruct;

trait Monad
{
    use Applicative, Bind;

    public function map(callable $f): static
    {
        return $this->liftM1($f);
    }

    public function liftM1(callable $f): static
    {
        return $this->bind(static fn (mixed $v) => static::pure($f($v)));
    }

    public function ap(self $m): static
    {
        return $m->bind(
            fn (mixed $m_) => $this->bind(static fn (mixed $v) => static::pure($m_($v))),
        );
    }
}
