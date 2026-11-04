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

namespace DumpLoader\LoadJob;

use DumpLoader\Step\StepInputType;

final class InvalidStepInputs extends \RuntimeException
{
    /**
     * @param non-empty-list<string> $problems
     */
    private function __construct(
        public readonly array $problems,
    ) {
        parent::__construct(implode(' ', $problems));
    }

    public static function missing(string $step, string $input): self
    {
        return new self([\sprintf('The "%s" input of step "%s" is required.', $input, $step)]);
    }

    public static function wrongType(string $step, string $input, StepInputType $type): self
    {
        return new self([\sprintf('The "%s" input of step "%s" must be %s.', $input, $step, match ($type) {
            StepInputType::String => 'a text',
            StepInputType::Int => 'an integer',
            StepInputType::IntList => 'a comma-separated list of integers',
        })]);
    }

    public static function unknown(string $step, string $input): self
    {
        return new self([\sprintf('There is no "%s" input on step "%s" among the steps to run.', $input, $step)]);
    }

    public static function malformed(string $option): self
    {
        return new self([\sprintf('Cannot read the input "%s": write it as STEP.INPUT=VALUE.', $option)]);
    }

    /**
     * @param non-empty-list<self> $refusals
     */
    public static function together(array $refusals): self
    {
        return new self(array_merge(...array_map(static fn (self $refusal): array => $refusal->problems, $refusals)));
    }
}
