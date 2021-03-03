<?php

namespace Drupal\layout_paragraphs_editor\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Url;
use Drupal\Core\Field\FieldDefinitionInterface;
use Drupal\layout_paragraphs\LayoutParagraphsService;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Entity\EntityDisplayRepositoryInterface;
use Drupal\Core\Entity\EntityTypeBundleInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\Routing\CurrentRouteMatch;
use Drupal\layout_paragraphs\Plugin\Field\FieldFormatter\LayoutParagraphsFormatter;
use Drupal\layout_paragraphs_editor\EditorTempstoreRepository;
use Drupal\Core\Form\FormBuilder;
use Drupal\Core\Session\AccountProxyInterface;

/**
 * Layout Paragraphs field formatter.
 *
 * @FieldFormatter(
 *   id = "layout_paragraphs_editor",
 *   label = @Translation("Layout Paragraphs Editor"),
 *   description = @Translation("Renders editable paragraphs with layout."),
 *   field_types = {
 *     "entity_reference_revisions"
 *   }
 * )
 */
class LayoutParagraphsEditorFormatter extends LayoutParagraphsFormatter {

  /**
   * The entity type bundle info service.
   *
   * @var \Drupal\Core\Entity\EntityTypeBundleInfo
   */
  protected $entityTypeBundleInfo;

  /**
   * The Entity Type Manager service property.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The Renderer service property.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The Layout Paragraphs Editor Tempstore service.
   *
   * @var \Drupal\layout_paragraphs_editor\EditorTempstoreRepository
   */
  protected $tempstore;

  /**
   * The current route match service.
   *
   * @var \Drupal\Core\Routing\CurrentRouteMatch
   */
  protected $routeMatch;

  /**
   * The form builder service.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  protected $currentUser;

  /**
   * {@inheritDoc}
   */
  public function __construct(
    $plugin_id,
    $plugin_definition,
    FieldDefinitionInterface $field_definition,
    array $settings,
    $label,
    $view_mode,
    array $third_party_settings,
    LoggerChannelFactoryInterface $logger_factory,
    EntityDisplayRepositoryInterface $entity_display_repository,
    LayoutParagraphsService $layout_paragraphs_service,
    EntityTypeBundleInfo $entity_type_bundle_info,
    EntityTypeManagerInterface $entity_type_manager,
    Renderer $renderer,
    EditorTempstoreRepository $layout_paragraphs_editor_tempstore,
    CurrentRouteMatch $route_match_service,
    FormBuilder $form_builder,
    AccountProxyInterface $current_user
    ) {
    parent::__construct($plugin_id,
      $plugin_definition,
      $field_definition,
      $settings,
      $label,
      $view_mode,
      $third_party_settings,
      $logger_factory,
      $entity_display_repository,
      $layout_paragraphs_service,
      $renderer
    );
    $this->entityTypeBundleInfo = $entity_type_bundle_info;
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->tempstore = $layout_paragraphs_editor_tempstore;
    $this->routeMatch = $route_match_service;
    $this->formBuilder = $form_builder;
    $this->currentUser = $current_user;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition) {
    return new static(
      $plugin_id,
      $plugin_definition,
      $configuration['field_definition'],
      $configuration['settings'],
      $configuration['label'],
      $configuration['view_mode'],
      $configuration['third_party_settings'],
      $container->get('logger.factory'),
      $container->get('entity_display.repository'),
      $container->get('layout_paragraphs'),
      $container->get('entity_type.bundle.info'),
      $container->get('entity_type.manager'),
      $container->get('renderer'),
      $container->get('layout_paragraphs_editor.tempstore_repository'),
      $container->get('current_route_match'),
      $container->get('form_builder'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function view(FieldItemListInterface $items, $langcode = NULL) {

    $elements['content'] = parent::view($items, $langcode);
    $entity = $items->getEntity();
    if (!$entity->access('update', $this->currentUser)) {
      return $elements['content'];
    }

    /** @var \Drupal\Core\Entity\EntityDefintion $definition */
    $definition = $items->getFieldDefinition();
    $field_name = $definition->get('field_name');
    $field_label = $definition->label();
    $layout = $this->tempstore->get(new LayoutParagraphsLayout($entity, $field_name));
    $base_url_params = [
      'entity_type' => $entity->getEntityTypeId(),
      'entity' => $entity->id(),
      'field_name' => $field_name,
      'view_mode' => $this->viewMode,
      'layout_paragraphs_layout' => $layout->id(),
    ];
    // Not editing, just add the edit button.
    if (!$this->isEditing()) {
      $elements['#attached']['drupalSettings']['layoutParagraphsEditor'][$layout->id()] = [];
      $elements['edit_button'] = [
        '#theme' => 'layout_paragraphs_editor_enable',
        '#url' => Url::fromRoute('layout_paragraphs_editor.editor', $base_url_params),
        '#title' => $this->t('Edit @field_name', ['@field_name' => $field_name]),
        '#field_label' => $field_label,
        '#is_empty' => $items->isEmpty(),
        '#weight' => '-1000',
        '#access' => $entity->access('update', $this->currentUser),
      ];
    }
    // Editing, so decorate with necessary ids and js settings.
    else {
      $allowed_component_types = $this->getAllowedTypes();
      $component_menu = [
        '#theme' => 'layout_paragraphs_editor_component_menu',
        '#types' => $allowed_component_types,
      ];
      $section_menu = [
        '#theme' => 'layout_paragraphs_editor_section_menu',
        '#types' => $allowed_component_types['layout'],
      ];
      $controls = [
        '#theme' => 'layout_paragraphs_editor_controls',
      ];
      $empty_container = [
        '#theme' => 'layout_paragraphs_editor_empty_container',
      ];
      $elements['content']['#attributes']['data-uuid'] = $items->getEntity()->uuid();
      $elements['#attached']['drupalSettings']['layoutParagraphsEditor'][$layout->id()] = [
        'selector' => '.js-' . $items->getEntity()->uuid() . '--' . $field_name,
        'componentMenu' => $this->renderer->render($component_menu),
        'sectionMenu' => $this->renderer->render($section_menu),
        'controls' => $this->renderer->render($controls),
        'toggleButton' => '<button class="lpe-toggle"><span class="visually-hidden">' . $this->t('Create Content') . '</span></button>',
        'baseUrl' => Url::fromRoute('layout_paragraphs_editor.editor', $base_url_params)->toString(),
        'emptyContainer' => $this->renderer->render($empty_container),
        'nestedSections' => FALSE,
        'requireSections' => TRUE,
      ];
      // Attach the main save/cancel buttons.
      $elements['actions'] = [
        '#theme' => 'layout_paragraphs_editor_banner',
        '#title' => $this->t('Editing @entity > @field', ['@entity' => $entity->label(), '@field' => $entity->{$field_name}->getFieldDefinition()->label()]),
        '#save_url' => Url::fromRoute('layout_paragraphs_editor.editor.save', $base_url_params)->toString(),
        '#cancel_url' => Url::fromRoute('layout_paragraphs_editor.cancel', $base_url_params)->toString(),
      ];
    }
    $elements['#type'] = 'container';
    $elements['#attributes'] = [
      'class' => [
        'lp-editor',
        'js-' . $entity->uuid() . '--' . $field_name,
      ],
      'data-lp-editor-id' => $layout->id(),
    ];
    $elements['#attached']['library'][] = 'layout_paragraphs_editor/layout_paragraphs_editor';

    return $elements;
  }

  /**
   * {@inheritDoc}
   */
  public static function defaultSettings() {
    $settings = ['editing' => FALSE] + parent::defaultSettings();
    return $settings;
  }

  /**
   * Returns an array of allowed types for building the create menu.
   *
   * @return array
   *   Nested array of allowed types with ids, names, and icons.
   */
  protected function getAllowedTypes() {
    $sorted_bundles = $this->getSortedAllowedTypes();
    $all_bundle_info = $this->entityTypeBundleInfo->getBundleInfo('paragraph');
    $storage = $this->entityTypeManager->getStorage('paragraphs_type');
    $types = [
      'layout' => [],
      'content' => [],
    ];
    foreach ($sorted_bundles as $bundle) {
      /** @var \Drupal\paragraphs\Entity\ParagraphsType $paragraphs_type */
      $paragraphs_type = $storage->load($bundle);
      $plugins = $paragraphs_type->getEnabledBehaviorPlugins();
      $has_layout = isset($plugins['layout_paragraphs']);
      $path = '';
      // Get the icon and pass to Javascript.
      if (method_exists($paragraphs_type, 'getIconUrl')) {
        $path = $paragraphs_type->getIconUrl();
      }
      $bundle_info = $all_bundle_info[$bundle];
      $types[($has_layout ? 'layout' : 'content')][] = [
        'id' => $bundle,
        'name' => $bundle_info['label'],
        'image' => $path,
        'title' => $this->t('New @name', ['@name' => $bundle_info['label']]),
      ];
    }
    return $types;
  }

  /**
   * Returns the sorted allowed paragraph types for this field.
   *
   * @return array
   *   The sorted types.
   */
  protected function getSortedAllowedTypes() {
    // @Todo: test with negate settings and without 'target_bundles_drag_drop'.
    // Use $this->entityTypeBundleInfo to get all possible paragraph types.
    $config = $this->getFieldSettings();
    $handler_settings = $config['handler_settings'];
    if (isset($handler_settings['target_bundles_drag_drop'])) {
      $bundles = array_filter($handler_settings['target_bundles_drag_drop'], function ($item) {
        return $item['enabled'];
      });
      uasort($bundles, 'Drupal\\Component\\Utility\\SortArray::sortByWeightElement');
    }
    return array_keys($bundles);
  }

  /**
   * Returns true if currently editing.
   */
  protected function isEditing() {
    return strpos($this->routeMatch->getRouteName(), 'layout_paragraphs_editor.editor') === 0;
  }

}
