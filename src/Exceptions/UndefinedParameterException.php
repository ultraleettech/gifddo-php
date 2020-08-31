<?php

namespace Gifddo\Exceptions;

use Throwable;
use InvalidArgumentException;

class UndefinedParameterException extends InvalidArgumentException
{
    public function __construct(string $parameter = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct("Undefined parameter: $parameter", $code, $previous);
    }
}
