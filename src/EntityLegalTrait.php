<?php

declare(strict_types = 1);

namespace Sweetchuck\DrupalTestTraits\EntityLegal;

use Drupal\Core\Session\AccountInterface;
use Drupal\entity_legal\EntityLegalDocumentInterface;

trait EntityLegalTrait {

  /**
   * @param \Drupal\Core\Session\AccountInterface $account
   * @param null|\Drupal\entity_legal\EntityLegalDocumentInterface[] $documents
   *
   * @return $this
   */
  protected function entityLegalAcceptDocuments(AccountInterface $account, ?array $documents = NULL) {
    if ($documents === NULL) {
      $documents = \Drupal::entityTypeManager()
        ->getStorage('entity_legal_document')
        ->loadMultiple(NULL);
    }

    foreach ($documents as $document) {
      $this->entityLegalAcceptDocument($account, $document);
    }

    return $this;
  }

  /**
   * @return $this
   */
  protected function entityLegalAcceptDocument(AccountInterface $account, EntityLegalDocumentInterface $document) {
    if (!$document->userMustAgree(FALSE, $account) || $document->userHasAgreed($account)) {
      return $this;
    }

    $publishedVersion = $document->getPublishedVersion();
    $acceptanceStorage = \Drupal::entityTypeManager()->getStorage('entity_legal_document_acceptance');
    $acceptance = $acceptanceStorage->create([
      'document_version_name' => $publishedVersion->id(),
      'uid' => $account->id(),
    ]);
    $acceptance->save();

    return $this;
  }

}
