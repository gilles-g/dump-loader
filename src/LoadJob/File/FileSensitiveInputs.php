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

namespace DumpLoader\LoadJob\File;

use DumpLoader\LoadJob\InputValues;
use DumpLoader\LoadJob\LoadJobId;
use DumpLoader\LoadJob\SensitiveInputs;
use DumpLoader\LoadJob\UnreadableLoadJob;

final readonly class FileSensitiveInputs implements SensitiveInputs
{
    public function __construct(
        private string $directory,
    ) {
    }

    public function keep(LoadJobId $id, InputValues $values): void
    {
        if ($values->isEmpty()) {
            return;
        }

        if (!is_dir($this->directory) && !@mkdir($this->directory, 0o775, true) && !is_dir($this->directory)) {
            throw JobDirectoryNotWritable::at($this->directory);
        }

        $handle = @fopen($this->pathFor($id), 'x');

        if (false === $handle) {
            throw JobDirectoryNotWritable::at($this->directory);
        }

        chmod($this->pathFor($id), 0o600);
        fwrite($handle, json_encode($values->toRevealedArray(), \JSON_THROW_ON_ERROR));
        fclose($handle);
    }

    public function take(LoadJobId $id): InputValues
    {
        $path = $this->pathFor($id);

        if (!is_file($path)) {
            return InputValues::none();
        }

        $contents = @file_get_contents($path);
        $this->discard($id);

        try {
            $data = json_decode((string) $contents, true, 512, \JSON_THROW_ON_ERROR);

            if (!\is_array($data)) {
                throw UnreadableLoadJob::at($path, 'it does not hold a JSON list');
            }

            return InputValues::fromArray($data);
        } catch (\JsonException|\InvalidArgumentException $exception) {
            throw UnreadableLoadJob::at($path, $exception->getMessage(), $exception);
        }
    }

    public function discard(LoadJobId $id): void
    {
        if (is_file($this->pathFor($id))) {
            unlink($this->pathFor($id));
        }
    }

    private function pathFor(LoadJobId $id): string
    {
        return $this->directory.'/'.$id->value.'.inputs';
    }
}
