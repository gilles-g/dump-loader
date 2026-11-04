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

namespace DumpLoader\Tests\Unit;

use DumpLoader\DumpLoaderBundle;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ConfigurationExtensionInterface;

final class DumpLoaderBundleTest extends TestCase
{
    #[Test]
    public function it_should_refuse_an_after_load_input_that_cannot_be_a_marker(): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('Each input of an "after_load" command must be named with lowercase letters, digits and underscores only.');

        $this->process([
            'source' => ['directory' => '/dumps'],
            'target_database' => ['dsn' => 'mysql://loader@127.0.0.1:3306/preprod'],
            'after_load' => [
                'assign_user' => [
                    'label' => 'Assign a user',
                    'command' => ['app:assign', '--user={user-id}'],
                    'inputs' => ['user-id' => ['type' => 'int']],
                ],
            ],
        ]);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function process(array $config): void
    {
        $extension = new DumpLoaderBundle()->getContainerExtension();
        self::assertInstanceOf(ConfigurationExtensionInterface::class, $extension);
        $configuration = $extension->getConfiguration([], new ContainerBuilder());
        self::assertInstanceOf(ConfigurationInterface::class, $configuration);

        new Processor()->processConfiguration($configuration, [$config]);
    }
}
