<?php

declare(strict_types=1);

namespace Tests\Webgriffe\SyliusUpgradePlugin\Integration\Command;

use org\bovigo\vfs\vfsStream;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Tests\Webgriffe\SyliusUpgradePlugin\Stub\Client\Git;
use Webgriffe\SyliusUpgradePlugin\Command\TemplateChangesCommand;

final class TemplateChangesCommandTest extends KernelTestCase
{
    private const FIXTURE_DIR = __DIR__ . '/../DataFixtures/Command/TemplateChangesCommandTest/';

    /** @var CommandTester */
    private $commandTester;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        vfsStream::setup();

        $application = new Application(static::$kernel);
        $command = $application->find('webgriffe:upgrade:template-changes');
        $this->commandTester = new CommandTester($command);
    }

    /**
     * @test
     */
    public function it_is_executable_with_mandatory_parameters(): void
    {
        $return = $this->commandTester->execute([
            TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
            TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
        ]);

        self::assertEquals(0, $return);
    }

    /**
     * @test
     */
    public function it_outputs_filepaths_of_overridden_template_files_that_changed_between_two_given_versions(): void
    {
        Git::$diffToReturn = file_get_contents(self::FIXTURE_DIR . $this->getName() . '/git.diff');
        vfsStream::copyFromFileSystem(self::FIXTURE_DIR . $this->getName() . '/vfs');

        $return = $this->commandTester->execute([
            TemplateChangesCommand::FROM_VERSION_ARGUMENT_NAME => '1.8.4',
            TemplateChangesCommand::TO_VERSION_ARGUMENT_NAME => '1.8.8',
        ]);

        self::assertEquals(0, $return);
        $output = $this->commandTester->getDisplay();
        $expectedOutput = <<<TXT
Computing differences between 1.8.4 and 1.8.8

Searching "vfs://root/templates/bundles/" for overridden files that changed between the two versions.
Found 2 files that changed and was overridden:
	SyliusShopBundle/Checkout/Address/_form.html.twig
	SyliusUiBundle/Form/theme.html.twig

TXT;

        self::assertEquals($expectedOutput, $output);
    }
}