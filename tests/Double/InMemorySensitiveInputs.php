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

use DumpLoader\LoadJob\InputValues;
use DumpLoader\LoadJob\LoadJobId;
use DumpLoader\LoadJob\SensitiveInputs;

final class InMemorySensitiveInputs implements SensitiveInputs
{
    /**
     * @var array<string, InputValues>
     */
    private array $kept = [];

    public function keep(LoadJobId $id, InputValues $values): void
    {
        if (!$values->isEmpty()) {
            $this->kept[$id->value] = $values;
        }
    }

    public function take(LoadJobId $id): InputValues
    {
        $values = $this->kept[$id->value] ?? InputValues::none();
        $this->discard($id);

        return $values;
    }

    public function discard(LoadJobId $id): void
    {
        unset($this->kept[$id->value]);
    }

    public function holdsAnything(): bool
    {
        return [] !== $this->kept;
    }
}
