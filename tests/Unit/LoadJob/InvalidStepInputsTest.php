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

namespace DumpLoader\Tests\Unit\LoadJob;

use DumpLoader\LoadJob\InvalidStepInputs;
use DumpLoader\Step\StepInputType;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InvalidStepInputsTest extends TestCase
{
    #[Test]
    #[DataProvider('expectations')]
    public function it_should_tell_what_the_input_expects(StepInputType $type, string $message): void
    {
        self::assertSame($message, InvalidStepInputs::wrongType('restrict', 'insured_ids', $type)->getMessage());
    }

    /**
     * @return iterable<string, array{StepInputType, string}>
     */
    public static function expectations(): iterable
    {
        yield 'a text' => [StepInputType::String, 'The "insured_ids" input of step "restrict" must be a text.'];
        yield 'an integer' => [StepInputType::Int, 'The "insured_ids" input of step "restrict" must be an integer.'];
        yield 'a list' => [StepInputType::IntList, 'The "insured_ids" input of step "restrict" must be a comma-separated list of integers.'];
    }
}
