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

use DumpLoader\Execution\CommandFailed;
use DumpLoader\Execution\ProcessRunner;
use DumpLoader\Execution\ShellCommand;
use DumpLoader\LoadJob\InputValues;
use DumpLoader\LoadJob\LogTag;

final readonly class RunConsoleCommand implements ParameterizedStep
{
    /**
     * @param list<StepInput> $inputs
     */
    public function __construct(
        private ProcessRunner $processes,
        private StepDefinition $definition,
        private ShellCommand $command,
        private bool $enabledByDefault,
        private array $inputs = [],
    ) {
    }

    public function definition(): StepDefinition
    {
        return $this->definition;
    }

    public function enabledByDefault(): bool
    {
        return $this->enabledByDefault;
    }

    public function inputs(): array
    {
        return $this->inputs;
    }

    public function run(LoadContext $context, StepTracker $tracker): void
    {
        $tracker->log(LogTag::Run, $this->definition->label);

        $values = $context->options->inputs;
        $command = $this->command->withArguments(
            array_map(fn (string $argument): string => $this->filled($argument, $values), $this->command->argv),
            $values->secretsOf($this->definition->name),
        );

        try {
            $this->processes->run($command, $tracker)->ensureSuccess();
        } catch (CommandFailed $failure) {
            throw StepFailed::because(\sprintf('The "%s" command could not be run.', $this->definition->name), $failure);
        }

        $tracker->log(LogTag::Ok, \sprintf('The "%s" command has been run.', $this->definition->name));
    }

    private function filled(string $argument, InputValues $values): string
    {
        foreach ($this->inputs as $input) {
            $argument = $input->fill($argument, $this->definition->name, $values);
        }

        return $argument;
    }
}
