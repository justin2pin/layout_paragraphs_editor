<?php

namespace Drupal\layout_paragraphs_editor;

use Drupal\Core\TempStore\PrivateTempStoreFactory;

/**
 * Layout Paragraphs Editor Tempstore class.
 */
class LayoutParagraphsEditorTempstore implements LayoutParagraphsEditorTempstoreInterface {

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
   * {@inheritDoc}
   */
  public function get(LayoutParagraphsEditorInterface $layout_paragraphs_editor) {
    $key = $layout_paragraphs_editor->getStorageKey();
    $tempstore_editor = $this->tempStoreFactory->get('layout_paragraphs_editor')->get($key);
    // Editor isn't in tempstore yet, so add it.
    if (empty($tempstore_editor)) {
      $tempstore_editor = $this->set($layout_paragraphs_editor);
    }
    return $tempstore_editor;
  }

  /**
   * {@inheritDoc}
   */
  public function set(LayoutParagraphsEditorInterface $layout_paragraphs_editor) {
    $key = $layout_paragraphs_editor->getStorageKey();
    $this->tempStoreFactory->get('layout_paragraphs_editor')->set($key, $layout_paragraphs_editor);
    return $layout_paragraphs_editor;
  }

  /**
   * {@inheritDoc}
   */
  public function delete(LayoutParagraphsEditorInterface $layout_paragraphs_editor) {
    $key = $layout_paragraphs_editor->getStorageKey();
    $this->tempStoreFactory->get('layout_paragraphs_editor')->delete($key);
  }

}
