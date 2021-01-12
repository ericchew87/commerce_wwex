<?php

namespace Drupal\commerce_wwex\Plugin\Commerce\ShippingMethod;

use Drupal\address\AddressInterface;
use Drupal\commerce_packaging\ShipmentPackagerManager;
use Drupal\commerce_price\Price;
use Drupal\commerce_shipping\Entity\ShipmentInterface;
use Drupal\commerce_shipping\PackageTypeManagerInterface;
use Drupal\commerce_shipping\Plugin\Commerce\ShippingMethod\ShippingMethodBase;
use Drupal\commerce_shipping\ShippingRate;
use Drupal\commerce_wwex\WWEXSpeedShip2RequestInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\physical\Measurement;
use Drupal\state_machine\WorkflowManagerInterface;
use ericchew87\WWEXSpeedShip2PHP\Structs\GetUPSServiceDetails;
use ericchew87\WWEXSpeedShip2PHP\Structs\RateServiceOptions;
use ericchew87\WWEXSpeedShip2PHP\Structs\ShipmentPackage;
use ericchew87\WWEXSpeedShip2PHP\Structs\ShipmentPackages;
use ericchew87\WWEXSpeedShip2PHP\Structs\SimpleShipmentAddress;
use ericchew87\WWEXSpeedShip2PHP\Structs\UPSServiceDetailRequest;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides the WorldWide Express SpeedShip 2 shipping method.
 *
 * @CommerceShippingMethod(
 *  id = "wwex_speedship2",
 *  label = @Translation("WorldWide Express SpeedShip 2"),
 *  services = {
 *    "1DA" = @translation("UPS Next Day Air"),
 *    "1DAS" = @translation("UPS Next Day Air Saturday"),
 *    "1DM" = @translation("UPS Next Day Air Early"),
 *    "1DMS" = @translation("UPS Next Day Air Early Saturday"),
 *    "1DP" = @translation("UPS Next Day Air Saver"),
 *    "2DA" = @translation("UPS Second Day Air"),
 *    "2DAS" = @translation("UPS Second Day Air Saturday"),
 *    "2DM" = @translation("UPS Second Day Air AM"),
 *    "GND" = @translation("UPS Ground"),
 *    "3DS" = @translation("UPS Three-Day Select"),
 *   }
 * )
 */
class WWEXSpeedShip2 extends WWEXBase {

  /**
   * The WWEX SpeedShip2 Request service.
   *
   * @var \Drupal\commerce_wwex\WWEXSpeedShip2RequestInterface
   */
  protected $wwexSpeedShip2Request;

  /**
   * Constructs a new WWEXSpeedShip2 object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\commerce_shipping\PackageTypeManagerInterface $package_type_manager
   *   The package type manager.
   * @param \Drupal\state_machine\WorkflowManagerInterface $workflow_manager
   *   The workflow manager.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\commerce_packaging\ShipmentPackagerManager $shipment_packager
   *   The shipment packager.
   * @param \Drupal\commerce_wwex\WWEXSpeedShip2RequestInterface $wwex_speedship2_request
   *   The WWEX SpeedShip2 Request service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, PackageTypeManagerInterface $package_type_manager, WorkflowManagerInterface $workflow_manager, EntityTypeManagerInterface $entity_type_manager, ShipmentPackagerManager $shipment_packager, WWEXSpeedShip2RequestInterface $wwex_speedship2_request) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $package_type_manager, $workflow_manager, $entity_type_manager, $shipment_packager);

    $this->wwexSpeedShip2Request = $wwex_speedship2_request;
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
      $container->get('entity_type.manager'),
      $container->get('plugin.manager.commerce_shipment_packager'),
      $container->get('commerce_wwex.wwex_speedship2_request')
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

    $shipment = clone $shipment;
    $this->packageShipment($shipment, $this);

    $wwex_service = $this->wwexSpeedShip2Request->getService($this->configuration);
    $request = $this->getUPSServiceDetailRequest($shipment);
    $response = $wwex_service->getUPSServiceDetails($request);

    $rates = [];
    if ($response) {
      $service_response = $response->getUpsServiceDetailResponse()->getServiceResponse();
      if ($service_response->getResponseStatusCode() === "0") {
        $service_details = $response->getUpsServiceDetailResponse()->getUpsServiceDetails()->getUpsServiceDetail();
        foreach ($service_details as $service_detail) {
          $service_fee_detail = $service_detail->getServiceFeeDetail();
          $rate_data = [
            'shipping_method_id' => $this->parentEntity->id(),
            'service' => $this->services[$service_detail->getServiceCode()],
            'amount' => new Price($service_fee_detail->getServiceFeeGrandTotal(), 'USD'),
          ];

          if ($delivery_date = $service_detail->getEstimateDelivery()) {
            // Default forma it is 08:30 AM Friday 01/01/21
            $date_format = 'h:i A l m/d/y';
            // Ground shipments need special format.
            if (str_contains($delivery_date, 'End of Day')) {
              // remove "End of Day " from the string.
              $delivery_date = str_replace('End of Day ', '', $delivery_date);
              // update the date format.
              $date_format = 'l m/d/y';
            }
            $date = DrupalDateTime::createFromFormat($date_format, $delivery_date);
            $rate_data['delivery_date'] = $date;
          }

          $rates[] = new ShippingRate($rate_data);
        }
      }
    }

    return $rates;
  }

  public function selectRate(ShipmentInterface $shipment, ShippingRate $rate) {
    parent::selectRate($shipment, $rate);
    $this->packageShipment($shipment, $this);
  }

  /**
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \ericchew87\WWEXSpeedShip2PHP\Structs\GetUPSServiceDetails
   *   The request.
   */
  protected function getUPSServiceDetailRequest(ShipmentInterface $shipment) {
    /** @var \Drupal\address\AddressInterface $recipient_address */
    $recipient_address = $shipment->getShippingProfile()->get('address')->first();
    $shipper_address = $shipment->getOrder()->getStore()->getAddress();

    $request = new UPSServiceDetailRequest(
      $this->getRateServiceOptions(),
      $this->getWWEXShipmentAddress($shipper_address),
      $this->getWWEXShipmentAddress($recipient_address),
      $this->getWWEXShipmentPackages($shipment)
    );

    return new GetUPSServiceDetails($request);
  }

  /**
   * Gets the ShipmentPackages.
   *
   * @param \Drupal\commerce_shipping\Entity\ShipmentInterface $shipment
   *   The shipment.
   *
   * @return \ericchew87\WWEXSpeedShip2PHP\Structs\ShipmentPackages
   *   An array of shipment packages.
   */
  protected function getWWEXShipmentPackages(ShipmentInterface $shipment) {
    $packages = [];

    $order_item_storage = $this->entityTypeManager->getStorage('commerce_order_item');

    foreach ($shipment->getItems() as $delta => $shipment_item) {
      $quantity = $shipment_item->getQuantity();

      $item_weight = $this->ensureUnitOfMeasure($shipment_item->getWeight(), 'lb');
      // The item weight was multiplied by the quantity in the packer...divide it back out.
      $item_weight = $item_weight->divide($quantity);

      $length = NULL;
      $width = NULL;
      $height = NULL;

      // Try to get the dimensions from the purchased item.
      /** @var \Drupal\commerce_order\Entity\OrderItemInterface $order_item */
      $order_item = $order_item_storage->load($shipment_item->getOrderItemId());
      if ($order_item->hasPurchasedEntity()) {
        $purchased_entity = $order_item->getPurchasedEntity();
        if ($purchased_entity->hasField('dimensions') && !$purchased_entity->get('dimensions')->isEmpty()) {
          /** @var \Drupal\physical\Plugin\Field\FieldType\DimensionsItem $dimensions */
          $dimensions = $purchased_entity->get('dimensions')->first();
          $length = $this->ensureUnitOfMeasure($dimensions->getLength(), 'in');
          $width = $this->ensureUnitOfMeasure($dimensions->getWidth(), 'in');
          $height = $this->ensureUnitOfMeasure($dimensions->getHeight(), 'in');
        }
      }

      // Fallback to shipment default package if no dimensions available from purchased item.
      if (!$length || !$width || !$height) {
        $package_type = $shipment->getPackageType();
        $length = $this->ensureUnitOfMeasure($package_type->getLength(), 'in');
        $width = $this->ensureUnitOfMeasure($package_type->getWidth(), 'in');
        $height = $this->ensureUnitOfMeasure($package_type->getHeight(), 'in');
      }

      $package = new ShipmentPackage(
          NULL,
          NULL,
          NULL,
          NULL,
          NULL,
          NULL,
          NULL,
          $height->getNumber(),
          NULL,
          NULL,
          $length->getNumber(),
          (string) ($delta +1),
          '00',
          $item_weight->getNumber(),
          $width->getNumber()
        );
        $packages[] = $package;
    }

    return new ShipmentPackages($packages);
  }

  /**
   * Ensures a measurement object unit of measure matches
   * the passed in unit of measure.
   *
   * @param \Drupal\physical\Measurement $measurement
   *   The measurement object.
   * @param string $unit
   *   The unit of measure.
   *
   * @return \Drupal\physical\Measurement
   *   The measurement object.
   */
  protected function ensureUnitOfMeasure(Measurement $measurement, $unit) {
    if ($measurement->getUnit() !== $unit) {
      $measurement = $measurement->convert($unit);
    }

    return $measurement;
  }

  /**
   * Converts an address into a WWEX SimpleShipmentAddress.
   *
   * @param \Drupal\address\AddressInterface $address
   *   The address.
   *
   * @return \ericchew87\WWEXSpeedShip2PHP\Structs\SimpleShipmentAddress
   *   The wwex shipment address.
   */
  protected function getWWEXShipmentAddress(AddressInterface $address) {
    return new SimpleShipmentAddress(
      $address->getLocality(),
      $address->getCountryCode(),
      $address->getPostalCode(),
      'N',
      $address->getAdministrativeArea()
    );
  }

  /**
   * Gets the rate service options.
   *
   * @return \ericchew87\WWEXSpeedShip2PHP\Structs\RateServiceOptions
   *   The rate service options.
   */
  protected function getRateServiceOptions() {
    $options = new RateServiceOptions(
      NULL,
      'N',
      'N',
      'N',
      'N',
      'N',
      'N',
      'N',
      'S'
    );

    return $options;
  }

}
