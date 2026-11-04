<?php

declare(strict_types=1);

/*
 * This file is part of the gilles-g/dump-loader-bundle package.
 *
 * (c) gilles-g <ggauthier@spiriit.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace DumpLoader\Step;

use function Symfony\Component\String\u;

enum StepInputType: string
{
    case String = 'string';
    case Int = 'int';
    case IntList = 'int_list';

    /**
     * @return string|int|list<int>|null
     */
    public function tryConvert(string $entry): string|int|array|null
    {
        $trimmed = u($entry)->trim()->toString();

        return match ($this) {
            self::String => $trimmed,
            self::Int => self::toInt($trimmed),
            self::IntList => self::toIntList($trimmed),
        };
    }

    private static function toInt(string $entry): ?int
    {
        return 1 === preg_match('/^-?\d{1,18}$/', $entry) ? (int) $entry : null;
    }

    /**
     * @return list<int>|null
     */
    private static function toIntList(string $entry): ?array
    {
        $integers = [];

        foreach (u($entry)->split(',') as $item) {
            $integer = self::toInt($item->trim()->toString());

            if (null === $integer) {
                return null;
            }

            $integers[] = $integer;
        }

        return $integers;
    }
}
