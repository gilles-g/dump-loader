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
 * @phpstan-type InputValueArray array{step: string, name: string, sensitive: bool, value?: string|int|list<int>}
 */
final readonly class InputValue
{
    private const string MASK = '***';

    /**
     * @param string|int|list<int>|null $value
     */
    private function __construct(
        public string $step,
        public string $name,
        public bool $sensitive,
        private string|int|array|null $value,
    ) {
    }

    /**
     * @param string|int|list<int> $value
     */
    public static function entered(string $step, string $name, string|int|array $value, bool $sensitive = false): self
    {
        return new self($step, $name, $sensitive, $value);
    }

    public static function withheld(string $step, string $name): self
    {
        return new self($step, $name, true, null);
    }

    public function isOf(string $step, string $name): bool
    {
        return $this->step === $step && $this->name === $name;
    }

    public function isWithheld(): bool
    {
        return null === $this->value;
    }

    /**
     * @return string|int|list<int>
     */
    public function value(): string|int|array
    {
        return $this->value ?? throw MissingInputValue::withheld($this->step, $this->name);
    }

    public function display(): string
    {
        return $this->sensitive ? self::MASK : $this->joined(', ');
    }

    public function asArgument(): string
    {
        if ($this->isWithheld()) {
            throw MissingInputValue::withheld($this->step, $this->name);
        }

        return $this->joined(',');
    }

    /**
     * @return InputValueArray
     */
    public function toArray(): array
    {
        if ($this->sensitive) {
            return ['step' => $this->step, 'name' => $this->name, 'sensitive' => true];
        }

        return $this->toRevealedArray();
    }

    /**
     * @return InputValueArray
     */
    public function toRevealedArray(): array
    {
        return ['step' => $this->step, 'name' => $this->name, 'sensitive' => $this->sensitive, 'value' => $this->value()];
    }

    /**
     * @param array<mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $step = $data['step'] ?? null;
        $name = $data['name'] ?? null;
        $sensitive = $data['sensitive'] ?? null;

        if (!\is_string($step) || !\is_string($name) || !\is_bool($sensitive)) {
            throw new \InvalidArgumentException('Cannot read an input value without its step, name and sensitivity.');
        }

        if (!\array_key_exists('value', $data) && $sensitive) {
            return self::withheld($step, $name);
        }

        return self::entered($step, $name, self::readValue($data['value'] ?? null, $step, $name), $sensitive);
    }

    /**
     * @return string|int|list<int>
     */
    private static function readValue(mixed $value, string $step, string $name): string|int|array
    {
        if (\is_string($value) || \is_int($value)) {
            return $value;
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw self::unreadable($step, $name);
        }

        $integers = array_values(array_filter($value, \is_int(...)));

        if (\count($integers) !== \count($value)) {
            throw self::unreadable($step, $name);
        }

        return $integers;
    }

    private static function unreadable(string $step, string $name): \InvalidArgumentException
    {
        return new \InvalidArgumentException(\sprintf('Cannot read the value of the "%s" input of step "%s".', $name, $step));
    }

    private function joined(string $separator): string
    {
        $value = $this->value();

        return \is_array($value) ? implode($separator, $value) : (string) $value;
    }
}
