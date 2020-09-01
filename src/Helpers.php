<?php

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
            static function ($memo, $value) {
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
