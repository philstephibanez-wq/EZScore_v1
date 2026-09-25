<?php

declare(strict_types=1);

namespace App\Command;

use App\Service\SongPublicationWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:mailing:worker',
    description: 'Process queued new-song publication mailings asynchronously.',
)]
final class SongPublicationMailWorkerCommand extends Command
{
    public function __construct(
        private readonly SongPublicationWorker $worker,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('once', null, InputOption::VALUE_NONE, 'Process at most one queued job and exit.')
            ->addOption('sleep', null, InputOption::VALUE_REQUIRED, 'Seconds to sleep when the queue is empty.', '2')
            ->addOption('limit', null, InputOption::VALUE_REQUIRED, 'Maximum number of jobs to claim before exit; 0 means unlimited.', '0');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $once = (bool) $input->getOption('once');
        $sleepSeconds = max(1, (int) $input->getOption('sleep'));
        $limit = max(0, (int) $input->getOption('limit'));
        $processed = 0;

        $output->writeln('<info>EZScore publication-mail worker started.</info>');

        while (true) {
            $didWork = $this->worker->processOne();

            if ($didWork) {
                ++$processed;
                $output->writeln(sprintf('<comment>Processed mailing job #%d.</comment>', $processed));

                if ($once || ($limit > 0 && $processed >= $limit)) {
                    break;
                }

                continue;
            }

            if ($once || ($limit > 0 && $processed >= $limit)) {
                break;
            }

            sleep($sleepSeconds);
        }

        $output->writeln(sprintf('<info>Worker stopped. Jobs claimed: %d.</info>', $processed));

        return Command::SUCCESS;
    }
}
