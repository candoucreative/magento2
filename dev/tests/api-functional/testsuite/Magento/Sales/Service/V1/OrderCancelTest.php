<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace Magento\Sales\Service\V1;

use Magento\TestFramework\TestCase\WebapiAbstract;

class OrderCancelTest extends WebapiAbstract
{
    const SERVICE_VERSION = 'V1';

    const SERVICE_NAME = 'salesOrderManagementV1';

    /**
     * @magentoApiDataFixture Magento/Sales/_files/order.php
     */
    public function testOrderCancel()
    {
        $objectManager = \Magento\TestFramework\Helper\Bootstrap::getObjectManager();
        $order = $objectManager->get('Magento\Sales\Model\Order')->loadByIncrementId('100000001');
        $serviceInfo = [
            'rest' => [
                'resourcePath' => '/V1/order/' . $order->getId() . '/cancel',
                'httpMethod' => \Magento\Framework\Webapi\Rest\Request::HTTP_METHOD_POST,
            ],
            'soap' => [
                'service' => self::SERVICE_NAME,
                'serviceVersion' => self::SERVICE_VERSION,
                'operation' => self::SERVICE_NAME . 'cancel',
            ],
        ];
        $requestData = ['id' => $order->getId()];
        $result = $this->_webApiCall($serviceInfo, $requestData);
        $this->assertTrue($result);
    }
}
