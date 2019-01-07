<?php

namespace Drupal\cci_cpnt_commerce\Plugin\Field\FieldWidget;

use Drupal\commerce_product\Plugin\Field\FieldWidget\ProductVariationWidgetBase;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'commerce_product_variation_title' widget.
 *
 * @FieldWidget(
 *   id = "commerce_product_variation_entity",
 *   label = @Translation("Product variation entity"),
 *   field_types = {
 *     "entity_reference"
 *   }
 * )
 */
class ProductVariationEntityWidget extends ProductVariationWidgetBase implements ContainerFactoryPluginInterface {

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return [
      'label_display' => TRUE,
      'label_text' => 'Please select',
      'view_mode' => 'Please select',
    ] + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $form = parent::settingsForm($form, $form_state);

    $view_modes = \Drupal::service('entity_display.repository')->getViewModes('commerce_product_variation');
    $view_mode_labels = array_map(function ($view_mode) {
      return $view_mode['label'];
    }, $view_modes);

    $form['label_display'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Display label'),
      '#default_value' => $this->getSetting('label_display'),
    ];
    $form['label_text'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Label text'),
      '#default_value' => $this->getSetting('label_text'),
      '#description' => $this->t('The label will be available to screen readers even if it is not displayed.'),
      '#required' => TRUE,
    ];

    $form['view_mode'] = array(
      '#type' => 'select',
      '#description' => $this->t('The display mode to use when rendering variation.'),
      '#title' => $this->t('View mode'),
      '#default_value' => $this->getSetting('view_mode'),
      '#options' => ['default' => $this->t('Default')] + $view_mode_labels,
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    $summary = parent::settingsSummary();
    $summary[] = $this->t('Label: "@text" (@visible)', [
      '@text' => $this->getSetting('label_text'),
      '@visible' => $this->getSetting('label_display') ? $this->t('visible') : $this->t('hidden'),
    ]);
    $summary[] = $this->t('Variation display mode: @mode', [
      '@mode' => $this->getSetting('view_mode'),
    ]);

    return $summary;
  }

  /**
   * {@inheritdoc}
   */
  public function formElement(FieldItemListInterface $items, $delta, array $element, array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\commerce_product\Entity\ProductInterface $product */
    $product = $form_state->get('product');
    $variations = $this->loadEnabledVariations($product);
    if (count($variations) === 0) {
      // Nothing to purchase, tell the parent form to hide itself.
      $form_state->set('hide_form', TRUE);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => 0,
      ];
      return $element;
    }
    elseif (count($variations) === 1) {
      /** @var \Drupal\commerce_product\Entity\ProductVariationInterface $selected_variation */
      $selected_variation = reset($variations);
      $element['variation'] = [
        '#type' => 'value',
        '#value' => $selected_variation->id(),
      ];
      return $element;
    }

    // Build the variation options form.
    $wrapper_id = Html::getUniqueId('commerce-product-add-to-cart-form');
    $form += [
      '#wrapper_id' => $wrapper_id,
      '#prefix' => '<div id="' . $wrapper_id . '">',
      '#suffix' => '</div>',
    ];
    $parents = array_merge($element['#field_parents'], [$items->getName(), $delta]);
    $user_input = (array) NestedArray::getValue($form_state->getUserInput(), $parents);
    if (!empty($user_input)) {
      $selected_variation = $this->selectVariationFromUserInput($variations, $user_input);
    }
    else {
      $selected_variation = $this->getDefaultVariation($product, $variations);
    }

    // Set the selected variation in the form state for our AJAX callback.
    $form_state->set('selected_variation', $selected_variation->id());

    $variation_options = [];
    foreach ($variations as $option) {
      $variation_options[$option->id()] = $option->label();
    }
    $element['variation'] = [
      '#type' => 'select',
      // Widget settings can't be translated in D8 yet, t() is a workaround.
      '#title' => $this->t($this->getSetting('label_text')),
      '#options' => $variation_options,
      '#required' => TRUE,
      '#default_value' => $selected_variation->id(),
      '#ajax' => [
        'callback' => [get_class($this), 'ajaxRefresh'],
        'wrapper' => $form['#wrapper_id'],
      ],
    ];

    $view_builder = \Drupal::entityTypeManager()->getViewBuilder('commerce_product_variation');
    $view_builder_view = $view_builder->view($selected_variation, $this->getSetting('view_mode'));
    $view_render = render($view_builder_view);

    $element['variation_content'] = [
      '#markup' => $view_render
    ];

    if ($this->getSetting('label_display') == FALSE) {
      $element['variation']['#title_display'] = 'invisible';
    }

    return $element;
  }

  /**
   * Selects a product variation from user input.
   *
   * If there's no user input (form viewed for the first time), the default
   * variation is returned.
   *
   * @param \Drupal\commerce_product\Entity\ProductVariationInterface[] $variations
   *   An array of product variations.
   * @param array $user_input
   *   The user input.
   *
   * @return \Drupal\commerce_product\Entity\ProductVariationInterface
   *   The selected variation.
   */
  protected function selectVariationFromUserInput(array $variations, array $user_input) {
    $current_variation = NULL;
    if (!empty($user_input['variation']) && $variations[$user_input['variation']]) {
      $current_variation = $variations[$user_input['variation']];
    }

    return $current_variation;
  }

}
