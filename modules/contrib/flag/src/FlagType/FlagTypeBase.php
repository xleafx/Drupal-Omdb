<?php

namespace Drupal\flag\FlagType;

use Drupal\Component\Utility\NestedArray;
use Drupal\Core\Access\AccessResult;
use Drupal\flag\FlagType\FlagTypePluginInterface;
use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\flag\FlagInterface;
use Drupal\Core\Entity\EntityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;

/**
 * Provides a base class for flag type plugins.
 */
abstract class FlagTypeBase extends PluginBase implements FlagTypePluginInterface {

  /**
   * The module handler.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * {@inheritdoc}
   */
  public function __construct(array $configuration, $plugin_id, array $plugin_definition, ModuleHandlerInterface $module_handler) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->moduleHandler = $module_handler;
    $this->configuration += $this->defaultConfiguration();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function calculateDependencies() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguration() {
    return $this->configuration;
  }

  /**
   * {@inheritdoc}
   */
  public function setConfiguration(array $configuration) {
    $this->configuration = NestedArray::mergeDeep($this->defaultConfiguration(), $configuration);
  }

  /**
   * Provides a form for this action link plugin settings.
   *
   * The form provided by this method is displayed by the FlagAddForm when
   * creating or editing the Flag. Derived classes should override this.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state.
   *
   * @return array
   *   The form array
   * @see \Drupal\flag\Form\FlagAddForm
   */
  public function buildConfigurationForm(array $form, FormStateInterface $form_state) {
    return $form;
  }

  /**
   * Handles the form submit for this action link plugin.
   *
   * Derived classes will want to override this.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function submitConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Override this.
  }

  /**
   * Handles the validation for the action link plugin settings form.
   *
   * @param array $form
   *   The form array.
   * @param FormStateInterface $form_state
   *   The form state.
   */
  public function validateConfigurationForm(array &$form, FormStateInterface $form_state) {
    // Override this.
  }

  /**
   * Defines options for extra permissions.
   *
   * @return array
   *  An array of options suitable for FormAPI.
   */
  protected function getExtraPermissionsOptions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function hasExtraPermission($option) {
    return in_array($option, $this->configuration['extra_permissions']);
  }

  /**
   * {@inheritdoc}
   */
  public function actionPermissions(FlagInterface $flag) {
    return [
      'flag ' . $flag->id() => [
        'title' => t('Flag %flag_title', [
          '%flag_title' => $flag->label(),
        ]),
      ],
      'unflag ' . $flag->id() => [
        'title' => t('Unflag %flag_title', [
          '%flag_title' => $flag->label(),
        ]),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function actionAccess($action, FlagInterface $flag, AccountInterface $account, EntityInterface $flaggable = NULL) {
    // Collect access results from objects.
    $results = $this->moduleHandler->invokeAll('flag_action_access', [
      $action,
      $flag,
      $account,
      $flaggable,
    ]);

    // Add default access check.
    $results[] = AccessResult::allowedIfHasPermission($account, $action . ' ' . $flag->id());

    /** @var \Drupal\Core\Access\AccessResultInterface $return */
    $return = array_shift($results);
    foreach ($results as $next) {
      $return = $return->orIf($next);
    }

    return $return;
  }

}
