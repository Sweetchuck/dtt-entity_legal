<?php

declare(strict_types = 1);

namespace Sweetchuck\DrupalTestTraits\EntityLegal\Behat\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Sweetchuck\DrupalTestTraits\Core\Behat\Context\Base;

class EntityLegal extends Base {

  protected string $acceptManuallyScenarioTag = 'entity_legal_accept_manually';

  /**
   * @todo Config entities should be reset by config:import.
   */
  protected array $originalState = [];

  /**
   * Disable legal document accept form notification.
   *
   * @BeforeScenario
   */
  public function disableLegalDocumentAcceptFormNotification(BeforeScenarioScope $scope) {
    $this->originalState = [];

    if ($this->hasToBeAcceptedManually($scope)) {
      return;
    }

    /** @var \Drupal\entity_legal\EntityLegalDocumentInterface[] $documents */
    $documents = \Drupal::entityTypeManager()
      ->getStorage(ENTITY_LEGAL_DOCUMENT_ENTITY_NAME)
      ->loadMultiple(NULL);

    foreach ($documents as $document) {
      if (!$document->get('require_signup') && !$document->get('require_existing')) {
        continue;
      }

      $this->originalState[$document->id()] = [
        'require_signup' => $document->get('require_signup'),
        'require_existing' => $document->get('require_existing'),
      ];

      $document
        ->set('require_signup', FALSE)
        ->set('require_existing', FALSE)
        ->save();
    }
  }

  /**
   * Enable legal document accept form notification.
   *
   * @AfterScenario
   */
  public function enableLegalDocumentAcceptFormNotification(AfterScenarioScope $scope) {
    if ($this->hasToBeAcceptedManually($scope)
      || !$this->originalState
    ) {
      return;
    }

    /** @var \Drupal\entity_legal\EntityLegalDocumentInterface[] $documents */
    $documents = \Drupal::entityTypeManager()
      ->getStorage(ENTITY_LEGAL_DOCUMENT_ENTITY_NAME)
      ->loadMultiple(array_keys($this->originalState));
    foreach ($documents as $document) {
      $originalState = $this->originalState[$document->id()];

      $document
        ->set('require_signup', $originalState['require_signup'])
        ->set('require_existing', $originalState['require_existing'])
        ->save();
    }
  }

  public function hasToBeAcceptedManually(ScenarioScope $scope): bool {
    return in_array(
      $this->acceptManuallyScenarioTag,
      $scope->getScenario()->getTags(),
    );
  }

}
