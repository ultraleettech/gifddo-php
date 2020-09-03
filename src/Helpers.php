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

namespace Gifddo;

class Helpers
{
    /**
     * Create packed string from concatenated message parameters.
     *
     * @param array $params
     *
     * @return string
     */
    public static function pack(array $params): string
    {
        return array_reduce(
            $params,
            static function ($memo, string $value) {
                return $memo . str_pad((string) strlen($value), 3, '0', \STR_PAD_LEFT) . $value;
            },
            ''
        );
    }

    /**
     * Generate random string of specified length.
     *
     * @param int $length
     *
     * @return string
     */
    public static function randomString(int $length = 20)
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz0123456789';
        for ($result = ''; strlen($result) < $length;) {
            $result .= $characters[rand(0, strlen($characters) - 1)];
        }
        return $result;
    }

    /**
     * @return string
     */
    public static function dateString(): string
    {
        return date('Y-m-d') . 'T' . date('H:i:sO');
    }
}
