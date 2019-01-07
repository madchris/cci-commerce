<?php

namespace Drupal\cci_cpnt_commerce;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Session\AccountInterface;
use Drupal\paragraphs\Entity\Paragraph;

class cciProductVariation extends \stdClass {

  private $productVariation;
  private $first_date = null;
  private $last_date = null;

  function __construct(ProductVariation $productVariation) {
    $this->productVariation = $productVariation;
  }

  public function getFirstLastDate() {
    $field_date = $this->productVariation->get('field_date')->getValue();
    foreach ($field_date AS $delta => $date) {

      if (isset($date['value'])) {
        $first_timestamp = strtotime($date['value']);

        if ($first_timestamp > 0
          && (is_null($this->first_date) || $this->first_date > $first_timestamp)) {
          $this->first_date = $first_timestamp;
        }
      }

      if (isset($date['end_value'])) {
        $last_timestamp = strtotime($date['end_value']);

        if ($last_timestamp > 0
          && (is_null($this->last_date) || $this->last_date < $last_timestamp)) {
          $this->last_date = $last_timestamp;
        }
      }
      else {
        $this->last_date = $this->first_date;
      }
    }

    return [
      'first_date' => $this->first_date,
      'last_date' => $this->last_date
    ];
  }

  public function getSessions() {
    $variation_dates = $this->getFirstLastDate();

    $sessions = \Drupal::entityQuery('paragraph')
      ->condition('type', 'session')
      ->condition('field_date.value', date('c', $variation_dates['first_date']-1), '>=')
      ->condition('field_date.end_value', date('c', $variation_dates['last_date']+1), '<=')
      ->condition('status', 1)
      ->execute();

    $entities = Paragraph::loadMultiple($sessions);

    return $entities;
  }

  public function getUserSessions(AccountInterface $user) {
    $sessions = \Drupal::entityQuery('paragraph')
      ->condition('type', 'session')
      ->condition('field_inscrit', $user->id())
      ->condition('status', 1)
      ->execute();

    $entities = Paragraph::loadMultiple($sessions);

    return $entities;
  }
}