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

use DumpLoader\LoadJob\InputValue;
use DumpLoader\LoadJob\InputValues;
use DumpLoader\LoadJob\InvalidStepInputs;

use function Symfony\Component\String\u;

final readonly class StepInput
{
    public function __construct(
        public string $name,
        public string $label,
        public StepInputType $type = StepInputType::String,
        public bool $required = true,
        public bool $sensitive = false,
        public string $description = '',
    ) {
        if (1 !== preg_match('/^[a-z0-9_]+$/', $this->name)) {
            throw new \InvalidArgumentException(\sprintf('Cannot name a step input "%s": use lowercase letters, digits and underscores only.', $this->name));
        }
    }

    public function read(string $step, string $entry): InputValues
    {
        if (u($entry)->trim()->isEmpty() && $this->required) {
            throw InvalidStepInputs::missing($step, $this->name);
        }

        if (u($entry)->trim()->isEmpty()) {
            return InputValues::none();
        }

        $value = $this->type->tryConvert($entry) ?? throw InvalidStepInputs::wrongType($step, $this->name, $this->type);

        return new InputValues(InputValue::entered($step, $this->name, $value, $this->sensitive));
    }

    public function fill(string $argument, string $step, InputValues $values): string
    {
        $marker = '{'.$this->name.'}';

        if (!u($argument)->containsAny($marker)) {
            return $argument;
        }

        return u($argument)->replace($marker, $values->argumentFor($step, $this->name))->toString();
    }
}
