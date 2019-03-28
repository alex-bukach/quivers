<?php

namespace Drupal\quivers\ParameterFactory;

use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\jsonrpc\Exception\JsonRpcException;
use Drupal\jsonrpc\ParameterDefinitionInterface;
use Drupal\jsonrpc\Object\Error;
use Drupal\jsonrpc\ParameterFactory\EntityParameterFactory;
use Shaper\Util\Context;
use Shaper\Validator\InstanceofValidator;

/**
 * A factory to create an order from entity UUID user input.
 */
class OrderParameterFactory extends EntityParameterFactory {

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
    return new InstanceofValidator(OrderInterface::class);
  }

  /**
   * {@inheritdoc}
   */
  protected function doTransform($data, Context $context = NULL) {
    if ($entity = $this->entityRepository->loadEntityByUuid('commerce_order', $data)) {
      return $entity;
    }
    throw JsonRpcException::fromError(Error::invalidParams('The requested order could not be found.'));
  }

}
