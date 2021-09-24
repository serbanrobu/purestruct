<?php

namespace Purestruct;

use JsonSerializable;

class Pair implements JsonSerializable
{
    public function __construct(public mixed $first, public mixed $second)
    {
    }

    public function jsonSerialize(): array
    {
        return [$this->first, $this->second];
    }
}
