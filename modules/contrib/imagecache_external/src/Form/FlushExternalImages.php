<?php

/**
 * @file
 * Contains \Drupal\imagecache_external\Form\FlushExternalImages.
 */

namespace Drupal\imagecache_external\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Defines a confirmation form for deleting mymodule data.
 */
class FlushExternalImages extends ConfirmFormBase {

  /**
   * The ID of the item to delete.
   *
   * @var string
   */
  protected $id;

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'imagecache_external_flush_external_images_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion() {
    return t('Flush all external images?');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl() {
    return new Url('imagecache_external.admin_settings');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription() {
    return t('Are you sure? This cannot be undone.');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Flush');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    if (imagecache_external_flush_cache()) {
      drupal_set_message(t('Flushed external images'));
    }
    else {
      drupal_set_message(t('Could not flush external images'), 'error');
    }
    $form_state->setRedirect('imagecache_external.admin_settings');
  }

}
