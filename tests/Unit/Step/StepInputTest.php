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

namespace DumpLoader\Tests\Unit\Step;

use DumpLoader\LoadJob\InputValue;
use DumpLoader\LoadJob\InputValues;
use DumpLoader\LoadJob\InvalidStepInputs;
use DumpLoader\LoadJob\MissingInputValue;
use DumpLoader\Step\StepInput;
use DumpLoader\Step\StepInputType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class StepInputTest extends TestCase
{
    #[Test]
    public function it_should_refuse_a_name_that_cannot_be_a_marker(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new StepInput('user.id', 'User id');
    }

    #[Test]
    public function it_should_read_an_entry_into_a_value_of_its_type(): void
    {
        $values = new StepInput('user_id', 'User id', StepInputType::Int)->read('assign_user', '42');

        self::assertSame(42, $values->value('assign_user', 'user_id'));
    }

    #[Test]
    public function it_should_require_an_entry_by_default(): void
    {
        $this->expectExceptionMessage('The "user_id" input of step "assign_user" is required.');

        new StepInput('user_id', 'User id', StepInputType::Int)->read('assign_user', '  ');
    }

    #[Test]
    public function it_should_read_nothing_from_an_optional_input_left_blank(): void
    {
        self::assertTrue(new StepInput('user_id', 'User id', required: false)->read('assign_user', '')->isEmpty());
    }

    #[Test]
    public function it_should_refuse_an_entry_of_another_type(): void
    {
        try {
            new StepInput('user_id', 'User id', StepInputType::Int)->read('assign_user', 'abc');
            self::fail('The entry should have been refused.');
        } catch (InvalidStepInputs $refusal) {
            self::assertSame(['The "user_id" input of step "assign_user" must be an integer.'], $refusal->problems);
        }
    }

    #[Test]
    public function it_should_fill_its_marker_with_the_entered_value(): void
    {
        $values = new InputValues(InputValue::entered('restrict', 'insured_ids', [12, 34]));

        self::assertSame('--ids=12,34', new StepInput('insured_ids', 'Insured ids', StepInputType::IntList)->fill('--ids={insured_ids}', 'restrict', $values));
    }

    #[Test]
    public function it_should_empty_its_marker_when_no_value_was_entered(): void
    {
        self::assertSame('--user=', new StepInput('user_id', 'User id', required: false)->fill('--user={user_id}', 'assign_user', InputValues::none()));
    }

    #[Test]
    public function it_should_leave_an_argument_without_its_marker_untouched(): void
    {
        $values = new InputValues(InputValue::withheld('assign_user', 'user_id'));

        self::assertSame('--force', new StepInput('user_id', 'User id')->fill('--force', 'assign_user', $values));
    }

    #[Test]
    public function it_should_refuse_to_fill_its_marker_with_a_withheld_value(): void
    {
        $this->expectException(MissingInputValue::class);

        new StepInput('password', 'Password', sensitive: true)->fill('--password={password}', 'set_password', new InputValues(InputValue::withheld('set_password', 'password')));
    }
}
