<?php
/**
 * @file
 * Contains \Drupal\ultimate_cron\Controller\JobController.
 */

namespace Drupal\ultimate_cron\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\ultimate_cron\Entity\CronJob;

/**
 * A controller to interact with CronJob entities.
 */
class JobController extends ControllerBase {

  /**
   * Runs a single cron job.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $ultimate_cron_job
   *   The cron job which will be run.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirects to the job listing after running a job.
   */
  public function runCronJob(CronJob $ultimate_cron_job) {
    $ultimate_cron_job->run();
    drupal_set_message($this->t('Cron job @job_label (@module) was successfully run.', ['@job_label' => $ultimate_cron_job->label(), '@module' => $ultimate_cron_job->getModuleName()]));
    return $this->redirect('entity.ultimate_cron_job.collection');
  }

  /**
   * Discovers new default cron jobs.
   */
  public function discoverJobs() {
    \Drupal::service('ultimate_cron.discovery')->discoverCronJobs();
    drupal_set_message($this->t('Completed discovery for now cron jobs.'));
    return $this->redirect('entity.ultimate_cron_job.collection');
  }

  /**
   * Displays a detailed cron job logs table.
   *
   * @param \Drupal\ultimate_cron\Entity\CronJob $ultimate_cron_job
   *   The cron job which will be run.
   *
   * @return array
   *   A render array as expected by drupal_render().
   */
  public function showLogs(CronJob $ultimate_cron_job) {

    $header = array(
      $this->t('Severity'),
      $this->t('Start Time'),
      $this->t('End Time'),
      $this->t('Message'),
      $this->t('Duration'),
    );

    $build['ultimate_cron_job_logs_table'] = [
      '#type' => 'table',
      '#header' => $header,
      '#empty' => $this->t('No log information available.'),
    ];

    $log_entries = $ultimate_cron_job->getLogEntries();
    foreach ($log_entries as $log_entry) {
      list($status, $title) = $log_entry->formatSeverity();
      $title = $log_entry->message ? $log_entry->message : $title;

      $row = [];
      $row['severity'] = $status;
      $row['severity']['#wrapper_attributes']['title'] = strip_tags($title);
      $row['start_time']['#markup'] = $log_entry->formatStartTime();
      $row['end_time']['#markup'] = $log_entry->formatEndTime();
      $row['message']['#markup'] = $log_entry->message ?: $log_entry->formatInitMessage();
      $row['duration']['#markup'] = $log_entry->formatDuration();

      $build['ultimate_cron_job_logs_table'][] = $row;
    }
    $build['#title'] = $this->t('Logs for %label', ['%label' => $ultimate_cron_job->label()]);
    return $build;

  }

}
