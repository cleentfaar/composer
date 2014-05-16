<?php

/*
 * This file is part of Composer.
 *
 * (c) Nils Adermann <naderman@naderman.de>
 *     Jordi Boggiano <j.boggiano@seld.be>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Composer\Command;

use Composer\Installer;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginEvents;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Cas Leentfaar <info@casleentfaar.com>
 */
class StabilizeCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('stabilize')
            ->setDescription('Updates your dependencies to the latest (stable) version possible, effectively creating the most stable composer.json you could have')
            ->setDefinition(array(
                new InputArgument('packages', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Packages that should be stabilized, if not provided all packages are.'),
                new InputOption('prefer-source', null, InputOption::VALUE_NONE, 'Forces installation from package sources when possible, including VCS information.'),
                new InputOption('prefer-dist', null, InputOption::VALUE_NONE, 'Forces installation from package dist.'),
                new InputOption('dry-run', null, InputOption::VALUE_NONE, 'Outputs the operations but will not execute anything (implicitly enables --verbose).'),
                new InputOption('no-plugins', null, InputOption::VALUE_NONE, 'Disables all plugins.'),
                new InputOption('no-scripts', null, InputOption::VALUE_NONE, 'Skips the execution of all scripts defined in composer.json file.'),
                new InputOption('no-progress', null, InputOption::VALUE_NONE, 'Do not output download progress.'),
                new InputOption('verbose', 'v|vv|vvv', InputOption::VALUE_NONE, 'Shows more details including new commits pulled in when updating packages.'),
                new InputOption('optimize-autoloader', 'o', InputOption::VALUE_NONE, 'Optimize autoloader during autoloader dump.')
            ))
            ->setHelp(<<<EOT
The <info>stabilize</info> command reads the composer.json file from the current directory,
finds the latest and most stable versions for all the packages, and writes
those versions to your composer.json.

After this is done, it executes the <info>update</info> command for each of the packages
like you would do after changing versions manually.

<info>php composer.phar stabilize</info>

To limit the stabilization to a few packages, you can list the package(s)
you want to stabilize as such:

<info>php composer.phar stabilize vendor/package1 foo/mypackage [...]</info>
EOT
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $composer = $this->getComposer(true, $input->getOption('no-plugins'));
        $composer->getDownloadManager()->setOutputProgress(!$input->getOption('no-progress'));
        $io = $this->getIO();

        $commandEvent = new CommandEvent(PluginEvents::COMMAND, 'stabilize', $input, $output);
        $composer->getEventDispatcher()->dispatch($commandEvent->getName(), $commandEvent);

        $install = Installer::create($io, $composer);

        $preferSource = false;
        $preferDist = false;

        $config = $composer->getConfig();

        switch ($config->get('preferred-install')) {
            case 'source':
                $preferSource = true;
                break;
            case 'dist':
                $preferDist = true;
                break;
            case 'auto':
            default:
                // noop
                break;
        }
        if ($input->getOption('prefer-source') || $input->getOption('prefer-dist')) {
            $preferSource = $input->getOption('prefer-source');
            $preferDist = $input->getOption('prefer-dist');
        }

        $optimize = $input->getOption('optimize-autoloader') || $config->get('optimize-autoloader');

        $install
            ->setDryRun($input->getOption('dry-run'))
            ->setVerbose($input->getOption('verbose'))
            ->setPreferSource($preferSource)
            ->setPreferDist($preferDist)
            ->setDevMode(!$input->getOption('no-dev'))
            ->setRunScripts(!$input->getOption('no-scripts'))
            ->setOptimizeAutoloader($optimize)
            ->setUpdate(true)
            ->setUpdateWhitelist($input->getOption('lock') ? array('lock') : $input->getArgument('packages'))
            ->setWhitelistDependencies($input->getOption('with-dependencies'))
        ;

        if ($input->getOption('no-plugins')) {
            $install->disablePlugins();
        }

        return $install->run();
    }
}
