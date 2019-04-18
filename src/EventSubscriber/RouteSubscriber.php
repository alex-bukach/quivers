<?php

namespace Drupal\quivers\EventSubscriber;

use Drupal\Core\Routing\RouteSubscriberBase;
use Symfony\Component\Routing\RouteCollection;

class RouteSubscriber extends RouteSubscriberBase {

  /**
   * {@inheritdoc}
   */
  protected function alterRoutes(RouteCollection $collection) {
    if ($route = $collection->get('jsonrpc.handler')) {
      $route->setOption('_auth', ['oauth2']);
    }
  }

}
