<?php

declare(strict_types = 1);

namespace Sweetchuck\DrupalTestTraits\EntityLegal\Behat\Context;

use Behat\Behat\Hook\Scope\AfterScenarioScope;
use Behat\Behat\Hook\Scope\BeforeScenarioScope;
use Behat\Behat\Hook\Scope\ScenarioScope;
use Behat\Gherkin\Node\TableNode;
use Drupal\entity_legal\EntityLegalDocumentInterface;
use PHPUnit\Framework\Assert;
use Sweetchuck\DrupalTestTraits\Core\Behat\Context\Base;
use Sweetchuck\DrupalTestTraits\Core\Behat\Context\Entity as EntityContext;

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

  /**
   * @Given :documentLabel entity legal acceptances:
   *
   * @code
   * # Minimal.
   * Given "my_terms" entity legal acceptances:
   *   | uid   |
   *   | Admin |
   * @endcode
   *
   * @code
   * # Long.
   * Given "my_terms" entity legal acceptances:
   *   | document_version_name | uid   | acceptance_date |
   *   | my_terms_12345        | Admin | yesterday       |
   * @endcode
   */
  public function doEntityLegalAcceptanceCreate(string $documentLabel, TableNode $table) {
    $entityContext = $this->getContext(EntityContext::class);

    /** @var \Drupal\entity_legal\EntityLegalDocumentInterface $document */
    $document = $entityContext->getEntityByLabel(
      'entity_legal_document',
      $documentLabel,
    );
    Assert::assertNotNull(
      $document,
      sprintf(
      'There is no Entity Legal Document with label: "%s"',
        $documentLabel,
      ),
    );

    $acceptancesFieldValues = $this->convertTableRowsToDocumentAcceptancesFieldValuesList(
      $document,
      $table,
      $this->getDocumentAcceptanceDefaultValues($document),
    );

    foreach ($acceptancesFieldValues as $acceptanceFieldValues) {
      $entityContext->createContentEntity('entity_legal_document_acceptance', $acceptanceFieldValues);
    }
  }

  public function convertTableRowsToDocumentAcceptancesFieldValuesList(
    EntityLegalDocumentInterface $document,
    TableNode $table,
    array $default
  ): array {
    $fieldValuesList = [];
    foreach ($table->getColumnsHash() as $row) {
      $fieldValuesList[] = $this->convertTableRowToDocumentAcceptanceFieldValues($document, $row, $default);
    }

    return $fieldValuesList;
  }

  public function convertTableRowToDocumentAcceptanceFieldValues(
    EntityLegalDocumentInterface $document,
    array $row,
    array $default
  ): array {
    if (empty($row['document_version_name']) && !empty($default['document_version_name'])) {
      $row['document_version_name'] = $default['document_version_name'];
    }

    $row['acceptance_date'] = !empty($row['acceptance_date']) ?
      strtotime($row['acceptance_date'])
      : ($default['acceptance_date'] ?? NULL);

    return $row;
  }

  public function getDocumentAcceptanceDefaultValues(EntityLegalDocumentInterface $document): array {
    $default = [
      'acceptance_date' => time(),
    ];

    $documentVersion = $document->getPublishedVersion();
    if ($documentVersion) {
      $default['document_version_name'] = $documentVersion->label();
    }

    return $default;
  }

}
