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

use DumpLoader\Step\LoadContext;
use DumpLoader\Step\ParameterizedStep;
use DumpLoader\Step\StepInput;
use DumpLoader\Step\StepTracker;

final class FakeParameterizedStep extends FakeStep implements ParameterizedStep
{
    public ?LoadContext $lastContext = null;

    /**
     * @var list<StepInput>
     */
    private array $inputs = [];

    public function declaring(StepInput ...$inputs): self
    {
        $this->inputs = array_values($inputs);

        return $this;
    }

    public function enabledByDefault(): bool
    {
        return false;
    }

    public function inputs(): array
    {
        return $this->inputs;
    }

    public function run(LoadContext $context, StepTracker $tracker): void
    {
        $this->lastContext = $context;

        parent::run($context, $tracker);
    }
}
