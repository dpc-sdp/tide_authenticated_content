<?php

namespace Drupal\tide_authenticated_content\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Drupal\node\NodeInterface;
/**
 * Excludes authenticated content from node indexes.
 *
 * @SearchApiProcessor(
 *   id = "authenticated_status",
 *   label = @Translation("Exclude authenticated status"),
 *   description = @Translation("Remove authenticated content from being indexed."),
 *   stages = {
 *     "alter_items" = -1,
 *   },
 * )
 */
class AuthenticatedStatus extends ProcessorPluginBase {

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {
    foreach ($items as $item_id => $item) {
      $entity = $item->getOriginalObject()->getValue();
      if ($entity instanceof NodeInterface && $entity->bundle() === 'landing_page') {
        if (
          $entity->hasField('field_authenticated_content')
          && !empty($entity->field_authenticated_content->getValue())
        ) {
          unset($items[$item_id]);
        }
      }
    }
  }

}
