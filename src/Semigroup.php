<?php

namespace Purestruct;

trait Semigroup
{
    abstract public function combine(self $other): static;
}
