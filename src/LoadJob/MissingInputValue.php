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

final class MissingInputValue extends \RuntimeException
{
    public static function notEntered(string $step, string $input): self
    {
        return new self(\sprintf('There is no value for the "%s" input of step "%s".', $input, $step));
    }

    public static function withheld(string $step, string $input): self
    {
        return new self(\sprintf('Cannot read the sensitive "%s" input of step "%s": its value is only kept until the load job starts.', $input, $step));
    }
}
