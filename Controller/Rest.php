<?php

namespace SpringImport\RestApiFilters\Controller;

use Magento\Framework\Webapi\Authorization;
use Magento\Framework\Exception\AuthorizationException;
use Magento\Framework\Webapi\ErrorProcessor;
use Magento\Framework\Webapi\Request;
use Magento\Framework\Webapi\ServiceInputProcessor;
use Magento\Framework\Webapi\ServiceOutputProcessor;
use Magento\Framework\Webapi\Rest\Request as RestRequest;
use Magento\Framework\Webapi\Rest\Response as RestResponse;
//use Magento\Framework\Webapi\Rest\Response\FieldsFilter;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Webapi\Controller\PathProcessor;
use Magento\Webapi\Controller\Rest\ParamsOverrider;
use Magento\Webapi\Controller\Rest\Router;
use Magento\Webapi\Controller\Rest\Router\Route;
use Magento\Webapi\Model\Rest\Swagger\Generator;
use Magento\Framework\App\DeploymentConfig;
use Magento\Framework\Config\ConfigOptionsListConstants;
use SpringImport\RestApiFilters\Filter\AbstractFilter;
use SpringImport\RestApiFilters\Filter\AbstractFieldsFilter;
use Magento\Framework\Exception\LocalizedException;
use SpringImport\RestApiFilters\Filter\FieldsFilter;

/**
 * Front controller for WebAPI REST area.
 *
 * TODO: Consider warnings suppression removal
 *
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 * @SuppressWarnings(PHPMD.TooManyFields)
 */
class Rest extends \Magento\Webapi\Controller\Rest
{
    /**
     * @var DeploymentConfig
     */
    private $deploymentConfig;

    /**
     * @var \Magento\Webapi\Controller\Rest\InputParamsResolver
     */
    private $inputParamsResolver;

    /**
     * Rest constructor
     *
     * @param RestRequest $request
     * @param RestResponse $response
     * @param Router $router
     * @param \Magento\Framework\ObjectManagerInterface $objectManager
     * @param \Magento\Framework\App\State $appState
     * @param Authorization $authorization
     * @param ServiceInputProcessor $serviceInputProcessor
     * @param ErrorProcessor $errorProcessor
     * @param PathProcessor $pathProcessor
     * @param \Magento\Framework\App\AreaList $areaList
     * @param \Magento\Framework\Webapi\Rest\Response\FieldsFilter $fieldsFilter
     * @param ParamsOverrider $paramsOverrider
     * @param ServiceOutputProcessor $serviceOutputProcessor
     * @param Generator $swaggerGenerator
     * @param StoreManagerInterface $storeManager
     */
    public function __construct(
        RestRequest $request,
        RestResponse $response,
        Router $router,
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Framework\App\State $appState,
        Authorization $authorization,
        ServiceInputProcessor $serviceInputProcessor,
        ErrorProcessor $errorProcessor,
        PathProcessor $pathProcessor,
        \Magento\Framework\App\AreaList $areaList,
        \Magento\Framework\Webapi\Rest\Response\FieldsFilter $fieldsFilter,
        ParamsOverrider $paramsOverrider,
        ServiceOutputProcessor $serviceOutputProcessor,
        Generator $swaggerGenerator,
        StoreManagerInterface $storeManager
    ) {
        parent::__construct(
            $request, $response, $router, $objectManager, $appState, $authorization,
            $serviceInputProcessor,
            $errorProcessor, $pathProcessor, $areaList, $fieldsFilter, $paramsOverrider, $serviceOutputProcessor,
            $swaggerGenerator, $storeManager
        );
    }

    /**
     * Get deployment config
     *
     * @return DeploymentConfig
     */
    private function getDeploymentConfig()
    {
        if (!$this->deploymentConfig instanceof \Magento\Framework\App\DeploymentConfig) {
            $this->deploymentConfig = \Magento\Framework\App\ObjectManager::getInstance()
                ->get('Magento\Framework\App\DeploymentConfig');
        }
        return $this->deploymentConfig;
    }

    /**
     * Execute API request
     *
     * @return void
     * @throws AuthorizationException
     * @throws \Magento\Framework\Exception\InputException
     * @throws \Magento\Framework\Webapi\Exception
     */
    protected function processApiRequest()
    {
        $inputParams = $this->getInputParamsResolver()->resolve();

        $route = $this->getInputParamsResolver()->getRoute();
        $serviceMethodName = $route->getServiceMethod();
        $serviceClassName = $route->getServiceClass();

        $service = $this->_objectManager->get($serviceClassName);
        /** @var \Magento\Framework\Api\AbstractExtensibleObject $outputData */
        $outputData = call_user_func_array([$service, $serviceMethodName], $inputParams);
        $outputData = $this->serviceOutputProcessor->process(
            $outputData,
            $serviceClassName,
            $serviceMethodName
        );
        $filters = $this->getFilters();
        if ($filters && is_array($outputData)) {
            foreach ($filters as $filterClassName) {
                $outputData = $this->applyFilter($filterClassName, $outputData);
            }
        }
        $header = $this->getDeploymentConfig()->get(ConfigOptionsListConstants::CONFIG_PATH_X_FRAME_OPT);
        if ($header) {
            $this->_response->setHeader('X-Frame-Options', $header);
        }
        $this->_response->prepareResponse($outputData);
    }

    /**
     * @param $filterClassName
     * @param $outputData
     * @return array
     * @throws LocalizedException
     */
    protected function applyFilter($filterClassName, $outputData)
    {
        $filter = $this->_objectManager->create($filterClassName);

        if (!$filter instanceof AbstractFilter) {
            throw new LocalizedException(
                __(
                    'Filter class must be an instance of ' . AbstractFilter::class
                )
            );
        }

        return $filter->filter($outputData);
    }

    /**
     * Filter classes list
     *
     * @return array
     */
    public function getFilters()
    {
        return [
            FieldsFilter::class
        ];
    }

    /**
     * The getter function to get InputParamsResolver object
     *
     * @return \Magento\Webapi\Controller\Rest\InputParamsResolver
     *
     * @deprecated
     */
    private function getInputParamsResolver()
    {
        if ($this->inputParamsResolver === null) {
            $this->inputParamsResolver = \Magento\Framework\App\ObjectManager::getInstance()
                ->get(\Magento\Webapi\Controller\Rest\InputParamsResolver::class);
        }
        return $this->inputParamsResolver;
    }
}
