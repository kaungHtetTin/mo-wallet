<?php
declare(strict_types=1);
namespace App\Services;

/** Exact two-place money arithmetic. Values stay decimal strings throughout. */
final class Money
{
    public static function add(string|int $left, string|int $right): string
    {
        return bcadd((string) $left, (string) $right, 2);
    }

    public static function subtract(string|int $left, string|int $right): string
    {
        return bcsub((string) $left, (string) $right, 2);
    }

    public static function compare(string|int $left, string|int $right): int
    {
        return bccomp((string) $left, (string) $right, 2);
    }

    public static function sum(array $amounts): string
    {
        return array_reduce($amounts, fn ($total, $amount) => self::add($total, $amount), '0.00');
    }

    public static function format(string|int|null $amount): string
    {
        [$whole, $fraction] = explode('.', self::add($amount ?? '0', '0'));
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $whole) . '.' . $fraction;
    }
}
