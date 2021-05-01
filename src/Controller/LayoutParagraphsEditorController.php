<?php

namespace Drupal\layout_paragraphs_editor\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Ajax\AjaxHelperTrait;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Entity\EntityInterface;
use Drupal\paragraphs\ParagraphsTypeInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\layout_paragraphs_editor\EditorTempstoreRepository;
use Symfony\Component\HttpFoundation\Request;
use Drupal\Component\Serialization\Json;
use Drupal\layout_paragraphs_editor\Ajax\LayoutParagraphsEditorInvokeHookCommand;
use Drupal\Core\Entity\RevisionableInterface;
use Drupal\Core\Entity\RevisionLogInterface;

/**
 * LayoutParagraphsEditor controller class.
 */
class LayoutParagraphsEditorController extends ControllerBase {

  use AjaxHelperTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs_editor\EditorTempstoreRepository
   */
  protected $editorTempstore;

  /**
   * Settings to pass to jQuery modal dialog.
   *
   * @var array
   */
  protected $modalSettings;

  /**
   * Construct a Layout Paragraphs Editor controller.
   *
   * @param \Drupal\layout_paragraphs_editor\EditorTempstoreRepository $editor_tempstore
   *   The tempstore service.
   */
  public function __construct(EditorTempstoreRepository $editor_tempstore) {
    $this->editorTempstore = $editor_tempstore;
    $this->modalSettings = [
      'width' => '70%',
      'minWidth' => 500,
      'draggable' => TRUE,
      'classes' => [
        'ui-dialog' => 'lpe-dialog',
      ],
      'modal' => TRUE,
    ];
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs_editor.tempstore_repository')
    );
  }

  /**
   * Initializes a layout paragraphs editor.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   An ajax command to inject the edit field.
   */
  public function init(
    EntityInterface $entity,
    string $field_name,
    string $view_mode) {

    $layout_paragraphs_layout = new LayoutParagraphsLayout($entity, $field_name);
    $this->editorTempstore->set($layout_paragraphs_layout);
    $rendered_field = $entity->{$field_name}->view($view_mode);

    $response = new AjaxResponse();
    $selector = '[data-lp-editor-id="' . $layout_paragraphs_layout->id() . '"]';
    $response->addCommand(new ReplaceCommand($selector, $rendered_field));
    return $response;
  }

  /**
   * Returns a paragraph edit form as a dialog.
   *
   * @param Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The editor instance.
   * @param string $paragraph_uuid
   *   The uuid of the paragraph we are editing.
   *
   * @return AjaxCommand
   *   The dialog command with edit form.
   */
  public function editForm(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout, string $paragraph_uuid) {
    $response = new AjaxResponse();

    if ($ordered_components = Json::decode($request->request->get("layoutParagraphsState"))) {
      $layout_paragraphs_layout->reorderComponents($ordered_components);
      $this->editorTempstore->set($layout_paragraphs_layout);
    }

    $paragraph = $layout_paragraphs_layout
      ->getComponentByUuid($paragraph_uuid)
      ->getEntity();
    $paragraph_type = $paragraph->getParagraphType();
    $label = $this->t('Edit @type', ['@type' => $paragraph_type->label()]);
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs_editor\Form\LayoutParagraphsEditorEditForm',
      $layout_paragraphs_layout,
      $paragraph,
      ['insert' => FALSE]
    );

    $this->addFormResponse($response, $label, $form);
    return $response;
  }

  /**
   * Saves the entity with the layout applied.
   *
   * @param Request $request
   * @param LayoutParagraphsLayout $layout_paragraphs_layout
   * @return void
   */
  public function save(Request $request, LayoutParagraphsLayout $layout_paragraphs_layout) {
    if ($delete_uuids = Json::decode($request->request->get("deleteComponents"))) {
      foreach ($delete_uuids as $uuid) {
        $layout_paragraphs_layout->deleteComponent($uuid);
      }
    }
    if ($ordered_components = Json::decode($request->request->get("layoutParagraphsState"))) {
      $layout_paragraphs_layout->reorderComponents($ordered_components);
    }
    $entity = $layout_paragraphs_layout->getEntity();
    $entity->save();
    $layout_paragraphs_layout->setEntity($entity);
    $this->editorTempstore->set($layout_paragraphs_layout);

    $response = new AjaxResponse();
    $response->addCommand(new LayoutParagraphsEditorInvokeHookCommand(
      'save',
      $layout_paragraphs_layout->id()
    ));
    return $response;
  }

  /**
   * Cancel out of the editor.
   */
  public function cancel(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    EntityInterface $entity,
    string $field_name,
    string $view_mode) {

    $this->editorTempstore->delete($layout_paragraphs_layout);
    $rendered_field = $entity->{$field_name}->view($view_mode);

    $response = new AjaxResponse();
    $selector = '.js-' . $entity->uuid() . '--' . $field_name;
    $response->addCommand(new ReplaceCommand($selector, $rendered_field));
    return $response;
  }

  /**
   * Insert a sibling paragraph into the field.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param Drupal\layout_paragraphs_editor\LayoutParagraphsEditorInterface $layout_paragraphs_editor
   *   The layout paragraphs editor from the tempstore.
   * @param string $position
   *   Whether to insert the sibling "before" or "after".
   * @param Drupal\paragraphs\ParagraphInterface $paragraph
   *   The existing paragraph we are adding a new item adjacent to.
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type for the new content being added.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Returns the edit form render array.
   */
  public function insertSibling(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    string $sibling_uuid,
    string $proximity,
    ParagraphsTypeInterface $paragraph_type) {

    $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    $label = $this->t('Create @type', ['@type' => $paragraph_type->label()]);

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);

    switch ($proximity) {
      case "before":
        $layout_paragraphs_layout->insertBeforeComponent($sibling_uuid, $paragraph);
        break;

      case "after":
        $layout_paragraphs_layout->insertAfterComponent($sibling_uuid, $paragraph);
        break;
    }
    $context = [
      'insert' => TRUE,
      'sibling_uuid' => $sibling_uuid,
      'proximity' => $proximity,
    ];

    $response = new AjaxResponse();
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs_editor\Form\LayoutParagraphsEditorEditForm',
      $layout_paragraphs_layout,
      $paragraph,
      $context
    );
    $this->addFormResponse($response, $label, $form);
    return $response;

  }

  /**
   * Insert a sibling paragraph into the field.
   *
   * @param Symfony\Component\HttpFoundation\Request $request
   *   The request object.
   * @param Drupal\layout_paragraphs_editor\LayoutParagraphsEditorInterface $layout_paragraphs_editor
   *   The layout paragraphs editor from the tempstore.
   * @param string $position
   *   Whether to insert the sibling "before" or "after".
   * @param Drupal\paragraphs\ParagraphInterface $paragraph
   *   The existing paragraph we are adding a new item adjacent to.
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type for the new content being added.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Returns the edit form render array.
   */
  public function insertIntoRegion(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    string $parent_uuid,
    string $region,
    ParagraphsTypeInterface $paragraph_type) {

    $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    $label = $this->t('Edit @type', ['@type' => $paragraph_type->label()]);

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);

    $layout_paragraphs_layout->insertIntoRegion($parent_uuid, $region, $paragraph);
    $context = [
      'insert' => TRUE,
      'parent_uuid' => $parent_uuid,
      'region' => $region,
    ];

    $response = new AjaxResponse();
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs_editor\Form\LayoutParagraphsEditorEditForm',
      $layout_paragraphs_layout,
      $paragraph,
      $context
    );
    $this->addFormResponse($response, $label, $form);
    return $response;
  }

  /**
   * Insert a sibling paragraph into the field.
   *
   * @param Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs editor from the tempstore.
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type for the new content being added.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Returns the edit form render array.
   */
  public function insertComponent(
    LayoutParagraphsLayout $layout_paragraphs_layout,
    ParagraphsTypeInterface $paragraph_type) {

    $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');
    $label = $this->t('Edit @type', ['@type' => $paragraph_type->label()]);

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')
      ->create([$bundle_key => $paragraph_type->id()]);
    $layout_paragraphs_layout->appendComponent($paragraph);

    $context = [
      'insert' => TRUE,
    ];

    $response = new AjaxResponse();
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs_editor\Form\LayoutParagraphsEditorEditForm',
      $layout_paragraphs_layout,
      $paragraph,
      $context
    );
    $this->addFormResponse($response, $label, $form);
    return $response;

  }

  /**
   * Insert a sibling paragraph into the field.
   *
   * @param Drupal\layout_paragraphs\LayoutParagraphsLayout $layout_paragraphs_layout
   *   The layout paragraphs editor from the tempstore.
   * @param Drupal\paragraphs\ParagraphsTypeInterface $paragraph_type
   *   The paragraph type for the new content being added.
   *
   * @return Drupal\Core\Ajax\AjaxResponse
   *   Returns the edit form render array.
   */
  public function testForm(LayoutParagraphsLayout $layout_paragraphs_layout) {

    $entity_type = $this->entityTypeManager()->getDefinition('paragraph');
    $bundle_key = $entity_type->getKey('bundle');

    /** @var \Drupal\paragraphs\ParagraphInterface $paragraph_entity */
    $paragraph = $this->entityTypeManager()->getStorage('paragraph')
      ->create([$bundle_key => 'section']);

    $context = [
      'insert' => TRUE,
    ];
    $form = $this->formBuilder()->getForm(
      '\Drupal\layout_paragraphs_editor\Form\LayoutParagraphsEditorEditForm',
      $layout_paragraphs_layout,
      $paragraph,
      $context
    );
    return $form;
  }

  /**
   * Adds the paragraph form to an ajax response.
   *
   * @param Drupal\Core\Ajax\AjaxResponse $response
   *   The ajax response object.
   * @param string $title
   *   The form title.
   * @param array $form
   *   The form array.
   */
  protected function addFormResponse(AjaxResponse &$response, string $title, array $form) {
    $selector = '#' . $form['#dialog_id'];
    $response->addCommand(new OpenDialogCommand($selector, $title, $form, $this->modalSettings));
    //$response->addCommand(new OpenModalDialogCommand($title, $form, $this->modalSettings));
  }

}
