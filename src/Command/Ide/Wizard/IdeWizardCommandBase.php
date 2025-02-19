<?php

namespace Acquia\Cli\Command\Ide\Wizard;

use Acquia\Cli\Command\WizardCommandBase;
use Acquia\Cli\Helpers\SshCommandTrait;
use AcquiaCloudApi\Endpoints\Ides;
use AcquiaCloudApi\Response\IdeResponse;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

abstract class IdeWizardCommandBase extends WizardCommandBase {

  use SshCommandTrait;

  protected string|false $ideUuid;

  protected IdeResponse $ide;

  /**
   * Initializes the command just after the input has been validated.
   *
   * @param \Symfony\Component\Console\Input\InputInterface $input
   *   An InputInterface instance.
   * @param \Symfony\Component\Console\Output\OutputInterface $output
   *   An OutputInterface instance.
   */
  protected function initialize(InputInterface $input, OutputInterface $output): void {
    parent::initialize($input, $output);

    $this->ideUuid = $this::getThisCloudIdeUuid();
    $this->setSshKeyFilepath(self::getSshKeyFilename($this->ideUuid));
    $this->passphraseFilepath = $this->localMachineHelper->getLocalFilepath('~/.passphrase');
    $acquia_cloud_client = $this->cloudApiClientService->getClient();
    $ides_resource = new Ides($acquia_cloud_client);
    $this->ide = $ides_resource->get($this->ideUuid);
  }

  /**
   * @param $ide_uuid
   */
  public static function getSshKeyFilename($ide_uuid): string {
    return 'id_rsa_acquia_ide_' . $ide_uuid;
  }

  protected function validateEnvironment(): void {
    $this->requireCloudIdeEnvironment();
  }

  protected function getSshKeyLabel(): string {
    return $this::getIdeSshKeyLabel($this->ide);
  }

  protected function deleteThisSshKeyFromCloud($output): void {
    if ($cloud_key = $this->findIdeSshKeyOnCloud($this::getThisCloudIdeUuid())) {
      $this->deleteSshKeyFromCloud($output, $cloud_key);
    }
  }

}
