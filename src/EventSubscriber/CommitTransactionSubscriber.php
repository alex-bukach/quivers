<?php

namespace Drupal\quivers_drupal_plugin\EventSubscriber;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class CommitTransactionSubscriber implements EventSubscriberInterface {
  
  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {

    $events = [
      'commerce_order.place.pre_transition' => ['commitTransaction', 300],
    ];
    return $events;
  }

  /**
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The workflow transition event.
   */
  public function commitTransaction(WorkflowTransitionEvent $event) {
    // * @var \Drupal\commerce_order\Entity\OrderInterface $order 
    $order = $event->getEntity();

    // Change Transition state to Validate, which will change order status to 'readytofulfill'
    // Do it only for payment gateway with id's -> stripe_gateway, paypal_gateway
    if (!$order->get('payment_gateway')->isEmpty()) {
      $order_payment_method = $order->get('payment_gateway')->first()->entity->id();

      if ( ($order_payment_method == 'stripe_gateway') || ($order_payment_method == 'paypal_gateway') ) {
        // if transition is not changed, then this could be a core problem, https://www.drupal.org/project/drupal/issues/2974156
        $order->getState()->applyTransitionById('validate');
        // $order->save();
      }
    }
  }
}
