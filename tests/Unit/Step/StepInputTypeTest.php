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

namespace DumpLoader\Tests\Unit\Step;

use DumpLoader\Step\StepInputType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepInputTypeTest extends TestCase
{
    /**
     * @param string|int|list<int> $expected
     */
    #[Test]
    #[DataProvider('convertedEntries')]
    public function it_should_convert_an_entry_of_its_type(StepInputType $type, string $entry, string|int|array $expected): void
    {
        self::assertSame($expected, $type->tryConvert($entry));
    }

    /**
     * @return iterable<string, array{StepInputType, string, string|int|list<int>}>
     */
    public static function convertedEntries(): iterable
    {
        yield 'a trimmed text' => [StepInputType::String, '  ACME  ', 'ACME'];
        yield 'an integer' => [StepInputType::Int, ' 42 ', 42];
        yield 'a negative integer' => [StepInputType::Int, '-7', -7];
        yield 'an integer with leading zeros' => [StepInputType::Int, '007', 7];
        yield 'a list of integers' => [StepInputType::IntList, '12, 34', [12, 34]];
        yield 'a list of one integer' => [StepInputType::IntList, '12', [12]];
    }

    #[Test]
    #[DataProvider('refusedEntries')]
    public function it_should_refuse_an_entry_of_another_type(StepInputType $type, string $entry): void
    {
        self::assertNull($type->tryConvert($entry));
    }

    /**
     * @return iterable<string, array{StepInputType, string}>
     */
    public static function refusedEntries(): iterable
    {
        yield 'a word as an integer' => [StepInputType::Int, 'abc'];
        yield 'a decimal as an integer' => [StepInputType::Int, '1.5'];
        yield 'an integer beyond 18 digits' => [StepInputType::Int, '1234567890123456789'];
        yield 'a list with a word' => [StepInputType::IntList, '12, abc'];
        yield 'a list with an empty item' => [StepInputType::IntList, '12,,34'];
    }
}
