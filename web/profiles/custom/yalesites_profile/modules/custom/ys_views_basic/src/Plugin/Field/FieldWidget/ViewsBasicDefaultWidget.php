<?php

namespace Drupal\ys_views_basic\Plugin\Field\FieldWidget;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\WidgetBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\ys_views_basic\ViewsBasicManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Plugin implementation of the 'views_basic_default' widget.
 *
 * @FieldWidget(
 *   id = "views_basic_default_widget",
 *   label = @Translation("Views basic default widget"),
 *   field_types = {
 *     "views_basic_params"
 *   }
 * )
 */
class ViewsBasicDefaultWidget extends WidgetBase implements ContainerFactoryPluginInterface {

  /**
   * The views basic manager service.
   *
   * @var \Drupal\ys_views_basic\ViewsBasicManager
   */
  protected $viewsBasicManager;

  /**
   * Constructs a ViewsBasicDefaultWidget object.
   *
   * @param string $plugin_id
   *   The plugin_id for the widget.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Field\FieldDefinitionInterface $field_definition
   *   The definition of the field to which the widget is associated.
   * @param array $settings
   *   The widget settings.
   * @param array $third_party_settings
   *   Any third party settings.
   * @param \Drupal\ys_views_basic\ViewsBasicManager $views_basic_manager
   *   The ViewsBasic management service.
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    array $third_party_settings,
    ViewsBasicManager $views_basic_manager,
  ) {
    parent::__construct(
      $plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $third_party_settings
    );
    $this->viewsBasicManager = $views_basic_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition,
  ) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['third_party_settings'],
      $container->get('ys_views_basic.views_basic_manager')
    );
  }

  /**
   * Define the form for the field type.
   */
  public function formElement(
    FieldItemListInterface $items,
    $delta,
    Array $element,
    Array &$form,
    FormStateInterface $formState,
  ) {

    $entity_list = $this->viewsBasicManager->entityTypeList();
    $entityValue = array_key_first($entity_list);
    $decodedParams = json_decode($items[$delta]->params, TRUE);
    if (!empty($decodedParams['filters']['types'][0])) {
      $entityValue = $decodedParams['filters']['types'][0];
    }

    $formSelectors = $this->viewsBasicManager->getFormSelectors($formState, NULL, $entityValue);
    $form['#form_selectors'] = $formSelectors;

    $element['group_params'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'views-basic--params',
        ],
      ],
    ];

    $form['group_user_selection'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'views-basic--group-user-selection',
        ],
        'data-drupal-ck-style-fence' => '',
      ],
      '#weight' => 10,
    ];

    $form['group_user_selection']['entity_and_view_mode'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'grouped-items',
          'views-basic--entity-view-mode',
        ],
      ],
    ];

    $form['group_user_selection']['filter_and_sort'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'grouped-items',
        ],
      ],
    ];

    $form['group_user_selection']['filter_options'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'grouped-items',
        ],
      ],
    ];

    $form['group_user_selection']['entity_specific'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'grouped-items',
        ],
      ],
    ];

    $form['group_user_selection']['options'] = [
      '#type' => 'container',
      '#attributes' => [
        'class' => [
          'grouped-items',
        ],
      ],
    ];

    $form['group_user_selection']['entity_and_view_mode']['entity_types'] = [
      '#type' => 'radios',
      '#options' => $this->viewsBasicManager->entityTypeList(),
      '#title' => $this->t('I Want To Show'),
      '#tree' => TRUE,
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('types', $items[$delta]->params) : NULL,
      '#wrapper_attributes' => [
        'class' => [
          'views-basic--user-selection',
          'views-basic--entity-types',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'updateOtherSettings'],
        'disable-refocus' => FALSE,
        'event' => 'change',
        'progress' => [
          'type' => 'none',
        ],
      ],
    ];

    // Gets the view mode options based on Ajax callbacks or initial load.
    $viewModeOptions = $this->viewsBasicManager->viewModeList($formSelectors['entity_types']);

    $form['group_user_selection']['entity_and_view_mode']['view_mode'] = [
      '#type' => 'radios',
      '#options' => $viewModeOptions,
      '#title' => $this->t('As'),
      '#tree' => TRUE,
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('view_mode', $items[$delta]->params) : key($viewModeOptions),
      '#attributes' => [
        'class' => [
          'views-basic--view-mode',
        ],
      ],
      '#wrapper_attributes' => [
        'class' => [
          'views-basic--user-selection',
        ],
      ],
      '#validated' => 'true',
      '#prefix' => '<div id="edit-view-mode">',
      '#suffix' => '</div>',
    ];

    $form['group_user_selection']['filter_and_sort']['terms_include'] = [
      '#title' => $this->t('Include content that uses the following tags or categories'),
      '#type' => 'select',
      '#options' => $this->viewsBasicManager->getAllTags(),
      '#chosen' => TRUE,
      '#multiple' => TRUE,
      '#tags' => TRUE,
      '#target_type' => 'taxonomy_term',
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('terms_include', $items[$delta]->params) : [],
    ];

    $form['group_user_selection']['filter_and_sort']['terms_exclude'] = [
      '#title' => $this->t('Exclude content that uses the following tags or categories'),
      '#type' => 'select',
      '#options' => $this->viewsBasicManager->getAllTags(),
      '#multiple' => TRUE,
      '#chosen' => TRUE,
      '#tags' => TRUE,
      '#target_type' => 'taxonomy_term',
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('terms_exclude', $items[$delta]->params) : [],
    ];

    $form['group_user_selection']['filter_and_sort']['term_operator'] = [
      '#type' => 'radios',
      '#title' => $this->t('Match Content That Has'),
      // Set operator: "+" is "OR" and "," is "AND".
      '#options' => [
        '+' => $this->t('Can have any term listed in tags and categories'),
        ',' => $this->t('Must have all terms listed in tags and categories'),
      ],
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('operator', $items[$delta]->params) : '+',
      '#attributes' => [
        'class'     => [
          'term-operator-item',
        ],
      ],

    ];

    // Gets the view mode options based on Ajax callbacks or initial load.
    $sortOptions = $this->viewsBasicManager->sortByList($formSelectors['entity_types']);

    $form['group_user_selection']['filter_and_sort']['sort_by'] = [
      '#type' => 'select',
      '#description' => $this->t('Items marked "Pin to the beginning of list" will precede the selected sort.'),
      '#options' => $sortOptions,
      '#title' => $this->t('Sorting by'),
      '#tree' => TRUE,
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('sort_by', $items[$delta]->params) : NULL,
      '#validated' => 'true',
      '#prefix' => '<div id="edit-sort-by">',
      '#suffix' => '</div>',
    ];

    $form['group_user_selection']['entity_specific']['event_time_period'] = [
      '#type' => 'radios',
      '#title' => $this->t('Event Time Period'),
      '#options' => [
        'future' => $this->t('Future Events') . '<img src="/profiles/custom/yalesites_profile/modules/custom/ys_views_basic/assets/icons/event-time-future.svg" alt="Future Events icon showing a calendar with a future-pointing arrow to the right.">',
        'past' => $this->t('Past Events') . '<img src="/profiles/custom/yalesites_profile/modules/custom/ys_views_basic/assets/icons/event-time-past.svg" alt="Past Events icon showing a calendar with a past-pointing arrow to the left.">',
        'all' => $this->t('All Events') . '<img src="/profiles/custom/yalesites_profile/modules/custom/ys_views_basic/assets/icons/event-time-all.svg" alt="All Events icon showing a calendar.">',
      ],
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('event_time_period', $items[$delta]->params) : 'future',
      '#states' => [
        'visible' => [
          $formSelectors['entity_types_ajax'] => [
            'value' => 'event',
          ],
        ],
      ],
    ];

    $displayValue = ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('display', $items[$delta]->params) : 'all';

    $form['group_user_selection']['options']['display'] = [
      '#type' => 'select',
      '#title' => $this
        ->t('Number of Items to Display'),
      '#default_value' => $displayValue,
      '#options' => [
        'all' => $this->t('Display all items'),
        'limit' => $this->t('Limit to'),
        'pager' => $this->t('Pagination after'),
      ],
    ];

    $limitTitle = $this->t('Items');

    if ($displayValue && $displayValue == 'pager') {
      $limitTitle = $this->t('Items per Page');
    }

    /*
     * Dynamic changes to this is handled in javascript due to issues with
     * callbacks and #states.
     */
    $form['group_user_selection']['options']['limit'] = [
      '#title' => $limitTitle,
      '#type' => 'number',
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('limit', $items[$delta]->params) : 10,
      '#min' => 1,
      '#required' => TRUE,
      '#prefix' => '<div id="edit-limit">',
      '#suffix' => '</div>',
    ];

    $form['group_user_selection']['options']['offset'] = [
      '#title' => 'Ignore Number of Results',
      '#description' => $this->t('Specify the number of results you want to ignore. If you enter "2", your view will omit the first two results that match the overall parameters you\'ve set in the view interface.'),
      '#type' => 'number',
      '#default_value' => ($items[$delta]->params) ? $this->viewsBasicManager->getDefaultParamValue('offset', $items[$delta]->params) : 0,
      '#min' => 0,
      '#attributes' => [
        'placeholder' => 0,
      ],
    ];

    $element['group_params']['params'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Params'),
      '#default_value' => $items[$delta]->params ?? NULL,
      '#empty_value' => '',
      '#attributes' => [
        'class'     => [
          'views-basic--params',
        ],
      ],
    ];

    $form['#attached']['library'][] = 'ys_views_basic/ys_views_basic';

    return $element;
  }

  /**
   * Get data from user selection and save into params field.
   */
  public function massageFormValues(array $values, array $form, FormStateInterface $form_state) {

    $formSelectors = $this->viewsBasicManager->getFormSelectors($form_state);

    $terms_include = ($form_state->getValue($formSelectors['massage_terms_include_array'])) ?? NULL;
    $terms_exclude = ($form_state->getValue($formSelectors['massage_terms_exclude_array'])) ?? NULL;

    foreach ($values as &$value) {
      $paramData = [
        "view_mode" => $form['group_user_selection']['entity_and_view_mode']['view_mode']['#value'],
        "filters" => [
          "types" => [
            $form['group_user_selection']['entity_and_view_mode']['entity_types']['#value'],
          ],
          "terms_include" => $terms_include,
          "terms_exclude" => $terms_exclude,
          "event_time_period" => $form['group_user_selection']['entity_specific']['event_time_period']['#value'],
        ],
        "operator" => $form['group_user_selection']['filter_and_sort']['term_operator']['#value'],
        "sort_by" => $form_state->getValue($formSelectors['sort_by_array']),
        "display" => $form_state->getValue($formSelectors['display_array']),
        "limit" => (int) $form_state->getValue($formSelectors['limit_array']),
        "offset" => (int) $form_state->getValue($formSelectors['offset_array']),
      ];
      $value['params'] = json_encode($paramData);
    }
    return $values;
  }

  /**
   * Ajax callback to return only view modes for the specified content type.
   */
  public function updateOtherSettings(array &$form, FormStateInterface $form_state) {
    $formSelectors = $this->viewsBasicManager->getFormSelectors($form_state, $form);

    $response = new AjaxResponse();
    $response->addCommand(new ReplaceCommand('#edit-view-mode', $formSelectors['view_mode_ajax']));
    $selector = '.views-basic--view-mode[name="group_user_selection[entity_and_view_mode][view_mode]"]:first';
    $response->addCommand(new InvokeCommand($selector, 'prop', [['checked' => TRUE]]));
    $response->addCommand(new ReplaceCommand('#edit-sort-by', $formSelectors['sort_by_ajax']));
    return $response;
  }

}
