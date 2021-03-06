<?php

namespace Drupal\search_api\Form;

use Drupal\Component\Utility\Html;
use Drupal\Core\Entity\EntityForm;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\PluginFormInterface;
use Drupal\Core\Form\SubformState;
use Drupal\search_api\Datasource\DatasourcePluginManager;
use Drupal\search_api\IndexInterface;
use Drupal\search_api\SearchApiException;
use Drupal\search_api\Tracker\TrackerPluginManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a form for the Index entity.
 */
class IndexForm extends EntityForm {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The datasource plugin manager.
   *
   * @var \Drupal\search_api\Datasource\DatasourcePluginManager
   */
  protected $datasourcePluginManager;

  /**
   * The tracker plugin manager.
   *
   * @var \Drupal\search_api\Tracker\TrackerPluginManager
   */
  protected $trackerPluginManager;

  /**
   * The index before the changes.
   *
   * @var \Drupal\search_api\IndexInterface
   */
  protected $originalEntity;

  /**
   * Constructs an IndexForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\search_api\Datasource\DatasourcePluginManager $datasource_plugin_manager
   *   The search datasource plugin manager.
   * @param \Drupal\search_api\Tracker\TrackerPluginManager $tracker_plugin_manager
   *   The Search API tracker plugin manager.
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, DatasourcePluginManager $datasource_plugin_manager, TrackerPluginManager $tracker_plugin_manager) {
    $this->entityTypeManager = $entity_type_manager;
    $this->datasourcePluginManager = $datasource_plugin_manager;
    $this->trackerPluginManager = $tracker_plugin_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    /** @var \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager */
    $entity_type_manager = $container->get('entity_type.manager');
    /** @var \Drupal\search_api\Datasource\DatasourcePluginManager $datasource_plugin_manager */
    $datasource_plugin_manager = $container->get('plugin.manager.search_api.datasource');
    /** @var \Drupal\search_api\Tracker\TrackerPluginManager $tracker_plugin_manager */
    $tracker_plugin_manager = $container->get('plugin.manager.search_api.tracker');
    return new static($entity_type_manager, $datasource_plugin_manager, $tracker_plugin_manager);
  }

  /**
   * Returns the entity type manager.
   *
   * @return \Drupal\Core\Entity\EntityTypeManagerInterface
   *   The entity type manager.
   */
  protected function getEntityTypeManager() {
    return $this->entityTypeManager ?: \Drupal::service('entity_type.manager');
  }

  /**
   * Returns the datasource plugin manager.
   *
   * @return \Drupal\search_api\Datasource\DatasourcePluginManager
   *   The datasource plugin manager.
   */
  protected function getDatasourcePluginManager() {
    return $this->datasourcePluginManager ?: \Drupal::service('plugin.manager.search_api.datasource');
  }

  /**
   * Returns the tracker plugin manager.
   *
   * @return \Drupal\search_api\Tracker\TrackerPluginManager
   *   The tracker plugin manager.
   */
  protected function getTrackerPluginManager() {
    return $this->trackerPluginManager ?: \Drupal::service('plugin.manager.search_api.tracker');
  }

  /**
   * Returns the index storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The index storage controller.
   */
  protected function getIndexStorage() {
    return $this->getEntityTypeManager()->getStorage('search_api_index');
  }

  /**
   * Returns the server storage controller.
   *
   * @return \Drupal\Core\Entity\EntityStorageInterface
   *   The server storage controller.
   */
  protected function getServerStorage() {
    return $this->getEntityTypeManager()->getStorage('search_api_server');
  }

  /**
   * Retrieves all available servers as an options list.
   *
   * @return string[]
   *   An associative array mapping server IDs to their labels.
   */
  protected function getServerOptions() {
    $options = array();
    /** @var \Drupal\search_api\ServerInterface $server */
    foreach ($this->getServerStorage()->loadMultiple() as $server_id => $server) {
      // @todo Special formatting for disabled servers.
      $options[$server_id] = Html::escape($server->label());
    }
    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function form(array $form, FormStateInterface $form_state) {
    // If the form is being rebuilt, rebuild the entity with the current form
    // values.
    if ($form_state->isRebuilding()) {
      // When the form is being built for an AJAX response the ID is not present
      // in $form_state. To ensure our entity is always valid, we're adding the
      // ID back.
      if (!$this->entity->isNew()) {
        $form_state->setValue('id', $this->entity->id());
      }
      $this->entity = $this->buildEntity($form, $form_state);
    }

    $form = parent::form($form, $form_state);

    /** @var \Drupal\search_api\IndexInterface $index */
    $index = $this->getEntity();
    if ($index->isNew()) {
      $form['#title'] = $this->t('Add search index');
    }
    else {
      $arguments = array('%label' => $index->label());
      $form['#title'] = $this->t('Edit search index %label', $arguments);
    }

    $this->buildEntityForm($form, $form_state, $index);

    return $form;
  }

  /**
   * Builds the form for the basic index properties.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index that is being created or edited.
   */
  public function buildEntityForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    $form['#tree'] = TRUE;
    $form['name'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Index name'),
      '#description' => $this->t('Enter the displayed name for the index.'),
      '#default_value' => $index->label(),
      '#required' => TRUE,
    );
    $form['id'] = array(
      '#type' => 'machine_name',
      '#default_value' => $index->isNew() ? NULL : $index->id(),
      '#maxlength' => 50,
      '#required' => TRUE,
      '#machine_name' => array(
        'exists' => array($this->getIndexStorage(), 'load'),
        'source' => array('name'),
      ),
      '#disabled' => !$index->isNew(),
    );

    $form['#attached']['library'][] = 'search_api/drupal.search_api.admin_css';

    $form['datasources'] = array(
      '#type' => 'checkboxes',
      '#title' => $this->t('Data sources'),
      '#description' => $this->t('Select one or more data sources of items that will be stored in this index.'),
      '#default_value' => $index->getDatasourceIds(),
      '#multiple' => TRUE,
      '#required' => TRUE,
      '#attributes' => array('class' => array('search-api-checkboxes-list')),
      '#ajax' => array(
        'trigger_as' => array('name' => 'datasources_configure'),
        'callback' => '::buildAjaxDatasourceConfigForm',
        'wrapper' => 'search-api-datasources-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
    $datasource_options = array();
    foreach ($index->createPlugins('datasource') as $datasource_id => $datasource) {
      $datasource_options[$datasource_id] = $datasource->label();
      $form['datasources'][$datasource_id]['#description'] = $datasource->getDescription();
    }
    asort($datasource_options, SORT_NATURAL | SORT_FLAG_CASE);
    $form['datasources']['#options'] = $datasource_options;

    $form['datasource_configs'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-datasources-config-form',
      ),
      '#tree' => TRUE,
    );

    $form['datasource_configure_button'] = array(
      '#type' => 'submit',
      '#name' => 'datasources_configure',
      '#value' => $this->t('Configure'),
      '#limit_validation_errors' => array(array('datasources')),
      '#submit' => array('::submitAjaxDatasourceConfigForm'),
      '#ajax' => array(
        'callback' => '::buildAjaxDatasourceConfigForm',
        'wrapper' => 'search-api-datasources-config-form',
      ),
      '#attributes' => array('class' => array('js-hide')),
    );

    $this->buildDatasourcesConfigForm($form, $form_state, $index);

    $form['tracker'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Tracker'),
      '#description' => $this->t('Select the type of tracker which should be used for keeping track of item changes.'),
      '#default_value' => $index->getTrackerId(),
      '#required' => TRUE,
      '#ajax' => array(
        'trigger_as' => array('name' => 'tracker_configure'),
        'callback' => '::buildAjaxTrackerConfigForm',
        'wrapper' => 'search-api-tracker-config-form',
        'method' => 'replace',
        'effect' => 'fade',
      ),
    );
    $tracker_options = array();
    foreach ($index->createPlugins('tracker') as $tracker_id => $tracker) {
      $tracker_options[$tracker_id] = $tracker->label();
      $form['tracker'][$tracker_id]['#description'] = $tracker->getDescription();
    }
    asort($tracker_options, SORT_NATURAL | SORT_FLAG_CASE);
    $form['tracker']['#options'] = $tracker_options;
    $form['tracker']['#access'] = !$index->hasValidTracker() || count($tracker_options) > 1;

    $form['tracker_config'] = array(
      '#type' => 'container',
      '#attributes' => array(
        'id' => 'search-api-tracker-config-form',
      ),
      '#tree' => TRUE,
    );

    $form['tracker_configure_button'] = array(
      '#type' => 'submit',
      '#name' => 'tracker_configure',
      '#value' => $this->t('Configure'),
      '#limit_validation_errors' => array(array('tracker')),
      '#submit' => array('::submitAjaxTrackerConfigForm'),
      '#ajax' => array(
        'callback' => '::buildAjaxTrackerConfigForm',
        'wrapper' => 'search-api-tracker-config-form',
      ),
      '#attributes' => array('class' => array('js-hide')),
      '#access' => count($tracker_options) > 1,
    );

    $this->buildTrackerConfigForm($form, $form_state, $index);

    $form['server'] = array(
      '#type' => 'radios',
      '#title' => $this->t('Server'),
      '#description' => $this->t('Select the server this index should use. Indexes cannot be enabled without a connection to a valid, enabled server.'),
      '#options' => array('' => '<em>' . $this->t('- No server -') . '</em>') + $this->getServerOptions(),
      '#default_value' => $index->hasValidServer() ? $index->getServerId() : '',
    );

    $form['status'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Enabled'),
      '#description' => $this->t('Only enabled indexes can be used for indexing and searching. This setting will only take effect if the selected server is also enabled.'),
      '#default_value' => $index->status(),
      // Can't enable an index lying on a disabled server or no server at all.
      '#disabled' => !$index->status() && (!$index->hasValidServer() || !$index->getServerInstance()->status()),
      // @todo This doesn't seem to work and should also hide for disabled
      //   servers. If that works, we can probably remove the last sentence of
      //   the description.
      '#states' => array(
        'invisible' => array(
          ':input[name="server"]' => array('value' => ''),
        ),
      ),
    );

    $form['description'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Description'),
      '#description' => $this->t('Enter a description for the index.'),
      '#default_value' => $index->getDescription(),
    );

    $form['options'] = array(
      '#tree' => TRUE,
      '#type' => 'details',
      '#title' => $this->t('Index options'),
      '#collapsed' => TRUE,
    );

    // We display the "read-only" flag along with the other options, even though
    // it is a property directly on the index object. We use "#parents" to move
    // it to the correct place in the form values.
    $form['options']['read_only'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Read only'),
      '#description' => $this->t('Do not write to this index or track the status of items in this index.'),
      '#default_value' => $index->isReadOnly(),
      '#parents' => array('read_only'),
    );
    $form['options']['index_directly'] = array(
      '#type' => 'checkbox',
      '#title' => $this->t('Index items immediately'),
      '#description' => $this->t('Immediately index new or updated items instead of waiting for the next cron run. This might have serious performance drawbacks and is generally not advised for larger sites.'),
      '#default_value' => $index->getOption('index_directly'),
    );
    $form['options']['cron_limit'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Cron batch size'),
      '#description' => $this->t('Set how many items will be indexed at once when indexing items during a cron run. "0" means that no items will be indexed by cron for this index, "-1" means that cron should index all items at once.'),
      '#default_value' => $index->getOption('cron_limit'),
      '#size' => 4,
    );
  }

  /**
   * Builds the configuration forms for all selected datasources.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index being created or edited.
   */
  public function buildDatasourcesConfigForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    $selected_datasources = $form_state->getValue('datasources');
    if ($selected_datasources === NULL) {
      // Initial form build, use the saved datasources (or none for new
      // indexes).
      $datasources = $index->getDatasources();
    }
    else {
      // The form is being rebuilt – use the datasources selected by the user
      // instead of the ones saved in the config.
      $datasources = $index->createPlugins('datasource', $selected_datasources);
    }
    $form_state->set('datasources', array_keys($datasources));

    $show_message = FALSE;
    foreach ($datasources as $datasource_id => $datasource) {
      if ($datasource instanceof PluginFormInterface) {
        // Get the "sub-form state" and appropriate form part to send to
        // buildConfigurationForm().
        $datasource_form = array();
        if (!empty($form['datasource_configs'][$datasource_id])) {
          $datasource_form = $form['datasource_configs'][$datasource_id];
        }
        $datasource_form_state = SubformState::createForSubform($datasource_form, $form, $form_state);
        $form['datasource_configs'][$datasource_id] = $datasource->buildConfigurationForm($datasource_form, $datasource_form_state);

        $show_message = TRUE;
        $form['datasource_configs'][$datasource_id]['#type'] = 'details';
        $form['datasource_configs'][$datasource_id]['#title'] = $this->t('Configure the %datasource datasource', array('%datasource' => $datasource->label()));
        $form['datasource_configs'][$datasource_id]['#open'] = $index->isNew();
      }
    }

    // If the user changed the datasources and there is at least one datasource
    // config form, show a message telling the user to configure it.
    if ($selected_datasources && $show_message) {
      drupal_set_message($this->t('Please configure the used datasources.'), 'warning');
    }
  }

  /**
   * Builds the tracker configuration form.
   *
   * @param \Drupal\search_api\IndexInterface $index
   *   The index being created or edited.
   */
  public function buildTrackerConfigForm(array &$form, FormStateInterface $form_state, IndexInterface $index) {
    $selected_tracker = $form_state->getValue('tracker');
    if ($selected_tracker === NULL || $selected_tracker == $index->getTrackerId()) {
      // Initial form build, use the saved tracker (or none for new indexes).
      if ($index->hasValidTracker()) {
        $tracker = $index->getTrackerInstance();
      }
      // Only notify the user of a missing tracker plugin if we're editing an
      // existing index.
      elseif (!$index->isNew()) {
        drupal_set_message($this->t('The tracker plugin is missing or invalid.'), 'error');
      }
    }
    else {
      // Probably an AJAX rebuild of the form – use the tracker selected by
      // the user.
      $tracker = $this->getTrackerPluginManager()->createInstance($selected_tracker, array());
    }

    if (empty($tracker)) {
      return;
    }

    $form_state->set('tracker', $tracker->getPluginId());

    if ($tracker instanceof PluginFormInterface) {
      // Get the "sub-form state" and appropriate form part to send to
      // buildConfigurationForm().
      $tracker_form = !empty($form['tracker_config']) ? $form['tracker_config'] : array();
      $tracker_form_state = SubformState::createForSubform($tracker_form, $form, $form_state);
      $form['tracker_config'] = $tracker->buildConfigurationForm($tracker_form, $tracker_form_state);

      $form['tracker_config']['#type'] = 'details';
      $form['tracker_config']['#title'] = $this->t('Configure the %plugin tracker', array('%plugin' => $tracker->label()));
      $form['tracker_config']['#description'] = Html::escape($tracker->getDescription());
      $form['tracker_config']['#open'] = $index->isNew();

      // If the user changed the tracker and the new one has a config form, show
      // a message telling the user to configure it.
      if ($selected_tracker && $selected_tracker != $tracker->getPluginId()) {
        drupal_set_message($this->t('Please configure the used tracker.'), 'warning');
      }
    }
  }

  /**
   * Form submission handler for buildEntityForm().
   *
   * Takes care of changes in the selected datasources.
   */
  public function submitAjaxDatasourceConfigForm($form, FormStateInterface $form_state) {
    $form_state->setValue('id', NULL);
    $form_state->setRebuild();
  }

  /**
   * Handles changes to the selected datasources.
   */
  public function buildAjaxDatasourceConfigForm(array $form, FormStateInterface $form_state) {
    return $form['datasource_configs'];
  }

  /**
   * Form submission handler for buildEntityForm().
   *
   * Takes care of changes in the selected tracker plugin.
   */
  public function submitAjaxTrackerConfigForm(array $form, FormStateInterface $form_state) {
    $form_state->setValue('id', NULL);
    $form_state->setRebuild();
  }

  /**
   * Handles switching the selected tracker plugin.
   */
  public function buildAjaxTrackerConfigForm(array $form, FormStateInterface $form_state) {
    return $form['tracker_config'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);

    /** @var $index \Drupal\search_api\IndexInterface */
    $index = $this->getEntity();

    $storage = $this->entityTypeManager->getStorage('search_api_index');
    if (!$index->isNew()) {
      $this->originalEntity = $storage->loadUnchanged($index->id());
    }
    if (empty($this->originalEntity)) {
      $this->originalEntity = $storage->create(array('status' => FALSE));
    }

    // Store the array of datasource plugin IDs with integer keys.
    $datasource_ids = array_values(array_filter($form_state->getValue('datasources', array())));
    $form_state->setValue('datasources', $datasource_ids);

    // Call validateConfigurationForm() for each enabled datasource with a form.
    $datasources = $this->originalEntity->createPlugins('datasource', $datasource_ids);
    $previous_datasources = $form_state->get('datasources');
    foreach ($datasources as $datasource_id => $datasource) {
      if ($datasource instanceof PluginFormInterface) {
        if (!in_array($datasource_id, $previous_datasources)) {
          $form_state->setRebuild();
          continue;
        }
        $datasource_form = &$form['datasource_configs'][$datasource_id];
        $datasource_form_state = SubformState::createForSubform($datasource_form, $form, $form_state);
        $datasource->validateConfigurationForm($datasource_form, $datasource_form_state);
      }
    }

    // Call validateConfigurationForm() for the (possibly new) tracker, if it
    // has not changed and if it has a form.
    $tracker_id = $form_state->getValue('tracker', NULL);
    if ($tracker_id == $form_state->get('tracker')) {
      $tracker = $this->originalEntity->createPlugin('tracker', $tracker_id);
      if ($tracker instanceof PluginFormInterface) {
        $tracker_form_state = SubformState::createForSubform($form['tracker_config'], $form, $form_state);
        $tracker->validateConfigurationForm($form['tracker_config'], $tracker_form_state);
      }
    }
    else {
      $tracker = $this->trackerPluginManager->createInstance($tracker_id, array('#index' => $this->originalEntity));
      if ($tracker instanceof PluginFormInterface) {
        $form_state->setRebuild();
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function actions(array $form, FormStateInterface $form_state) {
    $actions = parent::actions($form, $form_state);

    if ($this->getEntity()->isNew()) {
      $actions['save_edit'] = array(
        '#type' => 'submit',
        '#value' => $this->t('Save and add fields'),
        '#submit' => $actions['submit']['#submit'],
        '#button_type' => 'primary',
        // Work around for submit callbacks after save() not being called due to
        // batch operations.
        '#redirect_to_url' => 'add-fields',
      );
    }

    return $actions;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    /** @var $index \Drupal\search_api\IndexInterface */
    $index = $this->getEntity();
    $index->setOptions($form_state->getValue('options', array()) + $this->originalEntity->getOptions());

    $datasource_ids = $form_state->getValue('datasources', array());
    $datasources = $this->originalEntity->createPlugins('datasource', $datasource_ids);
    foreach ($datasources as $datasource_id => $datasource) {
      if ($datasource instanceof PluginFormInterface) {
        $datasource_form_state = SubformState::createForSubform($form['datasource_configs'][$datasource_id], $form, $form_state);
        $datasource->submitConfigurationForm($form['datasource_configs'][$datasource_id], $datasource_form_state);
      }
    }
    $index->setDatasources($datasources);

    // Call submitConfigurationForm() for the (possibly new) tracker, if it
    // has not changed and if it has a form.
    $tracker_id = $form_state->getValue('tracker', NULL);
    $tracker = $this->originalEntity->createPlugin('tracker', $tracker_id);
    if ($tracker_id == $form_state->get('tracker')) {
      if ($tracker instanceof PluginFormInterface) {
        $tracker_form_state = SubformState::createForSubform($form['tracker_config'], $form, $form_state);
        $tracker->submitConfigurationForm($form['tracker_config'], $tracker_form_state);
      }
    }
    /** @var \Drupal\search_api\Tracker\TrackerInterface $tracker */
    $index->setTracker($tracker);
  }

  /**
   * {@inheritdoc}
   */
  public function save(array $form, FormStateInterface $form_state) {
    // @todo Redirect to a confirm form if changing server or tracker, since
    //   that isn't such a light operation (equaling a "clear", basically).
    // Only save the index if the form doesn't need to be rebuilt.
    if (!$form_state->isRebuilding()) {
      try {
        /** @var \Drupal\search_api\IndexInterface $index */
        $index = $this->getEntity();
        $index->save();
        drupal_set_message($this->t('The index was successfully saved.'));
        $button = $form_state->getTriggeringElement();
        if (!empty($button['#redirect_to_url'])) {
          $form_state->setRedirectUrl($index->toUrl($button['#redirect_to_url']));
        }
        else {
          $form_state->setRedirect('entity.search_api_index.canonical', array('search_api_index' => $index->id()));
        }
      }
      catch (SearchApiException $ex) {
        $form_state->setRebuild();
        watchdog_exception('search_api', $ex);
        drupal_set_message($this->t('The index could not be saved.'), 'error');
      }
    }
  }

}
