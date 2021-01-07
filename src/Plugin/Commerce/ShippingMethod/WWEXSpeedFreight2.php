<?php

namespace Drupal\commerce_wwex\Plugin\Commerce\ShippingMethod;

use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_wwex\WWEXSpeedFreight2RequestInterface;
use Drupal\state_machine\WorkflowManagerInterface;
use ericchew87\WWEXSpeedFreight2PHP\Arrays\ArrayOfWSHandlingUnit;
use ericchew87\WWEXSpeedFreight2PHP\Arrays\ArrayOfWSLineItem;
use ericchew87\WWEXSpeedFreight2PHP\Structs\FreightShipmentCommodityDetails;
use ericchew87\WWEXSpeedFreight2PHP\Structs\FreightShipmentQuoteRequest;
use ericchew87\WWEXSpeedFreight2PHP\Structs\QuoteSpeedFreightShipment;
use ericchew87\WWEXSpeedFreight2PHP\Structs\WSHandlingUnit;
use ericchew87\WWEXSpeedFreight2PHP\Structs\WSLineItem;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the WorldWide Express SpeedFreight 2 shipping method.
 *
 * @CommerceShippingMethod(
 *  id = "wwex_speedfreight2",
 *  label = @Translation("WorldWide Express SpeedFreight 2"),
 *  services = {
 *    "CTII" = @translation("Central Transport"),
 *    "AACT" = @translation("AAA Cooper Transportation"),
 *    "SEFL" = @translation("Southeastern Freight Lines"),
 *    "RDFS" = @translation("Roadrunner Transportation Services"),
 *    "WARD" = @translation("Ward Trucking"),
 *    "CENF" = @translation("Central Freight Lines, Inc"),
 *    "FWDN" = @translation("Forward Air, Inc"),
 *    "HMES" = @translation("Holland"),
 *    "RLCA" = @translation("R & L Carriers Inc"),
 *    "UPGF" = @translation("UPS Freight"),
 *    "EXLA" = @translation("Estes Express Lines"),
 *    "CNWY" = @translation("XPO Logistics"),
 *    "ODFL" = @translation("Old Dominion"),
 *    "RDWY" = @translation("YRC"),
 *    "SAIA" = @translation("SAIA")
 *   }
 * )
 */
class WWEXSpeedFreight2 extends WWEXBase {

  /**
   * The WWEX SpeedShip2 Request service.
   *
   * @var \Drupal\commerce_wwex\WWEXSpeedFreight2RequestInterface
   */
  protected $wwexSpeedFreight2Request;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager, WWEXSpeedFreight2RequestInterface $wwex_speedfreight2_request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager);

    $this->wwexSpeedFreight2Request = $wwex_speedfreight2_request;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('plugin.manager.commerce_package_type'),
      $container->get('plugin.manager.workflow'),
      $container->get('commerce_wwex.wwex_speedfreight2_request')
    );
  }

  /**
   * Calculates rates for the given shipment.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \Drupal\commerce_shipping\ShippingRate[]
   *   The rates.
   */
  public function calculateRates(ShipmentInterface $shipment) {
    // Only attempt to collect rates if an address exists on the shipment.
    if ($shipment->getShippingProfile()->get('address')->isEmpty()) {
      return [];
    }

    if (empty($shipment->getPackageType())) {
      $shipment->setPackageType($this->getDefaultPackageType());
    }

    $wwex_service = $this->wwexSpeedFreight2Request->getService($this->configuration);
    $request = $this->getFreightShipmentQuoteRequest($shipment);
    $response = $wwex_service->quoteSpeedFreightShipment($request);

    $rates = [];
    if ($response) {
      $quote_response = $response->getQuoteSpeedFreightShipmentReturn();
      if ($quote_response->getResponseStatusCode() === '1') {
        $results = $quote_response->getFreightShipmentQuoteResults()->getFreightShipmentQuoteResult();
        foreach ($results as $result) {
          $rates[] = new ShippingRate([
            'shipping_method_id' => $this->parentEntity->id(),
            'service' => $this->services[$result->getCarrierSCAC()],
            'amount' => new Price($result->getTotalPrice(), 'USD'),
          ]);
        }
      }
    }

    return $rates;
  }

  protected function getFreightShipmentQuoteRequest(ShipmentInterface $shipment) {
    /** @var \Drupal\address\AddressInterface $recipient_address */
    $recipient_address = $shipment->getShippingProfile()->get('address')->first();
    $shipper_address = $shipment->getOrder()->getStore()->getAddress();

    $commodity_details = new FreightShipmentCommodityDetails(
      NULL,
      new ArrayOfWSHandlingUnit([
        new WSHandlingUnit(
          'Pallet',
          '1',
          '5',
          '30',
          '30',
          new ArrayOfWSLineItem([
            new WSLineItem(
              '85',
              '80',
              'Pallet of stuff',
              NULL,
              'Pallet',
              '1',
              'N',
              NULL
            )
          ])
        )
      ])
    );

    $request = new FreightShipmentQuoteRequest(
      $shipper_address->getLocality(),
      $shipper_address->getAdministrativeArea(), // required
      $shipper_address->getPostalCode(), // required
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      $recipient_address->getLocality(),
      $recipient_address->getAdministrativeArea(), // required
      $recipient_address->getPostalCode(), // required
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      NULL,
      $commodity_details // required
    );

    return new QuoteSpeedFreightShipment($request);
  }

}
