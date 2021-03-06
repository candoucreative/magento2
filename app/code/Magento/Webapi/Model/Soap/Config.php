<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\Webapi\Model\Soap;

use Magento\Framework\App\Cache\Type\Webapi as WebApiCache;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem\Directory\ReadInterface;
use Magento\Webapi\Model\Config\Converter;

/**
 * Webapi Config Model for Soap.
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Config
{
    /**#@+
     * Keys that a used for service config internal representation.
     */
    const KEY_CLASS = 'class';

    const KEY_IS_SECURE = 'isSecure';

    const KEY_SERVICE_METHODS = 'methods';

    const KEY_METHOD = 'method';

    const KEY_IS_REQUIRED = 'inputRequired';

    const KEY_ACL_RESOURCES = 'resources';

    const CACHE_ID = 'soap-services-config';

    /**#@-*/

    /** @var ReadInterface */
    protected $modulesDirectory;

    /** @var \Magento\Webapi\Model\Config */
    protected $config;

    /** @var \Magento\Framework\ObjectManagerInterface */
    protected $objectManager;

    /**
     * SOAP services should be stored separately as the list of available operations
     * is collected using reflection, not taken from config as for REST
     *
     * @var array
     */
    protected $soapServices;

    /**
     * List of SOAP operations available in the system
     *
     * @var array
     */
    protected $soapOperations;

    /**
     * @var \Magento\Webapi\Model\Soap\Config\ClassReflector
     */
    protected $classReflector;

    /**
     * @var WebApiCache
     */
    protected $cache;

    /** @var \Magento\Framework\Registry */
    protected $registry;

    /**
     * Initialize dependencies.
     *
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\Filesystem $filesystem
     * @param \Magento\Webapi\Model\Config $config
     * @param \Magento\Webapi\Model\Soap\Config\ClassReflector $classReflector
     * @param WebApiCache $cache
     * @param \Magento\Framework\Registry $registry
     */
    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\Filesystem $filesystem,
        \Magento\Webapi\Model\Config $config,
        \Magento\Webapi\Model\Soap\Config\ClassReflector $classReflector,
        WebApiCache $cache,
        \Magento\Framework\Registry $registry
    ) {
        $this->modulesDirectory = $filesystem->getDirectoryRead(DirectoryList::MODULES);
        $this->config = $config;
        $this->objectManager = $objectManager;
        $this->classReflector = $classReflector;
        $this->cache = $cache;
        $this->registry = $registry;
        //Initialize cache
        $this->soapServices = $this->initServicesMetadata();
    }

    /**
     * Retrieve the list of SOAP operations available in the system
     *
     * @param array $requestedService The list of requested services with their versions
     * @return array <pre>
     * array(
     *     array(
     *         'class' => $serviceClass,
     *         'method' => $serviceMethod
     *         'isSecure' => $isSecure
     *     ),
     *      ...
     * )</pre>
     */
    protected function getSoapOperations($requestedService)
    {
        if (null == $this->soapOperations) {
            $this->soapOperations = [];
            foreach ($this->getRequestedSoapServices($requestedService) as $serviceData) {
                foreach ($serviceData[self::KEY_SERVICE_METHODS] as $methodData) {
                    $method = $methodData[self::KEY_METHOD];
                    $class = $serviceData[self::KEY_CLASS];
                    $operationName = $this->getSoapOperation($class, $method);
                    $this->soapOperations[$operationName] = [
                        self::KEY_CLASS => $class,
                        self::KEY_METHOD => $method,
                        self::KEY_IS_SECURE => $methodData[self::KEY_IS_SECURE],
                        self::KEY_ACL_RESOURCES => $methodData[self::KEY_ACL_RESOURCES],
                    ];
                }
            }
        }
        return $this->soapOperations;
    }

    /**
     * Return services loaded from cache if enabled or from files merged previously
     *
     * @return array
     */
    protected function getSoapServicesConfig()
    {
        if (null === $this->soapServices) {
            $soapServicesConfig = $this->cache->load(self::CACHE_ID);
            if ($soapServicesConfig && is_string($soapServicesConfig)) {
                $this->soapServices = unserialize($soapServicesConfig);
            } else {
                $this->soapServices = $this->initServicesMetadata();
                $this->cache->save(serialize($this->soapServices), self::CACHE_ID);
            }
        }
        return $this->soapServices;
    }

    /**
     * Collect the list of services with their operations available in SOAP.
     *
     * @return array
     */
    protected function initServicesMetadata()
    {
        $soapServices = [];
        foreach ($this->config->getServices()[Converter::KEY_SERVICES] as $serviceClass => $serviceData) {
            $serviceName = $this->getServiceName($serviceClass);
            foreach ($serviceData as $methodName => $methodMetadata) {
                $soapServices[$serviceName][self::KEY_SERVICE_METHODS][$methodName] = [
                    self::KEY_METHOD => $methodName,
                    self::KEY_IS_REQUIRED => (bool)$methodMetadata[Converter::KEY_SECURE],
                    self::KEY_IS_SECURE => $methodMetadata[Converter::KEY_SECURE],
                    self::KEY_ACL_RESOURCES => $methodMetadata[Converter::KEY_ACL_RESOURCES],
                ];
                $soapServices[$serviceName][self::KEY_CLASS] = $serviceClass;
            }
            $reflectedMethodsMetadata = $this->classReflector->reflectClassMethods(
                $serviceClass,
                $soapServices[$serviceName][self::KEY_SERVICE_METHODS]
            );
            $soapServices[$serviceName][self::KEY_SERVICE_METHODS] = array_merge_recursive(
                $soapServices[$serviceName][self::KEY_SERVICE_METHODS],
                $reflectedMethodsMetadata
            );
        }

        return $soapServices;
    }

    /**
     * Retrieve service method information, including service class, method name, and isSecure attribute value.
     *
     * @param string $soapOperation
     * @param array $requestedServices The list of requested services with their versions
     * @return array
     * @throws \Magento\Framework\Webapi\Exception
     */
    public function getServiceMethodInfo($soapOperation, $requestedServices)
    {
        $soapOperations = $this->getSoapOperations($requestedServices);
        if (!isset($soapOperations[$soapOperation])) {
            throw new \Magento\Framework\Webapi\Exception(
                __('Operation "%1" not found.', $soapOperation),
                0,
                \Magento\Framework\Webapi\Exception::HTTP_NOT_FOUND
            );
        }
        return [
            self::KEY_CLASS => $soapOperations[$soapOperation][self::KEY_CLASS],
            self::KEY_METHOD => $soapOperations[$soapOperation][self::KEY_METHOD],
            self::KEY_IS_SECURE => $soapOperations[$soapOperation][self::KEY_IS_SECURE],
            self::KEY_ACL_RESOURCES => $soapOperations[$soapOperation][self::KEY_ACL_RESOURCES]
        ];
    }

    /**
     * Retrieve the list of services corresponding to specified services and their versions.
     *
     * @param array $requestedServices array('FooBarV1', 'OtherBazV2', ...)
     * @return array Filtered list of services
     */
    public function getRequestedSoapServices(array $requestedServices)
    {
        $services = [];
        $soapServicesConfig = $this->getSoapServicesConfig();
        foreach ($requestedServices as $serviceName) {
            if (isset($soapServicesConfig[$serviceName])) {
                $services[] = $soapServicesConfig[$serviceName];
            }
        }
        return $services;
    }

    /**
     * Generate SOAP operation name.
     *
     * @param string $interfaceName e.g. \Magento\Catalog\Api\ProductInterfaceV1
     * @param string $methodName e.g. create
     * @return string e.g. catalogProductCreate
     */
    public function getSoapOperation($interfaceName, $methodName)
    {
        $serviceName = $this->getServiceName($interfaceName);
        $operationName = $serviceName . ucfirst($methodName);
        return $operationName;
    }

    /**
     * Retrieve specific service interface data.
     *
     * @param string $serviceName
     * @return array
     * @throws \RuntimeException
     */
    public function getServiceMetadata($serviceName)
    {
        $soapServicesConfig = $this->getSoapServicesConfig();
        if (!isset($soapServicesConfig[$serviceName]) || !is_array($soapServicesConfig[$serviceName])) {
            throw new \RuntimeException(__('Requested service is not available: "%1"', $serviceName));
        }
        return $soapServicesConfig[$serviceName];
    }

    /**
     * Translate service interface name into service name.
     * Example:
     * <pre>
     * - 'Magento\Customer\Service\V1\CustomerAccountInterface', false => customerCustomerAccount
     * - 'Magento\Customer\Service\V1\CustomerAddressInterface', true  => customerCustomerAddressV1
     * </pre>
     *
     * @param string $interfaceName
     * @param bool $preserveVersion Should version be preserved during interface name conversion into service name
     * @return string
     * @throws \InvalidArgumentException
     */
    public function getServiceName($interfaceName, $preserveVersion = true)
    {
        $serviceNameParts = $this->config->getServiceNameParts($interfaceName, $preserveVersion);
        return lcfirst(implode('', $serviceNameParts));
    }
}
