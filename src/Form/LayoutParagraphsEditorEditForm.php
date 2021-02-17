<?php

namespace Drupal\layout_paragraphs_editor\Form;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\Entity\EntityFormDisplay;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\AfterCommand;
use Drupal\Core\Ajax\BeforeCommand;
use Drupal\Core\Ajax\AppendCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Ajax\InvokeCommand;
use Drupal\Core\Ajax\AjaxFormHelperTrait;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\layout_paragraphs\LayoutParagraphsLayout;
use Drupal\field_group\FormatterHelper;
use Drupal\Core\Form\SubformState;
use Drupal\layout_paragraphs_editor\EditorTempstoreRepository;
use Drupal\paragraphs\Entity\Paragraph;
use Drupal\layout_paragraphs_editor\Ajax\LayoutParagraphsEditorInvokeHookCommand;

/**
 * Class LayoutParagraphsEditorFormBase.
 */
class LayoutParagraphsEditorEditForm extends FormBase {

  use AjaxFormHelperTrait;

  /**
   * The tempstore service.
   *
   * @var \Drupal\layout_paragraphs_editor\EditorTempstoreRepository
   */
  protected $tempstore;

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The layout object.
   *
   * @var \Drupal\layout_paragraphs\LayoutParagraphsLayout
   */
  protected $layoutParagraphsLayout;

  /**
   * Context for the insert or update operation.
   *
   * @var array
   */
  protected $context;

  /**
   * Constructs a LayoutParagraphsEditorEditForm instance.
   *
   * @param Drupal\layout_paragraphs_editor\EditorTempstoreRepository $tempstore
   *   The layout paragraphs editor tempstore service.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(
    EditorTempstoreRepository $tempstore,
    EntityTypeManagerInterface $entity_type_manager) {
    $this->tempstore = $tempstore;
    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritDoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('layout_paragraphs_editor.tempstore_repository'),
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritDoc}
   */
  public function getFormId() {
    return 'layout_paragraphs_editor_edit_form';
  }

  /**
   * Builds a paragraph edit form.
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    LayoutParagraphsLayout $layout_paragraphs_layout = NULL,
    Paragraph $paragraph = NULL,
    array $context = []) {

    $display = EntityFormDisplay::collectRenderDisplay($paragraph, 'default');
    $this->layoutParagraphsLayout = $layout_paragraphs_layout;
    $this->context = $context;

    $form = [
      '#paragraph' => $paragraph,
      '#display' => $display,
      '#tree' => TRUE,
      'entity_form' => [
        '#weight' => 10,
      ],
      'actions' => [
        '#weight' => 20,
        '#type' => 'actions',
        'submit' => [
          '#type' => 'submit',
          '#value' => $this->t('Submit'),
          '#ajax' => [
            'callback' => [$this, 'ajaxSubmit'],
            'progress' => 'none',
          ],
        ],
      ],
    ];

    $paragraphs_type = $paragraph->getParagraphType();
    if ($paragraphs_type->hasEnabledBehaviorPlugin('layout_paragraphs')) {
      $form['layout_paragraphs'] = [];
      $layout_paragraphs_plugin = $paragraphs_type->getEnabledBehaviorPlugins()['layout_paragraphs'];
      $subform_state = SubformState::createForSubform($form['layout_paragraphs'], $form, $form_state);
      if ($layout_paragraphs_plugin_form = $layout_paragraphs_plugin->buildBehaviorForm($paragraph, $form['layout_paragraphs'], $subform_state)) {
        $form['layout_paragraphs'] = $layout_paragraphs_plugin_form;
      }
    }

    // Support for Field Group module based on Paragraphs module.
    // @todo Remove as part of https://www.drupal.org/node/2640056
    if (\Drupal::moduleHandler()->moduleExists('field_group')) {
      $context = [
        'entity_type' => $paragraph->getEntityTypeId(),
        'bundle' => $paragraph->bundle(),
        'entity' => $paragraph,
        'context' => 'form',
        'display_context' => 'form',
        'mode' => $display->getMode(),
      ];
      // phpcs:ignore
      field_group_attach_groups($form['entity_form'], $context);
      if (method_exists(FormatterHelper::class, 'formProcess')) {
        $form['entity_form']['#process'][] = [FormatterHelper::class, 'formProcess'];
      }
      elseif (function_exists('field_group_form_pre_render')) {
        $form['entity_form']['#pre_render'][] = 'field_group_form_pre_render';
      }
      elseif (function_exists('field_group_form_process')) {
        $form['entity_form']['#process'][] = 'field_group_form_process';
      }
    }

    $display->buildForm($paragraph, $form['entity_form'], $form_state);
    return $form;
  }

  /**
   * {@inheritDoc}
   */
  public function successfulAjaxSubmit(array $form, FormStateInterface $form_state) {

    $paragraph = $form['#paragraph'];
    $view_builder = $this->entityTypeManager->getViewBuilder('paragraph');
    $rendered_item = $view_builder->view($paragraph);

    if ($section = $this->layoutParagraphsLayout->getLayoutSection($paragraph)) {
      $rendered_item['regions'] = \Drupal::service('layout_paragraphs')->buildLayoutSection($section);
    }

    $uuid = $paragraph->uuid();
    $response = new AjaxResponse();

    // Insert a new component.
    if ($this->context['insert']) {
      // Insert before or after an existing component.
      if (!empty($this->context['sibling_uuid']) && !empty($this->context['proximity'])) {
        $sibling_uuid = $this->context['sibling_uuid'];
        $proximity = $this->context['proximity'];
        $command = $proximity == 'before' ?
          new BeforeCommand("[data-uuid={$sibling_uuid}]", $rendered_item) :
            new AfterCommand("[data-uuid={$sibling_uuid}]", $rendered_item);
      }
      elseif (!empty($this->context['parent_uuid']) && !empty($this->context['region'])) {
        $parent_uuid = $this->context['parent_uuid'];
        $region = $this->context['region'];
        $command = new AppendCommand("[data-region-uuid='{$parent_uuid}-{$region}']", $rendered_item);
      }
      else {
        $lp_editor_id = $this->layoutParagraphsLayout->id();
        $command = new AppendCommand("[data-lp-editor-id='{$lp_editor_id}']", $rendered_item);
      }
      $response->addCommand($command);
      $response->addCommand(new LayoutParagraphsEditorInvokeHookCommand(
        'insertComponent',
        [
          'layoutId' => $this->layoutParagraphsLayout->id(),
          'componentUuid' => $uuid,
        ]
      ));
    }
    // Update an existing component.
    else {
      $response->addCommand(new ReplaceCommand("[data-uuid={$uuid}]", $rendered_item));
      $response->addCommand(new LayoutParagraphsEditorInvokeHookCommand(
        'updateComponent',
        [
          'layoutId' => $this->layoutParagraphsLayout->id(),
          'componentUuid' => $uuid,
        ]
      ));
    }

    $response->addCommand(new InvokeCommand("[data-uuid={$uuid}]", "focus"));
    $response->addCommand(new CloseModalDialogCommand());

    return $response;
  }

  /**
   * {@inheritDoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {

  }

  /**
   * {@inheritDoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    /** @var \Drupal\paragraphs\Entity\Paragraph $paragraph */
    $paragraph = $form['#paragraph'];
    /** @var Drupal\Core\Entity\Entity\EntityFormDisplay $display */
    $display = $form['#display'];

    $paragraphs_type = $paragraph->getParagraphType();
    if ($paragraphs_type->hasEnabledBehaviorPlugin('layout_paragraphs')) {
      $layout_paragraphs_plugin = $paragraphs_type->getEnabledBehaviorPlugins()['layout_paragraphs'];
      $subform_state = SubformState::createForSubform($form['layout_paragraphs'], $form, $form_state);
      $layout_paragraphs_plugin->submitBehaviorForm($paragraph, $form['layout_paragraphs'], $subform_state);
    }

    $paragraph->setNeedsSave(TRUE);
    $display->extractFormValues($paragraph, $form['entity_form'], $form_state);

    $this->layoutParagraphsLayout->setComponent($paragraph);
    $this->tempstore->set($this->layoutParagraphsLayout);
  }

}
