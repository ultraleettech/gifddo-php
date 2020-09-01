<?php

namespace Gifddo\Exceptions;

use Throwable;

class RequestException extends \Exception
{
    public function __construct(string $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct("Error making a request to the Gifddo API: $message", $code, $previous);
    }
}
