<?php

declare(strict_types=1);

namespace Webgriffe\SyliusUpgradePlugin\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Webgriffe\SyliusUpgradePlugin\Client\GitInterface;
use Webmozart\Glob\Glob;

final class TemplateChangesCommand extends Command
{
    public const FROM_VERSION_ARGUMENT_NAME = 'from';

    public const TO_VERSION_ARGUMENT_NAME = 'to';

    private const TEMPLATES_BUNDLES_SUBDIR = 'templates/bundles/';

    protected static $defaultName = 'webgriffe:upgrade:template-changes';

    /** @var string */
    private $rootPath;

    /** @var OutputInterface */
    private $output;

    /** @var GitInterface */
    private $gitClient;

    public function __construct(GitInterface $gitClient, string $rootPath, string $name = null)
    {
        parent::__construct($name);

        $this->gitClient = $gitClient;
        $this->rootPath = rtrim($rootPath, \DIRECTORY_SEPARATOR) . \DIRECTORY_SEPARATOR;
    }

    protected function configure(): void
    {
        $this
            ->setDescription(
                'Print a list of template files (with extension .html.twig) that changed between two given Sylius versions ' .
                'and that has been overridden in the project (in "templates" dir or in a theme).'
            )
            ->addArgument(
                self::FROM_VERSION_ARGUMENT_NAME,
                InputArgument::REQUIRED,
                'Starting Sylius version to use for changes computation.'
            )
            ->addArgument(
                self::TO_VERSION_ARGUMENT_NAME,
                InputArgument::REQUIRED,
                'Target Sylius version to use for changes computation.'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->output = $output;

        $fromVersion = $input->getArgument(self::FROM_VERSION_ARGUMENT_NAME);
        if (!is_string($fromVersion)) {
            // todo
            return 1;
        }
        $toVersion = $input->getArgument(self::TO_VERSION_ARGUMENT_NAME);
        if (!is_string($toVersion)) {
            // todo
            return 1;
        }
        $versionChangedFiles = $this->getFilesChangedBetweenTwoVersions($fromVersion, $toVersion);
        $this->computeTemplateFilesChangedAndOverridden($versionChangedFiles);

        // todo: compute theme files

        return 0;
    }

    /**
     * @return string[]
     */
    private function getFilesChangedBetweenTwoVersions(string $fromVersion, string $toVersion): array
    {
        $this->output->writeln(sprintf('Computing differences between %s and %s', $fromVersion, $toVersion));
        $diff = $this->gitClient->getDiffBetweenTags($fromVersion, $toVersion);
        $versionChangedFiles = [];
        $diffLines = explode(\PHP_EOL, $diff);
        foreach ($diffLines as $diffLine) {
            if (strpos($diffLine, 'diff --git') !== 0) {
                continue;
            }
            $diffLineParts = explode(' ', $diffLine);
            $changedFileName = substr($diffLineParts[2], 2);
            if (strpos($changedFileName, 'Resources/views') === false) {
                continue;
            }
            $versionChangedFiles[] = $changedFileName;
        }

        // src/Sylius/Bundle/AdminBundle/Resources/views/PaymentMethod/_form.html.twig -> SyliusAdminBundle/PaymentMethod/_form.html.twig
        return array_map(
            static function (string $versionChangedFile): string {
                return str_replace(['src/Sylius/Bundle/', '/Resources/views'], ['Sylius', ''], $versionChangedFile);
            },
            $versionChangedFiles
        );
    }

    private function computeTemplateFilesChangedAndOverridden(array $versionChangedFiles): void
    {
        $targetDir = $this->rootPath . self::TEMPLATES_BUNDLES_SUBDIR;
        $this->output->writeln('');
        $this->output->writeln(sprintf('Searching "%s" for overridden files that changed between the two versions.', $targetDir));
        $templateFilenames = $this->getProjectTemplatesFiles($targetDir);
        /** @var string[] $overriddenTemplateFiles */
        $overriddenTemplateFiles = array_intersect($versionChangedFiles, $templateFilenames);
        $this->output->writeln(sprintf('Found %s files that changed and was overridden:', count($overriddenTemplateFiles)));
        foreach ($overriddenTemplateFiles as $file) {
            $this->output->writeln("\t" . $file);
        }
    }

    /**
     * @return string[]
     */
    private function getProjectTemplatesFiles(string $targetDir): array
    {
        $files = Glob::glob($targetDir . 'Sylius*Bundle/**/' . '*.html.twig');

        // from /Users/user/workspace/project/templates/bundles/SyliusAdminBundle/PaymentMethod/_form.html.twig
        // to SyliusAdminBundle/PaymentMethod/_form.html.twig
        return array_map(
            static function (string $file) use ($targetDir): string {
                return str_replace($targetDir, '', $file);
            },
            $files
        );
    }
}