<?php

namespace Purestruct;

trait Functor
{
    abstract public function map(callable $f): static;
}
