<?php

namespace Acquia\Cli\Command\Push;

use Acquia\Cli\Command\Pull\PullCommandBase;
use Acquia\Cli\Exception\AcquiaCliException;
use Acquia\Cli\Output\Checklist;
use Acquia\DrupalEnvironmentDetector\AcquiaDrupalEnvironmentDetector;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class PushFilesCommand extends PullCommandBase {

  protected static $defaultName = 'push:files';

  protected function configure(): void {
    $this->setDescription('Push Drupal files from your IDE to a Cloud Platform environment')
      ->acceptEnvironmentId()
      ->acceptSite()
      ->setHidden(!AcquiaDrupalEnvironmentDetector::isAhIdeEnv() && !self::isLandoEnv());
  }

  /**
   * @return int 0 if everything went fine, or an exit code
   */
  protected function execute(InputInterface $input, OutputInterface $output): int {
    $this->setDirAndRequireProjectCwd($input);
    $destination_environment = $this->determineEnvironment($input, $output);
    $chosen_site = $input->getArgument('site');
    if (!$chosen_site) {
      if ($this->isAcsfEnv($destination_environment)) {
        $chosen_site = $this->promptChooseAcsfSite($destination_environment);
      }
      else {
        $chosen_site = $this->promptChooseCloudSite($destination_environment);
      }
    }
    $answer = $this->io->confirm("Overwrite the public files directory on <bg=cyan;options=bold>{$destination_environment->name}</> with a copy of the files from the current machine?");
    if (!$answer) {
      return 0;
    }

    $this->checklist = new Checklist($output);
    $this->checklist->addItem('Pushing public files directory to remote machine');
    $this->rsyncFilesToCloud($destination_environment, $this->getOutputCallback($output, $this->checklist), $chosen_site);
    $this->checklist->completePreviousItem();

    return 0;
  }

  /**
   * @param $chosen_environment
   * @param callable|null $output_callback
   * @param string|null $site
   */
  private function rsyncFilesToCloud($chosen_environment, callable $output_callback = NULL, string $site = NULL): void {
    $source = $this->dir . '/docroot/sites/default/files/';
    $sitegroup = self::getSiteGroupFromSshUrl($chosen_environment->sshUrl);

    if ($this->isAcsfEnv($chosen_environment)) {
      $dest_dir = '/mnt/files/' . $sitegroup . '.' . $chosen_environment->name . '/sites/g/files/' . $site . '/files';
    }
    else {
      $dest_dir = '/mnt/files/' . $sitegroup . '.' . $chosen_environment->name . '/sites/' . $site . '/files';
    }
    $this->localMachineHelper->checkRequiredBinariesExist(['rsync']);
    $command = [
      'rsync',
      // -a archive mode; same as -rlptgoD.
      // -z compress file data during the transfer.
      // -v increase verbosity.
      // -P show progress during transfer.
      // -h output numbers in a human-readable format.
      // -e specify the remote shell to use.
      '-avPhze',
      'ssh -o StrictHostKeyChecking=no',
      $source,
      $chosen_environment->sshUrl . ':' . $dest_dir,
    ];
    $process = $this->localMachineHelper->execute($command, $output_callback, NULL, ($this->output->getVerbosity() > OutputInterface::VERBOSITY_NORMAL));
    if (!$process->isSuccessful()) {
      throw new AcquiaCliException('Unable to sync files to Cloud. {message}', ['message' => $process->getErrorOutput()]);
    }
  }

}
