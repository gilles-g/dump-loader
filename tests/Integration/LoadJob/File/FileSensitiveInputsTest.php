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

namespace DumpLoader\Tests\Integration\LoadJob\File;

use DumpLoader\LoadJob\File\FileSensitiveInputs;
use DumpLoader\LoadJob\File\JobDirectoryNotWritable;
use DumpLoader\LoadJob\InputValue;
use DumpLoader\LoadJob\InputValues;
use DumpLoader\LoadJob\LoadJobId;
use DumpLoader\LoadJob\UnreadableLoadJob;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class FileSensitiveInputsTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().'/dump-loader-inputs-'.bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->directory)) {
            return;
        }

        chmod($this->directory, 0o775);

        foreach (array_diff(scandir($this->directory) ?: [], ['.', '..']) as $entry) {
            unlink($this->directory.'/'.$entry);
        }

        rmdir($this->directory);
    }

    #[Test]
    public function it_should_keep_the_values_in_a_file_only_its_owner_can_read(): void
    {
        new FileSensitiveInputs($this->directory)->keep($this->anId(), $this->secrets());

        self::assertSame(0o600, fileperms($this->path()) & 0o777);
    }

    #[Test]
    public function it_should_hand_the_values_back_once(): void
    {
        $inputs = new FileSensitiveInputs($this->directory);
        $inputs->keep($this->anId(), $this->secrets());

        self::assertSame('s3cr3t', $inputs->take($this->anId())->value('set_password', 'password'));
        self::assertFileDoesNotExist($this->path());
        self::assertTrue($inputs->take($this->anId())->isEmpty());
    }

    #[Test]
    public function it_should_write_nothing_when_there_is_nothing_to_keep(): void
    {
        new FileSensitiveInputs($this->directory)->keep($this->anId(), InputValues::none());

        self::assertDirectoryDoesNotExist($this->directory);
    }

    #[Test]
    public function it_should_discard_the_values_of_a_job(): void
    {
        $inputs = new FileSensitiveInputs($this->directory);
        $inputs->keep($this->anId(), $this->secrets());

        $inputs->discard($this->anId());
        $inputs->discard($this->anId());

        self::assertFileDoesNotExist($this->path());
    }

    #[Test]
    public function it_should_refuse_to_overwrite_the_values_of_a_job(): void
    {
        $inputs = new FileSensitiveInputs($this->directory);
        $inputs->keep($this->anId(), $this->secrets());

        $this->expectException(JobDirectoryNotWritable::class);

        $inputs->keep($this->anId(), $this->secrets());
    }

    #[Test]
    public function it_should_refuse_to_keep_the_values_in_a_directory_it_cannot_create(): void
    {
        $this->expectException(JobDirectoryNotWritable::class);

        new FileSensitiveInputs('/proc/nowhere/dump-loader')->keep($this->anId(), $this->secrets());
    }

    #[Test]
    #[DataProvider('corruptedFiles')]
    public function it_should_refuse_a_file_it_cannot_read_and_still_delete_it(string $contents): void
    {
        mkdir($this->directory, 0o775, true);
        file_put_contents($this->path(), $contents);

        try {
            new FileSensitiveInputs($this->directory)->take($this->anId());
            self::fail('The file should have been refused.');
        } catch (UnreadableLoadJob) {
            self::assertFileDoesNotExist($this->path());
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function corruptedFiles(): iterable
    {
        yield 'not JSON' => ['{'];
        yield 'not a list' => ['"s3cr3t"'];
        yield 'not an input value' => ['[{"step": 1}]'];
    }

    private function secrets(): InputValues
    {
        return new InputValues(InputValue::entered('set_password', 'password', 's3cr3t', sensitive: true));
    }

    private function anId(): LoadJobId
    {
        return LoadJobId::fromString('01J8Z9P0000000000000000001');
    }

    private function path(): string
    {
        return $this->directory.'/01J8Z9P0000000000000000001.inputs';
    }
}
