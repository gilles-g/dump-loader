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

interface SensitiveInputs
{
    public function keep(LoadJobId $id, InputValues $values): void;

    public function take(LoadJobId $id): InputValues;

    public function discard(LoadJobId $id): void;
}
