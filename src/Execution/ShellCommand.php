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

namespace DumpLoader\Execution;

use function Symfony\Component\String\u;

final readonly class ShellCommand
{
    /**
     * @param list<string>          $argv
     * @param array<string, string> $env
     * @param iterable<string>      $input
     * @param list<string>          $secrets
     */
    public function __construct(
        public array $argv,
        public array $env = [],
        public ?string $cwd = null,
        public iterable $input = new NoInput(),
        private array $secrets = [],
    ) {
    }

    /**
     * @param iterable<string> $input
     */
    public function withInput(iterable $input): self
    {
        return new self(argv: $this->argv, env: $this->env, cwd: $this->cwd, input: $input, secrets: $this->secrets);
    }

    /**
     * @param list<string> $argv
     * @param list<string> $secrets
     */
    public function withArguments(array $argv, array $secrets): self
    {
        return new self(argv: $argv, env: $this->env, cwd: $this->cwd, input: $this->input, secrets: [...$this->secrets, ...$secrets]);
    }

    public function display(): string
    {
        $maskedEnv = array_map(static fn (string $name): string => $name.'=***', array_keys($this->env));
        $displayed = implode(' ', [...$maskedEnv, ...$this->argv]);

        foreach (array_filter($this->secrets, static fn (string $secret): bool => '' !== $secret) as $secret) {
            $displayed = u($displayed)->replace($secret, '***')->toString();
        }

        return $displayed;
    }
}
