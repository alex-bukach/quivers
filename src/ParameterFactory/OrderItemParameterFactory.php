<?php

namespace Drupal\quivers\ParameterFactory;

use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\Object\Error;
use Drupal\jsonrpc\ParameterDefinitionInterface;
use Shaper\Util\Context;
use Shaper\Validator\InstanceofValidator;
use Drupal\jsonrpc\ParameterFactory\EntityParameterFactory;

/**
 * A factory to create an order item from entity UUID user input.
 */
class OrderItemParameterFactory extends EntityParameterFactory {

  /**
   * {@inheritdoc}
   */
  public static function schema(ParameterDefinitionInterface $parameter_definition = NULL) {
    return [
      'uuid' => ['type' => 'string'],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getOutputValidator() {
    return new InstanceofValidator(OrderItemInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context = NULL) {
    if ($entity = $this->entityRepository->loadEntityByUuid('commerce_order_item', $data)) {
      return $entity;
    }
    throw JsonRpcException::fromError(Error::invalidParams('The requested order item could not be found.'));
  }

}
