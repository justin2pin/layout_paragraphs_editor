<?php

namespace Drupal\layout_paragraphs_editor\Routing;

use Drupal\Core\ParamConverter\ParamConverterInterface;
use Drupal\layout_paragraphs_editor\EditorTempstoreRepository;
use Symfony\Component\Routing\Route;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;

/**
 * Loads the layout paragraphs editor from the tempstore.
 *
 * @internal
 *   Tagged services are internal.
 */
class LayoutParagraphsEditorTempstoreParamConverter implements ParamConverterInterface {

  /**
   * The layout paragraphs editor tempstore.
   *
   * @var \Drupal\layout_paragraphs_editor\EditorTempstoreRepository
   */
  protected $layoutParagraphsEditorTempstore;

  /**
   * Constructs a new LayoutParagraphsEditorTempstoreParamConverter.
   *
   * @param \Drupal\layout_paragraphs_editor\EditorTempstoreRepository $layout_paragraphs_editor_tempstore
   *   The layout tempstore repository.
   */
  public function __construct(EditorTempstoreRepository $layout_paragraphs_editor_tempstore) {
    $this->layoutParagraphsEditorTempstore = $layout_paragraphs_editor_tempstore;
  }

  /**
   * {@inheritdoc}
   */
  public function convert($value, $definition, $name, array $defaults) {
    if (empty($defaults['entity']) | empty($defaults['field_name']) || empty($defaults['view_mode'])) {
      return NULL;
    }
    $layout = new LayoutParagraphsLayout($defaults['entity'], $defaults['field_name']);
    return $this->layoutParagraphsEditorTempstore->get($layout);
  }

  /**
   * {@inheritdoc}
   */
  public function applies($definition, $name, Route $route) {
    return !empty($definition['layout_paragraphs_editor_tempstore']);
  }

}
