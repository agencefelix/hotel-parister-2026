<?php

declare(strict_types=1);

namespace App\Command\Figma;

use App\Service\Figma\LayoutScreenshotter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * (Re)generates screenshots of shared layout elements (nav, footer…) declared in
 * `.claude/skills/figma-cms/integration/layout/*.json`. READ-ONLY — performs NO database write.
 *
 * Declarative wiring: each layout JSON lists its captures ({figmaNodeId, screenshot}).
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
#[AsCommand(name: 'figma:capture-layout', description: 'Génère les captures des éléments de layout déclarés (nav, footer…).')]
final class FigmaCaptureLayoutCommand extends Command
{
    public function __construct(
        private readonly LayoutScreenshotter $screenshotter,
        private readonly string $figmaFileKey,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('file-key', null, InputOption::VALUE_REQUIRED, 'Clé du fichier Figma', $this->figmaFileKey)
            ->addOption('layout-dir', null, InputOption::VALUE_REQUIRED, 'Dossier des JSON de layout', $this->projectDir.'/.claude/skills/figma-cms/integration/layout')
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Dossier des captures', $this->projectDir.'/.claude/skills/figma-cms/integration/screenshots/layout');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $fileKey = (string) $input->getOption('file-key');
        $layoutDir = rtrim((string) $input->getOption('layout-dir'), '/\\');
        $outputDir = rtrim((string) $input->getOption('output-dir'), '/\\');

        if ($fileKey === '') {
            $io->error('Aucune clé de fichier Figma (option --file-key ou FIGMA_FILE_KEY).');

            return Command::FAILURE;
        }

        $files = glob($layoutDir.'/*.json') ?: [];
        if ($files === []) {
            $io->warning(sprintf('Aucun JSON de layout dans %s', $layoutDir));

            return Command::SUCCESS;
        }

        $captures = [];
        foreach ($files as $file) {
            $data = json_decode((string) file_get_contents($file), true);
            foreach ($data['captures'] ?? [] as $capture) {
                if (!empty($capture['figmaNodeId']) && !empty($capture['screenshot'])) {
                    $captures[] = [
                        'figmaNodeId' => str_replace('-', ':', (string) $capture['figmaNodeId']),
                        'screenshot' => (string) $capture['screenshot'],
                    ];
                }
            }
        }

        $io->title('Captures des éléments de layout (lecture seule)');
        $written = $this->screenshotter->capture($fileKey, $captures, $outputDir);

        $io->success(sprintf('%d capture(s) de layout écrite(s) dans %s', count($written), $outputDir));
        $io->listing($written);

        return Command::SUCCESS;
    }
}
