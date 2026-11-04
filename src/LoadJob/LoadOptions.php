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

final readonly class LoadOptions
{
    /**
     * @param list<string> $names
     */
    private function __construct(
        public array $names,
        public InputValues $inputs,
    ) {
    }

    public static function none(): self
    {
        return new self([], InputValues::none());
    }

    /**
     * @param list<string> $names
     */
    public static function fromNames(array $names): self
    {
        return new self(array_values(array_unique(array_filter($names, static fn (string $name): bool => '' !== $name))), InputValues::none());
    }

    public function withInputs(InputValues $inputs): self
    {
        return new self($this->names, $inputs);
    }

    public function revealedWith(InputValues $kept): self
    {
        return new self($this->names, $this->inputs->revealedWith($kept));
    }

    public function toggled(string $name): self
    {
        if ($this->includes($name)) {
            return new self(array_values(array_filter($this->names, static fn (string $included): bool => $included !== $name)), $this->inputs);
        }

        return self::fromNames([...$this->names, $name])->withInputs($this->inputs);
    }

    public function includes(string $name): bool
    {
        return \in_array($name, $this->names, true);
    }

    public function hasInput(string $step, string $name): bool
    {
        return $this->inputs->has($step, $name);
    }

    /**
     * @return string|int|list<int>
     */
    public function input(string $step, string $name): string|int|array
    {
        return $this->inputs->value($step, $name);
    }
}
