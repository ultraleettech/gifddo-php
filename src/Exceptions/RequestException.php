<?php
/**
 * Gifddo PHP Client
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of
 * this software and associated documentation files (the "Software"), to deal in
 * the Software without restriction, including without limitation the rights to
 * use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 * the Software, and to permit persons to whom the Software is furnished to do so,
 * subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * @author Rene Aavik <renx81@gmail.com>
 * @copyright 2020-present Gifddo
 *
 * @link https://gifddo.com/
 */

declare(strict_types=1);

namespace Gifddo\Exceptions;

use Throwable;

class RequestException extends \Exception
{
    public function __construct(string $message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct("Error making a request to the Gifddo API: $message", $code, $previous);
    }
}
