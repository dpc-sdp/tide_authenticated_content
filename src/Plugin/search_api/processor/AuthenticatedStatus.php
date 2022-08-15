<?php

namespace Drupal\tide_authenticated_content\Plugin\search_api\processor;

use Drupal\search_api\Processor\ProcessorPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;

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
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    /** @var static $processor */
    $processor = parent::create($container, $configuration, $plugin_id, $plugin_definition);

    return $processor;
  }

  /**
   * {@inheritdoc}
   */
  public function alterIndexedItems(array &$items) {

    /** @var \Drupal\search_api\Item\ItemInterface $item */
    foreach ($items as $item_id => $item) {
      $object = $item->getOriginalObject()->getValue();
      $bundle = $object->bundle();

      if ($bundle === 'landing_page' && $object->hasField('field_authenticated_content')) {
        if (!$object->field_authenticated_content->isEmpty()) {
          unset($items[$item_id]);
        }
      }
    }
  }

}
