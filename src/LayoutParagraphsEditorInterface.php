<?php

namespace Drupal\layout_paragraphs_editor;

use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\ParagraphInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;

/**
 * Provides methods for working with the layout paragraphse editor.
 */
interface LayoutParagraphsEditorInterface {

  /**
   * Initializes an editor instance and saves it to the tempstore.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being edited.
   * @param string $field_name
   *   The name of the paragraph reference field being edited.
   * @param string $view_mode
   *   The view mode to use for rendering the field.
   *
   * @return $this
   */
  public function init(EntityInterface $entity, string $field_name, string $view_mode);

  /**
   * Saves the editor to tempstore.
   *
   * @return $this
   */
  public function save();

  /**
   * Loads an editor from the tempstore.
   *
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity being edited.
   * @param string $field_name
   *   The name of the paragraph reference field being edited.
   * @param string $view_mode
   *   The view mode to use for rendering the field.
   *
   * @return LayoutParagraphsEditorInteface
   *   The loaded layout paragraphs editor from tempstore.
   */
  public function load(EntityInterface $entity, string $field_name, string $view_mode);

  /**
   * Updates the state of the paragraph reference field.
   *
   * Updates the order, region, and parent/child
   * relationship for entity field items.
   *
   * @param array $state
   *   A nested array where each item is an array
   *   with keys/values for uuid, parent_uuid, and region.
   *
   * @return $this
   */
  public function updateState(array $state);

  /**
   * Saves a paragraph instance referenced in the field.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $item
   *   The paragraph instance to save.
   *
   * @return $this
   */
  public function updateItem(ParagraphInterface $item);

  /**
   * Inserts a new paragraph before $item.
   *
   * The new paragraph should have the same parent and region
   * as $item, and be inserted directly before $item in the field collection.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $existing_paragraph
   *   The exisiting paragraph adjacent to the new one.
   * @param \Drupal\paragraphs\ParagraphInterface $new_paragraph
   *   The paragraph being added.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Returns the new paragraph that was added.
   */
  public function insertBefore(ParagraphInterface $existing_paragraph, ParagraphInterface $new_paragraph);

  /**
   * Inserts a new paragraph after $item.
   *
   * The new paragraph should have the same parent and region
   * as $item, and be inserted directly after $item in the field collection.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $existing_paragraph
   *   The exisiting paragraph adjacent to the new one.
   * @param \Drupal\paragraphs\ParagraphInterface $new_paragraph
   *   The paragraph being added.
   *
   * @return \Drupal\paragraphs\ParagraphInterface
   *   Returns the new paragraph that was added.
   */
  public function insertAfter(ParagraphInterface $existing_paragraph, ParagraphInterface $new_paragraph);

  /**
   * Deletes a paragraph item from the list collection.
   *
   * @param \Drupal\paragraphs\ParagraphInterface $item
   *   The item to delete.
   *
   * @return $this
   */
  public function deleteItem(ParagraphInterface $item);

  /**
   * Returns the render array for the entire edit field.
   *
   * @return $this
   */
  public function render();

  /**
   * Returns the render array for a single paragraph item.
   *
   * This function must render the entire field and return
   * the render array for the single item specified, in order
   * to correctly display the item in the appopriate context
   * with the correct view mode, etc.
   *
   * @param Drupal\paragraphs\ParagraphInterface $item
   *   The paragraph item to render.
   *
   * @return $this
   */
  public function renderItem(ParagraphInterface $item);

  /**
   * Saves the entity being edited.
   *
   * @return $this
   */
  public function saveEntity();

  /**
   * Returns a unique ID for the field being edited.
   *
   * @return string
   *   The unique storage key.
   */
  public function getStorageKey();

  /**
   * Returns the single paragraph in field collection with matching ID.
   *
   * @param int $paragraph_id
   *   The paragraph id.
   */
  public function getItemById(int $paragraph_id);

  /**
   * Returns the single paragraph in field collection with matching UUID.
   *
   * @param string $paragraph_uuid
   *   The paragraph uuid.
   */
  public function getItemByUuid(string $paragraph_uuid);

  /**
   * Gets the base route parameters for the layout paragraphs editor instance.
   *
   * @return array
   *   An associative array of route parameters.
   */
  public function getRouteParameters();

}
