<?php

namespace Drupal\bulk_update_nodes\Controller;

use Drupal\node\Entity\NodeType;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Provides route responses for the bulk_update_nodes module.
 */
class BulkupdateController {

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function bulkupdate() {
    $current_path = \Drupal::service('path.current')->getPath();
    $arg = explode('/', $current_path);
    $type = '';

    if (!empty($arg)) {
      if (isset($arg[1]) && $arg[1] == 'bulk-update' && isset($arg[2])) {
        $type = $arg[2];
        $element = [
          '#markup' => bulk_update_nodes_bulk_update(trim($type), 0, 0),
        ];
        return $element;
      }
    }
  }

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function bulkupdatebatch() {
    $current_path = \Drupal::service('path.current')->getPath();
    $arg = explode('/', $current_path);
    $type = '';
    $from = $to = 0;
    if (!empty($arg)) {
      if (isset($arg[1]) && $arg[1] == 'bulk-update' && isset($arg[2])) {
        $type = $arg[2];
        if (isset($arg[3]) && isset($arg[4])) {
          $from = $arg[3];
          $to = $arg[4];
          $element = [
            '#markup' => bulk_update_nodes_bulk_updatebatch(trim($type), $from, $to),
          ];
        }
        return $element;
      }
    }
  }

  /**
   * Returns a simple page.
   *
   * @return array
   *   A simple renderable array.
   */
  public function bulkunpublish() {
    $current_path = \Drupal::service('path.current')->getPath();
    $arg = explode('/', $current_path);
    $type = '';

    if (!empty($arg)) {
      if (isset($arg[1]) && $arg[1] == 'bulk-unpublish' && isset($arg[2])) {
        $type = $arg[2];
        $element = [
          '#markup' => bulk_update_nodes_bulk_unpublish(trim($type)),
        ];
        return $element;
      }
    }
  }

  /**
   * Returns a bulk delete alias.
   */
  public function bulkdeletealias() {
    $current_path = \Drupal::service('path.current')->getPath();
    $arg = explode('/', $current_path);
    $type = '';

    if (!empty($arg)) {
      if (isset($arg[1]) && $arg[1] == 'bulk-delete-alias' && isset($arg[2])) {
        $type = $arg[2];
        $element = [
          '#markup' => bulk_update_nodes_delete_alias(trim($type), 0, 0),
        ];
        return $element;
      }
    }
  }

  /**
   * Returns a bulk delete country terms.
   */
  public function bulkdeletecountryterm() {
    $output = bulk_update_nodes_get_country_terms();

    return [
      '#markup' => $output,
    ];
  }

  /**
   * To remove webp from mongo.
   */
  public function removeWebp(string $type) {
    if (!NodeType::load($type)) {
      throw new NotFoundHttpException('Invalid content type.');
    }

    return [
      '#markup' => bulk_update_nodes_remove_webp($type),
    ];
  }

}
