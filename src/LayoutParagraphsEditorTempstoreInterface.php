<?php

namespace Drupal\layout_paragraphs_editor;

/**
 * Interface definition for Layout Paragraphs Editor Temp Store Repository.
 */
interface LayoutParagraphsEditorTempstoreInterface {

  /**
   * Retrieve a LayoutParagraphsEditor from the temp store.
   *
   * @return LayoutParagraphsEditor
   *   The layout paragraphs editor instance.
   */
  public function get(LayoutParagraphsEditorInterface $layout_paragraphs_editor);

  /**
   * Save a LayoutParagraphsEditor to the temp store.
   *
   * @return LayoutParagraphsEditor
   *   The layout paragraphs editor instance.
   */
  public function set(LayoutParagraphsEditorInterface $layout_paragraphs_editor);

  /**
   * Deletes a LayoutParagraphsEditor from the temp store.
   */
  public function delete(LayoutParagraphsEditorInterface $layout_paragraphs_editor);

}
