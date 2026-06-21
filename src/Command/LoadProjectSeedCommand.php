<?php

namespace App\Command;

use App\Entity\Project;
use App\Seed\ProjectSeedFamily;
use App\Seed\ProjectSeedImageSynchronizer;
use App\Seed\ProjectSeedLoader;
use App\Seed\ProjectSeedWatermarkInstaller;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsCommand(
    name: 'app:seed:load-project',
    description: 'Analyze and import real seed data for one existing project.',
)]
final class LoadProjectSeedCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProjectSeedLoader $projectSeedLoader,
        private readonly ProjectSeedImageSynchronizer $imageSynchronizer,
        private readonly ProjectSeedWatermarkInstaller $watermarkInstaller,
        #[Autowire('%env(default:seed_project_path:SEED_PROJECT_PATH)%')]
        private readonly string $seedProjectPath,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('projectSlug', InputArgument::REQUIRED, 'Existing project slug to import.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Confirm import in non-interactive mode.')
            ->addOption('sync-images', null, InputOption::VALUE_NONE, 'Clear the project media directory, then copy every file from resources/images.')
            ->addOption('only', null, InputOption::VALUE_REQUIRED, 'Comma-separated families to import: metadata,media,web-pages,accommodations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $projectSlug = trim((string) $input->getArgument('projectSlug'));
        $seedRootPath = rtrim(trim($this->seedProjectPath), '/');

        if ('' === $seedRootPath) {
            $io->error('SEED_PROJECT_PATH is not configured.');

            return Command::FAILURE;
        }

        $project = $this->entityManager->getRepository(Project::class)->findOneBy(['slug' => $projectSlug]);
        if (!$project instanceof Project) {
            $io->error(sprintf('Project "%s" was not found.', $projectSlug));

            return Command::FAILURE;
        }

        $projectPath = $seedRootPath.'/'.$projectSlug;
        $io->title(sprintf('Analyze seed project "%s"', $projectSlug));
        $report = $this->projectSeedLoader->analyze($project, $projectPath);
        $this->renderReport($io, $report->data, $report->warnings, $report->errors);

        if (!$report->isValid()) {
            $io->error('Seed preflight failed. No data was imported.');

            return Command::FAILURE;
        }

        try {
            $selectedFamilies = $this->resolveSelectedFamilies($input, $io);
        } catch (\InvalidArgumentException $exception) {
            $io->error($exception->getMessage());

            return Command::FAILURE;
        }
        if ([] === $selectedFamilies) {
            $io->warning('No import family selected. No data was imported.');

            return Command::SUCCESS;
        }

        $selectionErrors = $this->projectSeedLoader->validateSelection($project, $report->data, $selectedFamilies);
        if ([] !== $selectionErrors) {
            $io->listing($selectionErrors);
            $io->error('Seed selection is inconsistent. No data was imported.');

            return Command::FAILURE;
        }

        $plan = $this->projectSeedLoader->plan($report->data, $selectedFamilies);
        $syncImages = true === $input->getOption('sync-images');
        $io->section('Import plan');
        $io->listing($plan->families);
        if ($syncImages) {
            $io->warning(sprintf(
                'Image sync is enabled: %s will be cleared before copying every file from resources/images.',
                $project->getMediaPath() ?? 'the project media directory',
            ));
        }

        if (!$input->isInteractive() && true !== $input->getOption('force')) {
            $io->error('Use --force to confirm import in non-interactive mode.');

            return Command::FAILURE;
        }

        if ($input->isInteractive() && !$io->confirm('Import selected seed data now?', false)) {
            $io->warning('Import cancelled. No data was imported.');

            return Command::SUCCESS;
        }

        // Install the watermark before importing media so image watermarking finds it.
        $watermarkInstalled = $this->watermarkInstaller->install($project, $report->data);
        $result = $this->projectSeedLoader->import($project, $report->data, $plan);
        $syncedImages = null;
        if ($syncImages) {
            $syncedImages = $this->imageSynchronizer->sync($project, $report->data);
        }

        $io->success('Seed import completed.');
        $rows = array_map(
            static fn (string $family, int $count): array => [$family, (string) $count],
            array_keys($result->imported),
            $result->imported,
        );
        if (null !== $syncedImages) {
            $rows[] = ['synced-images', (string) $syncedImages];
        }
        $rows[] = ['watermark', $watermarkInstalled ? 'installed' : 'skipped'];

        $io->table(
            ['Family', 'Processed'],
            $rows,
        );

        return Command::SUCCESS;
    }

    /**
     * @param list<string> $warnings
     * @param list<string> $errors
     */
    private function renderReport(SymfonyStyle $io, \App\Seed\ProjectSeedData $data, array $warnings, array $errors): void
    {
        $io->table(
            ['Family', 'Detected'],
            [
                [ProjectSeedFamily::METADATA, (string) $data->countFamily(ProjectSeedFamily::METADATA)],
                [ProjectSeedFamily::MEDIA, (string) $data->countFamily(ProjectSeedFamily::MEDIA)],
                [ProjectSeedFamily::WEB_PAGES, (string) $data->countFamily(ProjectSeedFamily::WEB_PAGES)],
                [ProjectSeedFamily::ACCOMMODATIONS, (string) $data->countFamily(ProjectSeedFamily::ACCOMMODATIONS)],
                ['ignored-files', (string) count($data->ignoredFiles)],
            ],
        );

        if ([] !== $warnings) {
            $io->section('Warnings');
            $io->listing($warnings);
        }

        if ([] !== $errors) {
            $io->section('Blocking errors');
            $io->listing($errors);
        }
    }

    /**
     * @return list<string>
     */
    private function resolveSelectedFamilies(InputInterface $input, SymfonyStyle $io): array
    {
        $only = $input->getOption('only');
        if (is_string($only) && '' !== trim($only)) {
            return $this->parseFamilies($only);
        }

        if (!$input->isInteractive()) {
            return ProjectSeedFamily::all();
        }

        $choices = array_merge(['all'], ProjectSeedFamily::all());
        $selected = $io->choice('Which families should be imported?', $choices, 'all', true);
        $selected = is_array($selected) ? $selected : [$selected];

        if (in_array('all', $selected, true)) {
            return ProjectSeedFamily::all();
        }

        return $this->filterKnownFamilies($selected);
    }

    /**
     * @return list<string>
     */
    private function parseFamilies(string $only): array
    {
        $families = array_values(array_filter(
            array_map(static fn (string $family): string => trim($family), explode(',', $only)),
            static fn (string $family): bool => '' !== $family,
        ));

        if (in_array('all', $families, true)) {
            return ProjectSeedFamily::all();
        }

        return $this->filterKnownFamilies($families);
    }

    /**
     * @param list<string> $families
     *
     * @return list<string>
     */
    private function filterKnownFamilies(array $families): array
    {
        $known = ProjectSeedFamily::all();
        $unknown = array_values(array_diff($families, $known));
        if ([] !== $unknown) {
            throw new \InvalidArgumentException(sprintf('Unknown seed import families: %s.', implode(', ', $unknown)));
        }

        return array_values(array_unique($families));
    }
}
