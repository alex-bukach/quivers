<?php

namespace Drupal\quivers\Plugin\TrackingNumberType;

use Drupal\tracking_number\Plugin\TrackingNumberTypeBase;
use Drupal\Core\Url;

/**
 * Provides a Other tracking number type.
 *
 * @TrackingNumberType(
 *   id = "other",
 *   label = @Translation("Other")
 * )
 */
class Other extends TrackingNumberTypeBase {

  /**
   * {@inheritdoc}
   */
  public function getTrackingUrl($number) {
    return Url::fromUserInput('#');
  }

}
