<?php

namespace Purestruct;

trait Bind
{
    abstract public function bind(callable $f): static;

    public function join(): static
    {
        return $this->bind(static fn (self $b) => $b);
    }
}
