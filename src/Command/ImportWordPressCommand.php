<?php

namespace Base\Blog\Command;

use Base\Blog\WordPress\Importer;
use Base\Blog\WordPress\Result;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A WordPress site moved in through its REST API (wp-json/wp/v2): the posts
 * become blog posts, each page goes where the map sends it - a site's own
 * targets (a biography, a page, the agenda, a service) or nowhere -, the
 * pictures are copied, the links rewritten.
 *
 *   bin/console blog:import-wordpress https://example.org
 *   bin/console blog:import-wordpress https://example.org --map about-me/biography=bio --map contact=skip --update
 *   bin/console blog:import-wordpress https://example.org --map map.json
 *
 * --update brings what was imported before up to date; without it, what is
 * there is left alone. Either way nothing is doubled.
 */
#[AsCommand(name: 'blog:import-wordpress', description: 'Import a WordPress site (posts, pages, media) through its REST API.')]
final class ImportWordPressCommand extends Command
{
    public function __construct(
        private readonly Importer $importer,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('url', InputArgument::REQUIRED, 'The WordPress site (https://example.org), or its wp-json/wp/v2 address')
            ->addOption('map', 'm', InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'slug=target[:option] (a page\'s slug or path, *post, *page), or a JSON file of them')
            ->addOption('update', null, InputOption::VALUE_NONE, 'Bring up to date what an earlier run imported')
            ->addOption('author', null, InputOption::VALUE_REQUIRED, 'The author of what is created (a username)')
            ->addOption('uploads', null, InputOption::VALUE_REQUIRED, 'The pictures\' folder in the uploads', 'wordpress')
            ->addOption('no-media', null, InputOption::VALUE_NONE, 'Leave the pictures on the old site')
            ->addOption('targets', null, InputOption::VALUE_NONE, 'List the targets and stop');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        if ($input->getOption('targets')) {
            $io->listing(array_map(fn ($t) => $t::class.' ('.$t->getName().')', array_values($this->importer->getTargets())));

            return Command::SUCCESS;
        }

        $map = [];
        foreach ((array) $input->getOption('map') as $entry) {
            if (is_file($entry)) {
                $map = [...$map, ...(array) json_decode((string) file_get_contents($entry), true, 512, \JSON_THROW_ON_ERROR)];
            } elseif (str_contains($entry, '=')) {
                [$slug, $target] = explode('=', $entry, 2);
                $map[trim($slug, '/ ')] = trim($target);
            } else {
                $io->error(sprintf('--map "%s": slug=target, or a JSON file.', $entry));

                return Command::INVALID;
            }
        }
        $author = null;
        if ($username = $input->getOption('author')) {
            // The application's user (App\Entity\User holds the username), else omnibase's.
            $class = class_exists('App\\Entity\\User') ? 'App\\Entity\\User' : \Base\Entity\User::class;
            $author = $this->entityManager->getRepository($class)->findOneBy(['username' => $username]);
            if (!$author) {
                $io->warning(sprintf('No user "%s": what is created has no author.', $username));
            }
        }

        $result = $this->importer->import((string) $input->getArgument('url'), $map, (bool) $input->getOption('update'), $author, (string) $input->getOption('uploads'), !$input->getOption('no-media'));

        foreach ($result->warnings as $warning) {
            $io->warning($warning);
        }
        $rows = [];
        foreach ($result->counts as $target => $counts) {
            $rows[] = [$target, $counts[Result::CREATED] ?? 0, $counts[Result::UPDATED] ?? 0, $counts[Result::KEPT] ?? 0, $counts[Result::SKIPPED] ?? 0];
        }
        $io->table(['target', 'created', 'updated', 'kept', 'skipped'], $rows);
        $io->success(sprintf('%d picture(s) copied.', $result->media));

        return Command::SUCCESS;
    }
}
