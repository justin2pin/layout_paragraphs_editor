<?php

namespace Drupal\layout_paragraphs_editor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityTypeManager;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\Core\Render\Renderer;
use Drupal\Core\DependencyInjection\DependencySerializationTrait;
use Drupal\Core\Layout\LayoutPluginManager;
use Drupal\Core\Entity\Entity\EntityViewDisplay;

/**
 * Layout Paragraphs Editor class.
 */
class LayoutParagraphsEditor implements LayoutParagraphsEditorInterface {

  use DependencySerializationTrait;

  /**
   * The entity type manager service.
   *
   * @var Drupal\Core\Entity\EntityTypeManager
   */
  protected $entityTypeManager;

  /**
   * The renderer service.
   *
   * @var Drupal\Core\Render\Renderer
   */
  protected $renderer;

  /**
   * The entity being edited.
   *
   * @var \Drupal\Core\Entity\EntityInterface
   */
  protected $entity;

  /**
   * The name of the field being edited.
   *
   * @var string
   */
  protected $fieldName;

  /**
   * The name of the view mode to use for rendering.
   *
   * @var string
   */
  protected $viewMode;

  /**
   * The layout paragraphs editor tempstore service.
   *
   * @var \Drupal\layout_paragraphs_editor\LayoutParagraphsEditorTempstoreInterface
   */
  protected $tempstore;

  /**
   * Temporarily saves a newly added item.
   *
   * @var \Drupal\paragraphs\ParagraphInterface
   */
  protected $newItem;

  /**
   * Class constructor.
   *
   * @param \Drupal\Core\Entity\EntityTypeManager $entity_type_manager
   *   The entity type manager service.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer service.
   * @param \Drupal\layout_paragraphs_editor\LayoutParagraphsEditorTempstoreInterface $tempstore
   *   The tempstore service.
   */
  public function __construct(
    EntityTypeManager $entity_type_manager,
    Renderer $renderer,
    LayoutParagraphsEditorTempstoreInterface $tempstore) {
    $this->entityTypeManager = $entity_type_manager;
    $this->renderer = $renderer;
    $this->tempstore = $tempstore;
  }

  /**
   * {@inheritDoc}
   */
  public function init(EntityInterface $entity, string $field_name, string $view_mode) {
    $this->entity = $entity;
    $this->fieldName = $field_name;
    $this->viewMode = $view_mode;
    $this->tempstore->delete($this);
    return $this->tempstore->get($this);
  }

  /**
   * {@inheritDoc}
   */
  public function load(EntityInterface $entity, string $field_name, string $view_mode) {
    $this->entity = $entity;
    $this->fieldName = $field_name;
    $this->viewMode = $view_mode;
    return $this->tempstore->get($this);
  }

  /**
   * {@inheritDoc}
   */
  public function save() {
    return $this->tempstore->set($this);
  }

  /**
   * {@inheritDoc}
   */
  public function updateState(array $state) {
    $uuid_map = [];
    foreach ($state as $item) {
      $uuid_map[$item['uuid']] = [
        'parent_uuid' => $item['parent'],
        'region' => $item['region'],
      ];
    }
    foreach ($this->entity->{$this->fieldName} as $field_item) {
      /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
      $paragraph =& $field_item->entity;
      $merge_behaviors = $uuid_map[$paragraph->uuid()] ?? [];
      $all_behaviors = $paragraph->getAllBehaviorSettings();
      $lp_behaviors = $all_behaviors['layout_paragraphs'] ?? [];
      $merged_behaviors = array_merge($lp_behaviors, $merge_behaviors);
    }
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function renderItem(ParagraphInterface $item) {
    $render_array = $this->render();
    $render_item = static::findInRenderArray($render_array, $item->uuid());
    $rendered = $this->renderer->render($render_item);
    return $rendered;
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    $entity_view_id = $this->entity->getEntityTypeId() . '.' . $this->entity->bundle() . '.' . $this->viewMode;
    $display_options = EntityViewDisplay::load($entity_view_id)->getComponent($this->fieldName);
    $display_options['settings']['editing'] = TRUE;
    $display_options['view_mode'] = $this->viewMode;
    $render_array = $this->entity->{$this->fieldName}->view($display_options);
    return $render_array;
  }

  /**
   * Recurses the render array and returns the rendered paragraph.
   *
   * @param array $render_array
   *   The render array.
   * @param string $uuid
   *   The uuid for the paragraph we are looking for.
   *
   * @return array
   *   The render array for a single paragraph.
   */
  protected static function findInRenderArray(array $render_array, string $uuid) {
    foreach ($render_array as $item) {
      if (is_array($item)) {
        if (isset($item['#paragraph'])) {
          if ($item['#paragraph']->uuid() == $uuid) {
            return $item;
          }
        }
        if ($item = static::findInRenderArray($item, $uuid)) {
          return $item;
        }
      }
    }
    return NULL;
  }

  /**
   * {@inheritDoc}
   */
  public function saveEntity() {
    $this->entity->save();
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function updateItem(ParagraphInterface $item) {
    foreach ($this->entity->{$this->fieldName} as $delta => $field_item) {
      if ($item->uuid() == $field_item->entity->uuid()) {
        $this->entity->{$this->fieldName}[$delta]->entity = $item;
      }
    }
    unset($this->newItem);
    return $this;
  }

  /**
   * {@inheritDoc}
   */
  public function insertAfter(ParagraphInterface $existing_paragraph, ParagraphInterface $new_paragraph) {
    return $this->insertSibling($existing_paragraph, $new_paragraph, 1);
  }

  /**
   * {@inheritDoc}
   */
  public function insertBefore(ParagraphInterface $existing_paragraph, ParagraphInterface $new_paragraph) {
    return $this->insertSibling($existing_paragraph, $new_paragraph);
  }

  /**
   * Insert an new item adjacent to $sibling.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $existing_paragraph
   *   The existing sibling paragraph.
   * @param \Drupal\paragraphs\ParagraphInterface $new_paragraph
   *   The type to add.
   * @param int $delta_offset
   *   Where to add the new item in relation to sibling.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   The new paragraph.
   */
  protected function insertSibling(
    ParagraphInterface $existing_paragraph,
    ParagraphInterface $new_paragraph,
    int $delta_offset = 0) {

    /** @var Drupal\paragraphs\ParagraphInterface $new_paragraph */
    $behavior_settings = $existing_paragraph->getAllBehaviorSettings()['layout_paragraphs'] ?? [];
    $new_paragraph->setParentEntity($this->entity, $this->fieldName);
    $new_paragraph->setBehaviorSettings('layout_paragraphs', $behavior_settings);
    $list = $this->entity->{$this->fieldName}->getValue();
    $delta = 0;
    foreach ($this->entity->{$this->fieldName} as $delta => $field_item) {
      if ($field_item->entity->uuid() == $existing_paragraph->uuid()) {
        break;
      }
    }
    $delta += $delta_offset;
    array_splice($list, $delta, 0, ['entity' => $new_paragraph]);
    $this->entity->{$this->fieldName}->setValue($list);
    return $new_paragraph;
  }

  /**
   * Creates a new paragraph of the given type.
   *
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The type of paragraph to create.
   *
   * @return Drupal\paragraphs\ParagraphsInterface
   *   The new paragraph.
   */
  protected function createItem(ParagraphsTypeInterface $paragraph_type) {

    if ($this->newItem && $this->newItem->bundle() == $paragraph_type->id()) {
      return $this->newItem;
    }

    $entity_type = $this->entityTypeManager->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager->getStorage('paragraph')
      ->create([
        $bundle_key => $paragraph_type->id(),
      ]);
    $paragraph->setParentEntity($this->entity, $this->fieldName);
    $this->newItem = $paragraph;
    return $paragraph;
  }

  /**
   * {@inheritDoc}
   *
   * @Todo handle nested items properly.
   */
  public function deleteItem(ParagraphInterface $item) {
    foreach ($this->entity->{$this->fieldName} as $delta => $field_item) {
      if ($item->uuid() == $field_item->entity->uuid()) {
        unset($this->entity->{$this->fieldName}[$delta]);
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getStorageKey() {
    return $this->entity->uuid() . "--" . $this->fieldName . "--" . $this->viewMode;
  }

  /**
   * {@inheritDoc}
   */
  public function getItemById($paragraph_id) {
    foreach ($this->entity->{$this->fieldName} as $field_item) {
      if ($field_item->entity->id() == $paragraph_id) {
        return $field_item->entity;
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getItemByUuid($paragraph_uuid) {
    foreach ($this->entity->{$this->fieldName} as $field_item) {
      if ($field_item->entity->uuid() == $paragraph_uuid) {
        return $field_item->entity;
      }
    }
  }

  /**
   * {@inheritDoc}
   */
  public function getRouteParameters() {
    return [
      'entity_type' => $this->entity->getEntityTypeId(),
      'entity' => $this->entity->id(),
      'field_name' => $this->fieldName,
      'view_mode' => $this->viewMode,
      'layout_paragraphs_editor' => $this->getStorageKey(),
    ];
  }

}
