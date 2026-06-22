<?php

declare(strict_types=1);

namespace App\Command\Figma;

use App\Service\Figma\Dto\ParsedPage;
use App\Service\Figma\PageParser;
use App\Service\Figma\PageScreenshotter;
use App\Service\Figma\PageTreeExporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Dry-run: parses a Figma page node into a CMS architecture tree.
 *
 * READ-ONLY — prints the tree and writes a JSON artifact per page under
 * `.claude/skills/figma-cms/integration/pages/<slug>.json`. Performs NO database write.
 *
 * @author Sébastien FOURNIER <sebastien@agence-felix.fr>
 */
#[AsCommand(name: 'figma:parse-page', description: 'Dry-run : parse une page Figma en arbre CMS (aucune écriture base).')]
final class FigmaParsePageCommand extends Command
{
    public function __construct(
        private readonly PageParser $parser,
        private readonly PageTreeExporter $exporter,
        private readonly PageScreenshotter $screenshotter,
        private readonly string $figmaFileKey,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('node-id', InputArgument::REQUIRED, 'Node-id Figma de la page (ex. 123:456 ou 123-456)')
            ->addOption('file-key', null, InputOption::VALUE_REQUIRED, 'Clé du fichier Figma', $this->figmaFileKey)
            ->addOption('output-dir', null, InputOption::VALUE_REQUIRED, 'Dossier de sortie des JSON', $this->projectDir.'/.claude/skills/figma-cms/integration/pages')
            ->addOption('screenshot-dir', null, InputOption::VALUE_REQUIRED, 'Dossier des captures par bande', $this->projectDir.'/.claude/skills/figma-cms/integration/screenshots')
            ->addOption('no-screenshots', null, InputOption::VALUE_NONE, 'Ne pas générer les captures de bandes');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $nodeId = str_replace('-', ':', (string) $input->getArgument('node-id'));
        $fileKey = (string) $input->getOption('file-key');

        if ($fileKey === '') {
            $io->error('Aucune clé de fichier Figma (option --file-key ou FIGMA_FILE_KEY).');

            return Command::FAILURE;
        }

        $io->title(sprintf('Dry-run Figma → CMS : %s (%s)', $nodeId, $fileKey));
        $io->note('Lecture seule : aucune écriture en base de données.');

        $page = $this->parser->parse($fileKey, $nodeId);

        $this->renderTree($io, $page);

        if (!$input->getOption('no-screenshots')) {
            $this->captureScreenshots($io, $input, $fileKey, $nodeId, $page);
        }

        $outputDir = rtrim((string) $input->getOption('output-dir'), '/\\');
        if (!is_dir($outputDir) && !mkdir($outputDir, 0775, true) && !is_dir($outputDir)) {
            $io->error(sprintf('Impossible de créer le dossier de sortie : %s', $outputDir));

            return Command::FAILURE;
        }

        $file = $outputDir.'/'.$page->slug.'.json';
        $json = json_encode($this->exporter->toArray($page), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        file_put_contents($file, $json.\PHP_EOL);

        $io->success(sprintf('Architecture écrite (éditable à la main) : %s', $file));

        return Command::SUCCESS;
    }

    private function captureScreenshots(SymfonyStyle $io, InputInterface $input, string $fileKey, string $nodeId, ParsedPage $page): void
    {
        // Base dir; the screenshotter writes bands to <base>/<slug>/ and layout to <base>/layout/.
        $baseDir = rtrim((string) $input->getOption('screenshot-dir'), '/\\');

        try {
            $files = $this->screenshotter->capture($fileKey, $nodeId, $page, $baseDir);
            $io->note(sprintf('%d capture(s) écrite(s) (bandes %s/ + layout/) sous %s', count($files), $page->slug, $baseDir));

            $mediaDir = $this->projectDir.'/.claude/skills/figma-cms/integration/media/'.$page->slug;
            $media = $this->screenshotter->captureMedia($fileKey, $page, $mediaDir);
            if ($media !== []) {
                $io->note(sprintf('%d média(s) de contenu écrit(s) dans %s', count($media), $mediaDir));
            }
        } catch (\Throwable $e) {
            $io->warning('Captures non générées : '.$e->getMessage());
        }
    }

    private function renderTree(SymfonyStyle $io, ParsedPage $page): void
    {
        $lines = [sprintf('<info>PAGE</info> [page] → Layout\\Page (slug: %s)%s', $page->slug, $page->zonesDeduced ? ' <comment>[zones déduites]</comment>' : '')];

        foreach ($page->zones as $zi => $zone) {
            $lines[] = sprintf(
                '├─ <info>Zone %d</info> : %s%s',
                $zi + 1,
                $zone->label,
                $zone->deduced ? ' <comment>[déduite]</comment>' : ''
            );
            foreach ($zone->cols as $ci => $col) {
                $lines[] = sprintf('│  ├─ Col (size %d)%s', $col->size, $col->deduced ? ' <comment>~</comment>' : '');
                foreach ($col->blocks as $block) {
                    $lines[] = '│  │  └─ '.$this->describeBlock($block);
                }
                if ($col->untaggedCount > 0) {
                    $lines[] = sprintf('│  │  └─ <comment>%d calque(s) non balisé(s)</comment>', $col->untaggedCount);
                }
                if ($col->blocks === [] && $col->untaggedCount === 0) {
                    $lines[] = '│  │  └─ <comment>(vide)</comment>';
                }
            }
            unset($ci);
        }
        unset($zi);

        $io->text($lines);
        $io->newLine();

        if ($page->excluded !== []) {
            $io->section('Exclus (layout de base, non générés dans la page)');
            $io->listing($page->excluded);
        }

        if ($page->warnings !== []) {
            $io->section('Avertissements');
            $io->listing($page->warnings);
        }

        $this->renderSummary($io, $page);
    }

    private function describeBlock(\App\Service\Figma\Dto\ParsedBlock $block): string
    {
        return match ($block->kind) {
            'atom' => sprintf('Block <info>%s</info>  ← %s', $block->blockTypeSlug, $block->figmaName),
            'module' => sprintf('Block core-action / <info>%s</info> → %s  ← %s', $block->moduleAction, $block->moduleEntity, $block->figmaName),
            default => sprintf('<error>[???]</error> %s (%s)', $block->figmaName, $block->note ?? 'non mappé'),
        };
    }

    private function renderSummary(SymfonyStyle $io, ParsedPage $page): void
    {
        $atoms = $modules = $unknown = $untagged = 0;
        foreach ($page->zones as $zone) {
            foreach ($zone->cols as $col) {
                $untagged += $col->untaggedCount;
                foreach ($col->blocks as $block) {
                    match ($block->kind) {
                        'atom' => $atoms++,
                        'module' => $modules++,
                        default => $unknown++,
                    };
                }
            }
        }

        $io->definitionList(
            ['Zones' => (string) count($page->zones).($page->zonesDeduced ? ' (déduites)' : '')],
            ['Blocs atomiques' => (string) $atoms],
            ['Modules' => (string) $modules],
            ['Non mappés [???]' => (string) $unknown],
            ['Calques non balisés' => (string) $untagged],
            ['Exclus (nav/footer)' => (string) count($page->excluded)],
        );
    }
}
