<?php

namespace Drupal\quivers\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

class CommitTransactionSubscriber implements EventSubscriberInterface {
  
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {

    $events = [
      'commerce_order.return.post_transition' => ['returnTransaction', 300],
    ];
    return $events;
  }

  /**
   * Redirecting Admin to Order Edit Page.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function returnTransaction(WorkflowTransitionEvent $event) {
    // * @var \Drupal\commerce_order\Entity\OrderInterface $order 
    $order = $event->getEntity();

    // Redirect Admin to Edit Order Page
    $response = new RedirectResponse($order->getOrderNumber()."/edit");
    $response->send();
    drupal_set_message("Please update refunded amount, quantity and state of the returned items below.");
  }
}
