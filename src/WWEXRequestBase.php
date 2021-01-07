<?php


namespace Drupal\commerce_wwex;


use ericchew87\WWEXSpeedShip2PHP\Services\WWEXSpeedShip2Service;
use ericchew87\WWEXSpeedShip2PHP\Structs\AuthenticationDetail;
use ericchew87\WWEXSpeedShip2PHP\Structs\AuthenticationToken;

abstract class WWEXRequestBase {

  /**
   * Gets the mode to use to connect.
   *
   * @param array $configuration
   *   The configuration array.
   *
   * @return string
   *   The mode (test or live).
   */
  protected function getMode(array $configuration) {
    $mode = 'test';

    if (!empty($configuration['api_information']['mode'])) {
      $mode = $configuration['api_information']['mode'];
    }

    return $mode;
  }

}
