<?php

namespace FieldTop;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckCommand extends Command
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('check');

        $this
            ->addArgument('host', InputArgument::OPTIONAL, 'The server location', 'localhost')
            ->addOption('user', 'u', InputOption::VALUE_OPTIONAL, 'Username to use.', get_current_user())
            ->addOption('password', 'p', InputOption::VALUE_OPTIONAL, 'Password to use. Default is none')
            ->addOption('max', 'm', InputOption::VALUE_OPTIONAL, 'How many columns max', 100)
            ->addOption('database', 'd', InputOption::VALUE_OPTIONAL, 'If you want to limit the search to a particular database. Default all databases');
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $o = new \FieldTop\OverflowChecker();
        $o->connectDB($input->getOption('user'), $input->getOption('password'));
        if ($input->getOption('database')) {
            $o->setDatabase($input->getOption('database'));
        }
        
        $o->showCLI($output, $input->getOption('max'));
    }
}