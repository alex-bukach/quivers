<?php

namespace Drupal\quivers\Plugin\rest\resource;

use Drupal\rest\Plugin\rest\resource\EntityResource;

/**
 * Represents entities as resources.
 *
 * @RestResource(
 *   id = "quivers_entity",
 *   label = @Translation("Quivers entity"),
 *   serialization_class = "Drupal\Core\Entity\Entity",
 *   deriver = "Drupal\quivers\Plugin\Deriver\QuiversDeriver",
 *   uri_paths = {
 *     "canonical" = "/entity/{entity_type}/{entity}",
 *   }
 * )
 */
class QuiversResource extends EntityResource {

  /**
   * {@inheritdoc}
   */
  protected function getBaseRoute($canonical_path, $method) {
    $route = parent::getBaseRoute($canonical_path, $method);
    $route->setOption('_auth', ['oauth2'])->setRequirement('_format', 'json');
    return $route;
  }

}
