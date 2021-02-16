<?php

namespace Drupal\layout_paragraphs_editor\Ajax;

use Drupal\Core\Ajax\CommandInterface;

/**
 * Class ExampleCommand.
 */
class LayoutParagraphsEditorInvokeHookCommand implements CommandInterface {

  /**
   * The hook to invoke.
   *
   * @var string
   */
  protected $hook;

  /**
   * The params to pass to the hook.
   *
   * @var mixed
   */
  protected $params;

  /**
   * Class constructor.
   *
   * @param string $hook
   *   The hook to invoke.
   * @param mixed $params
   *   The paramters to pass to the hook.
   */
  public function __construct($hook, $params) {
    $this->hook = $hook;
    $this->params = $params;
  }

  /**
   * {@inheritDoc}
   */
  public function render() {
    return [
      'command' => 'layoutParagraphsEditorInvokeHook',
      'hook' => $this->hook,
      'params' => $this->params,
    ];
  }

}
