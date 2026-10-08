<?php
declare(strict_types=1);
namespace App\Command;
use App\Application\Workspace\AssessmentService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\{InputInterface,InputOption};
use Symfony\Component\Console\Output\OutputInterface;
#[AsCommand(name:'app:assessment:work',description:'Process a bounded batch of durable assessment jobs')]
final class AssessmentWorkCommand extends Command
{
    public function __construct(private AssessmentService $assessment) { parent::__construct(); }
    protected function configure(): void { $this->addOption('limit',null,InputOption::VALUE_REQUIRED,'Maximum jobs',1); }
    protected function execute(InputInterface $input,OutputInterface $output): int
    {
        $limit=max(1,min(10,(int)$input->getOption('limit')));
        try { for($i=0;$i<$limit;++$i) if(!$this->assessment->work()) break; }
        catch(\Throwable) { $output->writeln('Assessment worker temporarily unavailable.'); return Command::FAILURE; }
        return Command::SUCCESS;
    }
}
