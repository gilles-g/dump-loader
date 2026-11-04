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

use DumpLoader\Step\ParameterizedStep;
use DumpLoader\Step\Step;
use DumpLoader\Step\StepInput;

use function Symfony\Component\String\u;

final readonly class InputEntries
{
    /**
     * @param array<string, array<string, string>> $entries
     */
    public function __construct(
        private array $entries = [],
    ) {
    }

    /**
     * @param array<mixed> $form
     */
    public static function fromForm(array $form): self
    {
        $entries = [];

        foreach ($form as $step => $inputs) {
            foreach (\is_array($inputs) ? $inputs : [] as $input => $entry) {
                if (\is_string($step) && \is_string($input) && \is_string($entry)) {
                    $entries[$step][$input] = $entry;
                }
            }
        }

        return new self($entries);
    }

    /**
     * @param list<string> $options
     */
    public static function fromCommandLine(array $options): self
    {
        $entries = new self();

        foreach ($options as $option) {
            if (1 !== preg_match('/^([a-z0-9_]+)\.([a-z0-9_]+)=(.*)$/s', $option, $parts)) {
                throw InvalidStepInputs::malformed($option);
            }

            $entries = $entries->with($parts[1], $parts[2], $parts[3]);
        }

        return $entries;
    }

    public function with(string $step, string $input, string $entry): self
    {
        $entries = $this->entries;
        $entries[$step][$input] = $entry;

        return new self($entries);
    }

    public function has(string $step, string $input): bool
    {
        return !u($this->entries[$step][$input] ?? '')->trim()->isEmpty();
    }

    public function restrictedTo(LoadOptions $options): self
    {
        return new self(array_filter(
            $this->entries,
            $options->includes(...),
            \ARRAY_FILTER_USE_KEY,
        ));
    }

    /**
     * @param list<Step> $planned
     */
    public function valuesFor(array $planned): InputValues
    {
        $declared = self::declaredBy($planned);
        $refusals = $this->unknownTo($declared);
        $values = InputValues::none();

        foreach ($declared as $step => $inputs) {
            foreach ($inputs as $input) {
                try {
                    $values = $values->merge($input->read($step, $this->entries[$step][$input->name] ?? ''));
                } catch (InvalidStepInputs $refusal) {
                    $refusals[] = $refusal;
                }
            }
        }

        if ([] !== $refusals) {
            throw InvalidStepInputs::together($refusals);
        }

        return $values;
    }

    /**
     * @param list<Step> $planned
     *
     * @return array<string, list<StepInput>>
     */
    private static function declaredBy(array $planned): array
    {
        $declared = [];

        foreach ($planned as $step) {
            if ($step instanceof ParameterizedStep) {
                $declared[$step->definition()->name] = $step->inputs();
            }
        }

        return $declared;
    }

    /**
     * @param array<string, list<StepInput>> $declared
     *
     * @return list<InvalidStepInputs>
     */
    private function unknownTo(array $declared): array
    {
        $refusals = [];

        foreach ($this->entries as $step => $inputs) {
            $names = array_map(static fn (StepInput $input): string => $input->name, $declared[$step] ?? []);

            foreach (array_diff(array_keys($inputs), $names) as $unknown) {
                $refusals[] = InvalidStepInputs::unknown($step, $unknown);
            }
        }

        return $refusals;
    }
}
