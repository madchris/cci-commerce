<?php

namespace Drupal\cci_cpnt_commerce\Form;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_cart\CartManagerInterface;
use Drupal\commerce_cart\CartProviderInterface;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_order\Resolver\OrderTypeResolverInterface;
use Drupal\commerce_price\Price;
use Drupal\commerce_price\Resolver\ChainPriceResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\RedirectCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;

class cciSessionModalForm extends FormBase {

  /**
   * The cart manager.
   *
   * @var \Drupal\commerce_cart\CartManagerInterface
   */
  protected $cartManager;

  /**
   * The cart provider.
   *
   * @var \Drupal\commerce_cart\CartProviderInterface
   */
  protected $cartProvider;

  /**
   * The order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\OrderTypeResolverInterface
   */
  protected $orderTypeResolver;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  /**
   * The chain base price resolver.
   *
   * @var \Drupal\commerce_price\Resolver\ChainPriceResolverInterface
   */
  protected $chainPriceResolver;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * The form ID.
   *
   * @var string
   */
  protected $formId;

  /**
   * Constructs a new AddToCartForm object.
   *
   * @param \Drupal\commerce_cart\CartManagerInterface $cart_manager
   *   The cart manager.
   * @param \Drupal\commerce_cart\CartProviderInterface $cart_provider
   *   The cart provider.
   * @param \Drupal\commerce_order\Resolver\OrderTypeResolverInterface $order_type_resolver
   *   The order type resolver.
   * @param \Drupal\commerce_store\CurrentStoreInterface $current_store
   *   The current store.
   * @param \Drupal\commerce_price\Resolver\ChainPriceResolverInterface $chain_price_resolver
   *   The chain base price resolver.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The current user.
   */
  public function __construct(CartManagerInterface $cart_manager, CartProviderInterface $cart_provider, OrderTypeResolverInterface $order_type_resolver, CurrentStoreInterface $current_store, ChainPriceResolverInterface $chain_price_resolver, AccountInterface $current_user) {

    $this->cartManager = $cart_manager;
    $this->cartProvider = $cart_provider;
    $this->orderTypeResolver = $order_type_resolver;
    $this->currentStore = $current_store;
    $this->chainPriceResolver = $chain_price_resolver;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('commerce_cart.cart_manager'),
      $container->get('commerce_cart.cart_provider'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store'),
      $container->get('commerce_price.chain_price_resolver'),
      $container->get('current_user')
    );
  }

  public function getFormId() {
    return 'cci_session_modal_form';
  }

  public function buildForm(array $form, FormStateInterface $form_state, $values = NULL) {
    $variation = $values['#variation'];
    $sessions = $values['#sessions'];
    $salles = $values['#salles'];
    $salle_ids = array_keys($salles);

    $form['#prefix'] = '<div id="modal_session_form">';
    $form['#suffix'] = '</div>';

    foreach ($salles AS $salle_id => $salle) {
      $form['salle_'.$salle_id] = [
        '#type' => 'checkboxes',
        '#options' => $this->getSalleSessions($salle['sessions']),
        '#title' => $salle['salle']->getTitle(),
        '#default_value' => isset($salle['sessions_default']) ? $salle['sessions_default'] : [],
        '#process' => [
          array($this, 'processCheckboxes'),
        ],
        '#objects' => $sessions,
        '#attributes' => [
          'class' => ['row']
        ]
      ];
    }

    $form['action']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t("S'inscrire"),
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['#variation'] = $variation;
    $form['#salles'] = $salle_ids;

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $form['#attached']['library'][] = 'cci_cpnt_commerce/event_session';
    $form['#submit'] = ['\Drupal\cci_cpnt_commerce\Form\cciSessionModalForm::submitForm'];

    return $form;
  }

  private function getSalleSessions(Array $salle = NULL) {
    $sessions = [];
    if (!is_null($salle)) {
      foreach ($salle AS $session) {
        $sessions[$session['id']] = $session['title'];
      }
    }

    return $sessions;
  }

  public function submitModalFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();

    //save cart
    $saved_sessions = [];
    $salles = $form['#salles'];
    if (is_array($salles)
    && !empty($salles)) {
      foreach ($salles AS $salle) {
        $sessions = $form_state->getValue('salle_' . $salle);
        if (is_array($sessions)
          && !empty($sessions)) {
          foreach ($sessions AS $session_id => $session_value) {
            if ($session_value !== 0) {
              $saved_sessions[] = ['target_id' => $session_id];
            }
          }
        }
      }
    }

    /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
    $variation = $form['#variation'];
    $order_item = OrderItem::create([
      'title' => $variation->getTitle(),
      'type' => 'evenement',
      'quantity' => 1,
      'unit_price' => $variation->getPrice(),
      'purchased_entity' => $variation,
      'field_session' => $saved_sessions
    ]);

    /** @var \Drupal\commerce\PurchasableEntityInterface $purchased_entity */
    $purchased_entity = $order_item->getPurchasedEntity();

    $order_type_id = $this->orderTypeResolver->resolve($order_item);
    $store = $this->selectStore($purchased_entity);
    $cart = $this->cartProvider->getCart($order_type_id, $store);
    if (!$cart) {
      $cart = $this->cartProvider->createCart($order_type_id, $store);
    }
    $this->entity = $this->cartManager->addOrderItem($cart, $order_item, $form_state->get(['settings', 'combine']));
    // Other submit handlers might need the cart ID.
    $form_state->set('cart_id', $cart->id());


    // If there are any form errors, re-display the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_session_form', $form));
    }
    else {
      $url = \Drupal\Core\Url::fromRoute('commerce_cart.page')->toString();
      $response->addCommand(new RedirectCommand($url));
    }

    return $response;

  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * Process checkboxes
   */
  public static function processCheckboxes(&$element, FormStateInterface $form_state, &$complete_form) {
    $value = is_array($element['#value']) ? $element['#value'] : array();
    $element['#tree'] = TRUE;
    if (count($element['#options']) > 0) {
      if (!isset($element['#default_value']) || $element['#default_value'] == 0) {
        $element['#default_value'] = array();
      }
      $weight = 0;
      foreach ($element['#options'] as $key => $choice) {
        $session = $element['#objects'][$key];
        $wrapper_class = ['session-wrapper'];

        $view_builder = \Drupal::entityTypeManager()->getViewBuilder('node');
        $view_builder_view = $view_builder->view($session, 'teaser');
        $view_render = render($view_builder_view);

        if ($key === 0) {
          $key = '0';
        }
        $weight += 0.001;

        if (isset($value[$key])) $wrapper_class[] = 'selected';

        $element['#attributes']['class'][] = 'col col-md-3';

        $element += array($key => array());
        $element[$key] += array(
          '#type' => 'checkbox',
          '#title' => $choice,
          '#return_value' => $key,
          '#default_value' => isset($value[$key]) ? $key : NULL,
          '#attributes' => $element['#attributes'],
          '#ajax' => isset($element['#ajax']) ? $element['#ajax'] : NULL,
          '#error_no_message' => TRUE,
          '#weight' => $weight,
          '#disabled' => isset($value[$key]) ? TRUE : FALSE,
          '#prefix' => '<div class="'.implode(' ', $wrapper_class).'" id="session_'.$session->id().'">'.$view_render,
          '#suffix' => '</div>'
        );
      }
    }
    return $element;
  }


  /**
   * Selects the store for the given purchasable entity.
   *
   * If the entity is sold from one store, then that store is selected.
   * If the entity is sold from multiple stores, and the current store is
   * one of them, then that store is selected.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The entity being added to cart.
   *
   * @throws \Exception
   *   When the entity can't be purchased from the current store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The selected store.
   */
  protected function selectStore(PurchasableEntityInterface $entity) {
    $stores = $entity->getStores();
    if (count($stores) === 1) {
      $store = reset($stores);
    }
    elseif (count($stores) === 0) {
      // Malformed entity.
      throw new \Exception('The given entity is not assigned to any store.');
    }
    else {
      $store = $this->currentStore->getStore();
      if (!in_array($store, $stores)) {
        // Indicates that the site listings are not filtered properly.
        throw new \Exception("The given entity can't be purchased from the current store.");
      }
    }

    return $store;
  }

}