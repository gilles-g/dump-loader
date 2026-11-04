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

namespace DumpLoader\Tests\Integration;

use DumpLoader\Execution\ShellCommand;
use DumpLoader\LoadJob\LoadJobRepository;
use DumpLoader\Tests\Double\InMemoryDatabaseClient;
use DumpLoader\Tests\Double\RecordingProcessRunner;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

use function Symfony\Component\String\u;

final class StepInputsTest extends WebTestCase
{
    use BootsTestKernel;

    private const string SECRET = 's3cr3t-value';

    private KernelBrowser $client;

    protected function setUp(): void
    {
        parent::setUp();
        self::ensureKernelShutdown();

        $this->client = self::createClient(['config' => 'inputs']);

        self::purgeDumpLoaderVarDir();
    }

    protected function tearDown(): void
    {
        self::ensureKernelShutdown();
        parent::tearDown();
    }

    #[Test]
    public function it_should_offer_a_field_for_each_input_of_an_option(): void
    {
        $crawler = $this->client->request('GET', '/dump-loader/');

        self::assertCount(1, $crawler->filter('input[name="inputs[assign_user][user_id]"][type="text"]'));
        self::assertCount(1, $crawler->filter('input[name="inputs[set_password][password]"][type="password"]'));
    }

    #[Test]
    public function it_should_hand_the_user_id_entered_in_the_dashboard_to_the_command(): void
    {
        $this->requestFromTheDashboard(['assign_user' => ['user_id' => '42']]);

        self::assertResponseRedirects('/dump-loader/monitor');
        self::assertContains('--user=42', $this->ranCommand('app:some-command')->argv);
    }

    #[Test]
    #[DataProvider('refusedUserIds')]
    public function it_should_refuse_a_user_id_entered_in_the_dashboard_without_touching_the_database(string $userId, string $problem): void
    {
        $this->requestFromTheDashboard(['assign_user' => ['user_id' => $userId]]);

        self::assertResponseRedirects('/dump-loader/');
        self::assertNull($this->database()->droppedFrom());
        self::assertSame([], $this->runner()->commands);
        self::assertTrue(self::service(LoadJobRepository::class)->history(10)->isEmpty());

        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert', $problem);
    }

    #[Test]
    public function it_should_hand_the_user_id_given_on_the_command_line_to_the_command(): void
    {
        $tester = $this->loadFromTheCommandLine('assign_user', ['assign_user.user_id=42']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('--user=42', $this->ranCommand('app:some-command')->argv);
    }

    /**
     * @param list<string> $inputs
     */
    #[Test]
    #[DataProvider('refusedUserIdsOnTheCommandLine')]
    public function it_should_refuse_a_user_id_given_on_the_command_line_without_touching_the_database(array $inputs, string $problem): void
    {
        $tester = $this->loadFromTheCommandLine('assign_user', $inputs);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString($problem, (string) preg_replace('/\s+/', ' ', $tester->getDisplay()));
        self::assertNull($this->database()->droppedFrom());
        self::assertSame([], $this->runner()->commands);
        self::assertTrue(self::service(LoadJobRepository::class)->history(10)->isEmpty());
    }

    #[Test]
    public function it_should_keep_a_sensitive_input_out_of_the_job_files_the_report_and_the_log(): void
    {
        $tester = $this->loadFromTheCommandLine('set_password', ['set_password.password='.self::SECRET]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertContains('--password='.self::SECRET, $this->ranCommand('app:set-password')->argv);
        self::assertStringNotContainsString(self::SECRET, $this->ranCommand('app:set-password')->display());
        self::assertStringContainsString('set_password.password = ***', $tester->getDisplay());
        self::assertStringNotContainsString(self::SECRET, $tester->getDisplay());

        $jobFiles = glob($this->jobsDir().'/*') ?: [];

        self::assertNotEmpty($jobFiles);
        self::assertSame([], array_filter($jobFiles, static fn (string $path): bool => u($path)->endsWith('.inputs')));

        foreach ($jobFiles as $path) {
            self::assertStringNotContainsString(self::SECRET, (string) file_get_contents($path));
        }
    }

    #[Test]
    public function it_should_show_the_inputs_in_the_history_with_the_sensitive_ones_masked(): void
    {
        $this->requestFromTheDashboard(['assign_user' => ['user_id' => '42'], 'set_password' => ['password' => self::SECRET]]);

        $this->client->request('GET', '/dump-loader/');

        self::assertSelectorTextContains('table', 'assign_user.user_id = 42');
        self::assertSelectorTextContains('table', 'set_password.password = ***');
        self::assertStringNotContainsString(self::SECRET, (string) $this->client->getResponse()->getContent());
    }

    #[Test]
    public function it_should_ignore_the_inputs_of_an_option_left_unchecked(): void
    {
        $this->requestFromTheDashboard(['assign_user' => ['user_id' => 'abc']], checked: []);

        self::assertResponseRedirects('/dump-loader/monitor');
        self::assertSame([], array_filter($this->runner()->commands, static fn (ShellCommand $command): bool => \in_array('app:some-command', $command->argv, true)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedUserIds(): iterable
    {
        yield 'not an integer' => ['abc', 'The "user_id" input of step "assign_user" must be an integer.'];
        yield 'missing' => ['', 'The "user_id" input of step "assign_user" is required.'];
    }

    /**
     * @return iterable<string, array{list<string>, string}>
     */
    public static function refusedUserIdsOnTheCommandLine(): iterable
    {
        yield 'not an integer' => [['assign_user.user_id=abc'], 'The "user_id" input of step "assign_user" must be an integer.'];
        yield 'missing' => [[], 'The "user_id" input of step "assign_user" is required.'];
    }

    /**
     * @param array<string, array<string, string>> $inputs
     * @param list<string>|null                    $checked
     */
    private function requestFromTheDashboard(array $inputs, ?array $checked = null): void
    {
        $crawler = $this->client->request('GET', '/dump-loader/');
        $token = (string) $crawler->filter('form[action="/dump-loader/request"] input[name="_token"]')->first()->attr('value');

        $this->client->request('POST', '/dump-loader/request', [
            '_token' => $token,
            'dump' => 'simple.sql',
            'options' => $checked ?? array_keys($inputs),
            'inputs' => $inputs,
            'confirm' => '1',
        ]);
    }

    /**
     * @param list<string> $inputs
     */
    private function loadFromTheCommandLine(string $option, array $inputs): CommandTester
    {
        $tester = new CommandTester(new Application($this->client->getKernel())->find('dump-loader:load'));
        $tester->execute(
            ['dump' => 'simple.sql', '--option' => [$option], '--input' => $inputs, '--force' => true],
            ['interactive' => false],
        );

        return $tester;
    }

    private function ranCommand(string $name): ShellCommand
    {
        foreach ($this->runner()->commands as $command) {
            if (\in_array($name, $command->argv, true)) {
                return $command;
            }
        }

        self::fail(\sprintf('The "%s" command was not run.', $name));
    }

    private function database(): InMemoryDatabaseClient
    {
        $database = self::getContainer()->get('dump_loader.database_client');

        self::assertInstanceOf(InMemoryDatabaseClient::class, $database);

        return $database;
    }

    private function runner(): RecordingProcessRunner
    {
        $runner = self::getContainer()->get('dump_loader.process_runner');

        self::assertInstanceOf(RecordingProcessRunner::class, $runner);

        return $runner;
    }

    private function jobsDir(): string
    {
        $jobsDir = self::getContainer()->getParameter('dump_loader.jobs_dir');

        self::assertIsString($jobsDir);

        return $jobsDir;
    }
}
