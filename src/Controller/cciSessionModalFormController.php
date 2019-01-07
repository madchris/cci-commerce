<?php

namespace Drupal\cci_cpnt_commerce\Controller;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\node\Entity\Node;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class cciSessionModalFormController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The ModalFormExampleController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function openModalForm($node_id = NULL, $variation_id = NULL) {

    $values = [];

    if (!is_null($node_id) && !is_null($variation_id)) {
      // verification des arguments
      $variation = ProductVariation::load($variation_id);
      $node = \Drupal\node\Entity\Node::load($node_id);

      if ($this->verificationArguments($node, $variation)) {

        $values['#node'] = $node;
        $values['#variation'] = $variation;

        $this->getSessionsAndSalles($node, $variation, $values);

        if (!empty($values['#sessions'])) {
          return $response = $this->returnModal($values);
        }
      }
    }

    throw new AccessDeniedHttpException();
  }

  private function getSessionsAndSalles(Node $node = NULL, ProductVariation $variation = NULL, Array &$values = []) {
    $salles = [];
    $sessions = [];
    if (!is_null($node)
    && !is_null($variation)
    && $node->hasField('field_session')
    && $variation->hasField('field_date')) {
      $variation_date = $variation->get('field_date')->getValue();
      $session_reference = $node->get('field_session')->getValue();
      if (is_array($session_reference)
        && !empty($session_reference)) {
        foreach ($session_reference AS $session) {
          $node_session = Node::load($session['target_id']);
          $sessions[$session['target_id']] = $node_session;

          if ($node_session->hasField('field_salle')
          && $node_session->hasField('field_date')) {
            $session_date = $node_session->get('field_date')->getValue();
            if ($this->checkSessionVariationDate($session_date, $variation_date)) {
              $salle_reference = $node_session->get('field_salle')->getValue();
              if (is_array($salle_reference)
                && !empty($salle_reference)) {
                $salle_id = $salle_reference[0]['target_id'];

                $salles[$salle_id]['sessions'][strtotime($session_date[0]['value'])] = ['id' => $session['target_id'], 'title' => $node_session->getTitle()];
                if (!isset($salles[$salle_id]['salle'])) {
                  $salles[$salle_id]['salle'] = Node::load($salle_id);
                }
              }
            }
          }
        }
      }
    }



    $values['#sessions'] = $sessions;
    $values['#salles'] = $this->sortSessions($salles);
  }

  private function verificationArguments (Node $node = NULL, ProductVariation $variation = NULL){
    if (!is_null($node) && !is_null($variation)) {
      $product = $variation->getProduct();

      if ($node->hasField('field_produit')) {
        $field_product = $node->get('field_produit')->getValue();
        if (isset($field_product[0]['target_id'])
        && $field_product[0]['target_id'] === $product->id()) {
          return true;
        }
      }
    }

    return false;
  }

  private function checkSessionVariationDate(Array $session_date = [], Array $variation_date = []) {
    if (isset($session_date[0]['value'])
    && isset($variation_date[0]['value'])
    && isset($variation_date[0]['end_value'])) {
      $session_date_debut = strtotime($session_date[0]['value']);
      $variation_date_debut = strtotime($variation_date[0]['value']);
      $variation_date_fin = strtotime($variation_date[0]['end_value']);

      if ($session_date_debut >= $variation_date_debut
        && $session_date_debut <= $variation_date_fin) {
        return TRUE;
      }
    }

    return false;
  }

  private function returnModal(Array $values = []) {
    $response = new AjaxResponse();

    // Get the modal form using the form builder.
    $modal_form = \Drupal::formBuilder()->getForm('Drupal\cci_cpnt_commerce\Form\cciSessionModalForm', $values);

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Sélectionnez des sessions', $modal_form, [
      'width' => '80%',
      'closeOnEscape' => true,
    ]));

    $attachments = ['library' => ['cci_cpnt_commerce/event_session']];
    $response->addAttachments($attachments);

    return $response;
  }

  private function sortSessions(Array $salles = NULL) {
    if (!is_null($salles)
    && !empty($salles)) {
      foreach ($salles AS &$salle) {
        ksort($salle['sessions']);
      }
    }
    return $salles;
  }
}