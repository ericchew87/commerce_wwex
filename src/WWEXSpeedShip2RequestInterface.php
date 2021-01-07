<?php

namespace Drupal\commerce_wwex;

use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\Entity\ShippingMethodInterface;

interface WWEXSpeedShip2RequestInterface {

  /**
   * Gets a new WWEXService.
   *
   * @param array $configuration
   *   The Plugin Configuration.
   * @param array $wsdlOptions
   *   Additional options to send to AbstractSoapClientBase.
   * @param bool $resetSoapClient
   *   Whether to get a new soap client.
   *
   * @return \ericchew87\WWEXSpeedShip2PHP\Services\WWEXSpeedShip2Service
   *   The service.
   */
  public function getService(array $configuration, array $wsdlOptions = [], $resetSoapClient = TRUE);
}
