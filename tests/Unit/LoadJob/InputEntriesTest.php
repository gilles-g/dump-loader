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

use DumpLoader\LoadJob\InputEntries;
use DumpLoader\LoadJob\InvalidStepInputs;
use DumpLoader\LoadJob\LoadOptions;
use DumpLoader\Step\StepInput;
use DumpLoader\Step\StepInputType;
use DumpLoader\Tests\Double\FakeParameterizedStep;
use DumpLoader\Tests\Double\FakeStep;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class InputEntriesTest extends TestCase
{
    #[Test]
    public function it_should_read_the_values_of_the_planned_steps(): void
    {
        $values = new InputEntries(['restrict' => ['insured_ids' => '12, 34', 'label' => 'Sample']])->valuesFor($this->planned());

        self::assertSame([12, 34], $values->value('restrict', 'insured_ids'));
        self::assertSame('Sample', $values->value('restrict', 'label'));
    }

    #[Test]
    public function it_should_refuse_every_problem_at_once(): void
    {
        try {
            new InputEntries(['restrict' => ['insured_ids' => 'abc', 'unknown' => '1'], 'not_planned' => ['user_id' => '42']])
                ->valuesFor($this->planned());
            self::fail('The entries should have been refused.');
        } catch (InvalidStepInputs $refusal) {
            self::assertSame([
                'There is no "unknown" input on step "restrict" among the steps to run.',
                'There is no "user_id" input on step "not_planned" among the steps to run.',
                'The "insured_ids" input of step "restrict" must be a comma-separated list of integers.',
            ], $refusal->problems);
        }
    }

    #[Test]
    public function it_should_read_the_entries_given_on_the_command_line(): void
    {
        $entries = InputEntries::fromCommandLine(['restrict.insured_ids=12, 34', 'restrict.label=a=b']);

        self::assertSame('a=b', $entries->valuesFor($this->planned())->value('restrict', 'label'));
    }

    #[Test]
    public function it_should_refuse_a_command_line_entry_without_its_step(): void
    {
        $this->expectExceptionMessage('Cannot read the input "insured_ids=12": write it as STEP.INPUT=VALUE.');

        InputEntries::fromCommandLine(['insured_ids=12']);
    }

    #[Test]
    public function it_should_keep_only_the_text_fields_of_a_form(): void
    {
        $entries = InputEntries::fromForm(['restrict' => ['insured_ids' => '12', 'label' => ['nested']], 'clear_caches' => 'on']);

        self::assertTrue($entries->has('restrict', 'insured_ids'));
        self::assertFalse($entries->has('restrict', 'label'));
        self::assertFalse($entries->has('clear_caches', 'on'));
    }

    #[Test]
    public function it_should_drop_the_entries_of_the_steps_left_unchecked(): void
    {
        $entries = new InputEntries(['restrict' => ['insured_ids' => '12'], 'assign_user' => ['user_id' => '42']])
            ->restrictedTo(LoadOptions::fromNames(['restrict']));

        self::assertTrue($entries->has('restrict', 'insured_ids'));
        self::assertFalse($entries->has('assign_user', 'user_id'));
    }

    #[Test]
    public function it_should_not_count_a_blank_entry_as_entered(): void
    {
        self::assertFalse(new InputEntries()->with('restrict', 'insured_ids', '  ')->has('restrict', 'insured_ids'));
    }

    /**
     * @return list<FakeStep>
     */
    private function planned(): array
    {
        return [
            FakeStep::named('restore_database', 100),
            FakeParameterizedStep::named('restrict', 225)->declaring(
                new StepInput('insured_ids', 'Insured ids', StepInputType::IntList),
                new StepInput('label', 'Label', required: false),
            ),
        ];
    }
}
