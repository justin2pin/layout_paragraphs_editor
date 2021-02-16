<?php

namespace Drupal\layout_paragraphs_editor;

use Drupal\Core\TempStore\PrivateTempStoreFactory;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Layout Paragraphs Editor Tempstore Repository class definition.
 */
class EditorTempstoreRepository {

  /**
   * The shared tempstore factory.
   *
   * @var \Drupal\Core\TempStore\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * LayoutTempstoreRepository constructor.
   *
   * @param \Drupal\Core\TempStore\PrivateTempStoreFactory $temp_store_factory
   *   The shared tempstore factory.
   */
  public function __construct(PrivateTempStoreFactory $temp_store_factory) {
    $this->tempStoreFactory = $temp_store_factory;
  }

  /**
   * Get a layout paragraphs layout from the tempstore.
   */
  public function get(LayoutParagraphsLayout $layout_paragraphs_layout) {
    $key = $this->getStorageKey($layout_paragraphs_layout);
    $tempstore_layout = $this->tempStoreFactory->get('layout_paragraphs_editor')->get($key);
    // Editor isn't in tempstore yet, so add it.
    if (empty($tempstore_layout)) {
      $tempstore_layout = $this->set($layout_paragraphs_layout);
    }
    return $tempstore_layout;
  }

  /**
   * Save a layout paragraphs layout to the tempstore.
   */
  public function set(LayoutParagraphsLayout $layout_paragraphs_layout) {
    $key = $this->getStorageKey($layout_paragraphs_layout);
    $this->tempStoreFactory->get('layout_paragraphs_editor')->set($key, $layout_paragraphs_layout);
    return $layout_paragraphs_layout;
  }

  /**
   * Delete a layout from tempstore.
   */
  public function delete(LayoutParagraphsLayout $layout_paragraphs_layout) {
    $key = $this->getStorageKey($layout_paragraphs_layout);
    $this->tempStoreFactory->get('layout_paragraphs_editor')->delete($key);
  }

  /**
   * Returns a unique key for storing the layout.
   *
   * @param \Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout object.
   *
   * @return string
   *   The unique key.
   */
  protected function getStorageKey(LayoutParagraphsLayout $layout_paragraphs_layout) {
    return $layout_paragraphs_layout->getEntity()->uuid() .
      '--' .
      $layout_paragraphs_layout->getFieldName();
  }

}
