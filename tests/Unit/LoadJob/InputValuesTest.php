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
use DumpLoader\LoadJob\MissingInputValue;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InputValuesTest extends TestCase
{
    #[Test]
    public function it_should_show_a_list_and_mask_a_sensitive_value(): void
    {
        self::assertSame('12, 34', InputValue::entered('restrict', 'insured_ids', [12, 34])->display());
        self::assertSame('***', InputValue::entered('set_password', 'password', 's3cr3t', sensitive: true)->display());
    }

    #[Test]
    public function it_should_refuse_to_read_a_withheld_value(): void
    {
        $this->expectExceptionMessage('Cannot read the sensitive "password" input of step "set_password": its value is only kept until the load job starts.');

        InputValue::withheld('set_password', 'password')->value();
    }

    #[Test]
    public function it_should_refuse_to_read_a_value_never_entered(): void
    {
        $this->expectException(MissingInputValue::class);

        InputValues::none()->value('restrict', 'insured_ids');
    }

    #[Test]
    public function it_should_reveal_the_withheld_values_with_the_ones_kept_aside(): void
    {
        $persisted = new InputValues(
            InputValue::entered('restrict', 'insured_ids', [12]),
            InputValue::withheld('set_password', 'password'),
        );

        $revealed = $persisted->revealedWith(new InputValues(InputValue::entered('set_password', 'password', 's3cr3t', sensitive: true)));

        self::assertSame('s3cr3t', $revealed->value('set_password', 'password'));
        self::assertSame([12], $revealed->value('restrict', 'insured_ids'));
        self::assertCount(2, $revealed);
    }

    #[Test]
    public function it_should_name_the_secrets_a_step_must_not_show(): void
    {
        $values = new InputValues(
            InputValue::entered('set_password', 'password', 's3cr3t', sensitive: true),
            InputValue::entered('set_password', 'login', 'admin'),
            InputValue::withheld('set_password', 'token'),
            InputValue::entered('other', 'password', 'elsewhere', sensitive: true),
        );

        self::assertSame(['s3cr3t'], $values->secretsOf('set_password'));
    }

    #[Test]
    public function it_should_keep_aside_only_the_sensitive_values(): void
    {
        $values = new InputValues(
            InputValue::entered('set_password', 'password', 's3cr3t', sensitive: true),
            InputValue::entered('set_password', 'login', 'admin'),
        );

        self::assertSame(
            [['step' => 'set_password', 'name' => 'password', 'sensitive' => true, 'value' => 's3cr3t']],
            $values->sensitive()->toRevealedArray(),
        );
    }

    #[Test]
    public function it_should_refuse_to_read_an_input_value_that_is_not_a_map(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        InputValues::fromArray(['insured_ids']);
    }
}
