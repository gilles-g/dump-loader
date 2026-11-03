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

namespace DumpLoader\Tests\Double;

use DumpLoader\LoadJob\LogTag;
use DumpLoader\LoadJob\Percentage;
use DumpLoader\Step\StepTracker;

final class RecordingStepTracker implements StepTracker
{
    /**
     * @var list<string>
     */
    public array $messages = [];

    /**
     * @param (\Closure(): bool)|null $abortWhen
     */
    private function __construct(
        private readonly ?\Closure $abortWhen,
    ) {
    }

    public static function silent(): self
    {
        return new self(abortWhen: null);
    }

    public static function abortingWhen(\Closure $condition): self
    {
        return new self(abortWhen: $condition);
    }

    public function log(LogTag $tag, string $message): void
    {
        $this->messages[] = $message;
    }

    public function progress(Percentage $percentage): void
    {
    }

    public function abortRequested(): bool
    {
        return null !== $this->abortWhen && ($this->abortWhen)();
    }
}
