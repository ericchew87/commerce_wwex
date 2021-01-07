<?php


namespace Drupal\commerce_wwex;


use ericchew87\WWEXSpeedShip2PHP\Services\WWEXSpeedShip2Service;
use ericchew87\WWEXSpeedShip2PHP\Structs\AuthenticationDetail;
use ericchew87\WWEXSpeedShip2PHP\Structs\AuthenticationToken;

class WWEXSpeedShip2Request extends WWEXRequestBase implements WWEXSpeedShip2RequestInterface {

  /**
   * {@inheritdoc}
   */
  public function getService(array $configuration, array $wsdlOptions = [], $resetSoapClient = TRUE) {
    $service = new WWEXSpeedShip2Service($wsdlOptions, $resetSoapClient, $this->getMode($configuration));

    $token = new AuthenticationToken(
      $configuration['api_information']['user_name'],
      $configuration['api_information']['password'],
      $configuration['api_information']['auth_key'],
      $configuration['api_information']['account_number']
    );
    $auth_detail = new AuthenticationDetail($token);
    $service->setSoapHeaderAuthenticationDetail($auth_detail);

    return $service;
  }

}
