<?php
/**
 * @package Tageswoche
 * @copyright 2012 Sourcefabric o.p.s.
 * @license http://www.gnu.org/licenses/gpl-3.0.txt
 */

namespace Newscoop\TagesWocheMobilePluginBundle\Console\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use DateTime;
use Newscoop\Entity\Article;

/**
 */
class GenerateOfflineIssueCommand extends ContainerAwareCommand
{
    /**
     */
    protected function configure()
    {
        $this->setName('oi:generate')
            ->setDescription('Generate offline issue.')
            ->addArgument('issue', InputArgument::OPTIONAL, 'Issue id', 'current');
    }

    /**
     */
    protected function execute($input, $output)
    {
        if ($input->getArgument('issue') === 'all') {
            foreach ($this->getHelper('container')->getService('newscoop_tageswochemobile_plugin.mobile.issue')->findAll() as $issue) {
                $this->generateIssue($issue);
            }
        } else {
            $issue = $this->getHelper('container')->getService('newscoop_tageswochemobile_plugin.mobile.issue')->find($input->getArgument('issue'));
            if ($issue !== null) {
                $this->generateIssue($issue);
            } else {
                $output->writeln(sprintf("<error>Issue '%s' not found.</error>", $input->getArgument('issue')));
            }
        }
    }

    /**
     * Generate single issue
     *
     * @param Newscoop\Entity\Article $issue
     * @return void
     */
    private function generateIssue(Article $issue)
    {
        try {
            $this->getHelper('container')->getService('newscoop_tageswochemobile_plugin.mobile.issue.offline')->generateIssue($issue);
        } catch (\Exception $e) {
            print_r($e->getMessage());
            print_r($e->getTraceAsString());
            exit;
        }
    }
}
