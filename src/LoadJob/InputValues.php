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

/**
 * @phpstan-import-type InputValueArray from InputValue
 *
 * @implements \IteratorAggregate<int, InputValue>
 */
final readonly class InputValues implements \IteratorAggregate, \Countable
{
    /**
     * @var list<InputValue>
     */
    private array $values;

    public function __construct(InputValue ...$values)
    {
        $this->values = array_values($values);
    }

    public static function none(): self
    {
        return new self();
    }

    public function merge(self $other): self
    {
        return new self(...$this->values, ...$other->values);
    }

    public function has(string $step, string $name): bool
    {
        return [] !== $this->matching($step, $name);
    }

    /**
     * @return string|int|list<int>
     */
    public function value(string $step, string $name): string|int|array
    {
        return $this->named($step, $name)->value();
    }

    public function argumentFor(string $step, string $name): string
    {
        return $this->has($step, $name) ? $this->named($step, $name)->asArgument() : '';
    }

    /**
     * @return list<string>
     */
    public function secretsOf(string $step): array
    {
        $secrets = array_filter(
            $this->values,
            static fn (InputValue $value): bool => $value->step === $step && $value->sensitive && !$value->isWithheld(),
        );

        return array_values(array_map(static fn (InputValue $value): string => $value->asArgument(), $secrets));
    }

    public function sensitive(): self
    {
        return new self(...array_filter($this->values, static fn (InputValue $value): bool => $value->sensitive));
    }

    public function revealedWith(self $kept): self
    {
        return new self(...array_map(
            static fn (InputValue $value): InputValue => $value->isWithheld() && $kept->has($value->step, $value->name)
                ? $kept->named($value->step, $value->name)
                : $value,
            $this->values,
        ));
    }

    public function isEmpty(): bool
    {
        return [] === $this->values;
    }

    /**
     * @return \ArrayIterator<int, InputValue>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->values);
    }

    public function count(): int
    {
        return \count($this->values);
    }

    /**
     * @return list<InputValueArray>
     */
    public function toArray(): array
    {
        return array_map(static fn (InputValue $value): array => $value->toArray(), $this->values);
    }

    /**
     * @return list<InputValueArray>
     */
    public function toRevealedArray(): array
    {
        return array_map(static fn (InputValue $value): array => $value->toRevealedArray(), $this->values);
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(...array_map(
            static fn (mixed $value): InputValue => \is_array($value)
                ? InputValue::fromArray($value)
                : throw new \InvalidArgumentException('Cannot read an input value that is not a map.'),
            array_values($data),
        ));
    }

    private function named(string $step, string $name): InputValue
    {
        return $this->matching($step, $name)[0] ?? throw MissingInputValue::notEntered($step, $name);
    }

    /**
     * @return list<InputValue>
     */
    private function matching(string $step, string $name): array
    {
        return array_values(array_filter($this->values, static fn (InputValue $value): bool => $value->isOf($step, $name)));
    }
}
