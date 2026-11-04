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

namespace DumpLoader\Tests\Unit\LoadJob;

use DumpLoader\LoadJob\InputValue;
use DumpLoader\LoadJob\InputValues;
use DumpLoader\LoadJob\LoadOptions;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class LoadOptionsTest extends TestCase
{
    #[Test]
    public function it_should_add_an_option_it_does_not_include_yet(): void
    {
        self::assertSame(['run_migrations', 'clear_caches'], LoadOptions::fromNames(['run_migrations'])->toggled('clear_caches')->names);
    }

    #[Test]
    public function it_should_remove_an_option_it_already_includes(): void
    {
        self::assertSame(['clear_caches'], LoadOptions::fromNames(['run_migrations', 'clear_caches'])->toggled('run_migrations')->names);
    }

    #[Test]
    public function it_should_keep_the_entered_inputs_while_options_are_toggled(): void
    {
        $options = LoadOptions::fromNames(['restrict'])
            ->withInputs(new InputValues(InputValue::entered('restrict', 'insured_ids', [12, 34])))
            ->toggled('clear_caches')
            ->toggled('clear_caches');

        self::assertTrue($options->hasInput('restrict', 'insured_ids'));
        self::assertSame([12, 34], $options->input('restrict', 'insured_ids'));
        self::assertFalse($options->hasInput('restrict', 'label'));
    }
}
