<?php


namespace Drupal\commerce_wwex;


use ericchew87\WWEXSpeedFreight2PHP\Services\WWEXSpeedFreight2Service;
use ericchew87\WWEXSpeedFreight2PHP\Structs\AuthenticationToken;

class WWEXSpeedFreight2Request extends WWEXRequestBase implements WWEXSpeedFreight2RequestInterface {

  /**
   * {@inheritdoc}
   */
  public function getService(array $configuration, array $wsdlOptions = [], $resetSoapClient = TRUE) {
    $service = new WWEXSpeedFreight2Service($wsdlOptions, $resetSoapClient, $this->getMode($configuration));

    $token = new AuthenticationToken(
      $configuration['api_information']['user_name'],
      $configuration['api_information']['password'],
      $configuration['api_information']['auth_key'],
      $configuration['api_information']['account_number']
    );
    $service->setSoapHeaderAuthenticationToken($token);

    return $service;
  }

}
