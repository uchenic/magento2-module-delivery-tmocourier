<?php
/**
 * Copyright © 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace Magento\TMOcourier\Model;

use Magento\Framework\Module\Dir;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Carrier\AbstractCarrierOnline;
use Magento\Shipping\Model\Rate\Result;
use Magento\Framework\Xml\Security;

/**
 * Fedex shipping implementation
 *
 * @author     Magento Core Team <core@magentocommerce.com>
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class Carrier extends AbstractCarrierOnline implements \Magento\Shipping\Model\Carrier\CarrierInterface
{
    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'tmocourier';

    /**
     * Purpose of rate request
     *
     * @var string
     */
    const RATE_REQUEST_GENERAL = 'general';

    /**
     * Purpose of rate request
     *
     * @var string
     */
    const RATE_REQUEST_SMARTPOST = 'SMART_POST';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

    

    /**
     * Rate request data
     *
     * @var RateRequest|null
     */
    protected $_request = null;

    /**
     * Rate result data
     *
     * @var Result|null
     */
    protected $_result = null;

    /**
     * Path to wsdl file of rate service
     *
     * @var string
     */
    protected $_rateServiceWsdl;

    

    

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    protected $_storeManager;

    /**
     * @var \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory
     */
    protected $_productCollectionFactory;

    /**
     * @param \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig
     * @param \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory
     * @param \Psr\Log\LoggerInterface $logger
     * @param Security $xmlSecurity
     * @param \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory
     * @param \Magento\Shipping\Model\Rate\ResultFactory $rateFactory
     * @param \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory
     * @param \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory
     * @param \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory
     * @param \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory
     * @param \Magento\Directory\Model\RegionFactory $regionFactory
     * @param \Magento\Directory\Model\CountryFactory $countryFactory
     * @param \Magento\Directory\Model\CurrencyFactory $currencyFactory
     * @param \Magento\Directory\Helper\Data $directoryData
     * @param \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry
     * @param \Magento\Store\Model\StoreManagerInterface $storeManager
     * @param \Magento\Framework\Module\Dir\Reader $configReader
     * @param \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory
     * @param array $data
     *
     * @SuppressWarnings(PHPMD.ExcessiveParameterList)
     */
    public function __construct(
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Quote\Model\Quote\Address\RateResult\ErrorFactory $rateErrorFactory,
        \Psr\Log\LoggerInterface $logger,
        Security $xmlSecurity,
        \Magento\Shipping\Model\Simplexml\ElementFactory $xmlElFactory,
        \Magento\Shipping\Model\Rate\ResultFactory $rateFactory,
        \Magento\Quote\Model\Quote\Address\RateResult\MethodFactory $rateMethodFactory,
        \Magento\Shipping\Model\Tracking\ResultFactory $trackFactory,
        \Magento\Shipping\Model\Tracking\Result\ErrorFactory $trackErrorFactory,
        \Magento\Shipping\Model\Tracking\Result\StatusFactory $trackStatusFactory,
        \Magento\Directory\Model\RegionFactory $regionFactory,
        \Magento\Directory\Model\CountryFactory $countryFactory,
        \Magento\Directory\Model\CurrencyFactory $currencyFactory,
        \Magento\Directory\Helper\Data $directoryData,
        \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\Module\Dir\Reader $configReader,
        \Magento\Catalog\Model\ResourceModel\Product\CollectionFactory $productCollectionFactory,
        array $data = []
    ) {
        $this->_storeManager = $storeManager;
        $this->_productCollectionFactory = $productCollectionFactory;
        parent::__construct(
            $scopeConfig,
            $rateErrorFactory,
            $logger,
            $xmlSecurity,
            $xmlElFactory,
            $rateFactory,
            $rateMethodFactory,
            $trackFactory,
            $trackErrorFactory,
            $trackStatusFactory,
            $regionFactory,
            $countryFactory,
            $currencyFactory,
            $directoryData,
            $stockRegistry,
            $data
        );
       
        
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return [$this->getCarrierCode() => __($this->getConfigData('name'))];
    }

  
    protected function _doShipmentRequest(\Magento\Framework\DataObject $request){
        return 0;
    }
   

    public function collectRates(RateRequest $request){
        //$this->_debug(var_dump($request));
        $this->_request=$request;

        $city= $this->loadCityNameByPostal($this->_request->getDestPostcode(),$this->_request->getDestCountryId());

        $ammount= $this->getAmmountBycityName($city);
        
        $result = $this->_rateFactory->create();

        $method = $this->_rateMethodFactory->create();

        if($ammount===false){
            return $result;
        }

        $method->setCarrier('tmocourier');
        $method->setCarrierTitle('Ocourier');

        $method->setMethod('ocourier');
        $method->setMethodTitle('Self pick');

        

        $method->setPrice($ammount);
        $method->setCost($ammount);
       


       $result->append($method);
      // $this->_debug(var_dump($result));
        return $result;
       

    }

    protected function loadCityNameByPostal($code,$country)
    {   try {
        $url = sprintf('http://www.geopostcodes.com/inc/search.php?t=%s&tp=%s',$code,$country);
        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_USERAGENT => 'sample',
            CURLOPT_HTTPGET=>True,
            CURLOPT_COOKIE =>"geopclanguage=en",
            CURLOPT_HTTPHEADER => array("X-Requested-With: XMLHttpRequest",
                                        "Accept-Encoding: gzip, deflate",
                                        "Referer: http://www.geopostcodes.com/Russia",
                                        "Host: www.geopostcodes.com",
                                        "Accept: */*" )
        ));
        // Send the request & save response to $resp
        $resp = curl_exec($curl);
        $resp= gzdecode($resp);
        curl_close($curl);
        $myfile = fopen("postal_code_request.txt", "w");
        $ww= "<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"
            \"http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd\">
        <html xmlns=\"http://www.w3.org/1999/xhtml\" xml:lang=\"en-US\" dir=\"ltr\">
        <head> <meta http-equiv=\"Content-Type\" content=\"text/html;charset=UTF-8\"> </head><body>%s</body></html>";

        $qwe=sprintf($ww,$resp);
        $qwe=preg_replace("#&nbsp;#", '', $qwe);

        $q = new \DOMDocument;
        $q->loadHTML($qwe);
        
        $res = $q->getElementsByTagName('div')->item(0);
        // var_dump($items);
        $res->removeChild($res->getElementsByTagName('b')->item(0));
        $res->removeChild($res->getElementsByTagName('p')->item(0));
        $city_name=explode('/ ',$res->nodeValue)[1];
        return $city_name;
    } catch (Exception $e) {
        return '';
    }
        return '';
    }

    protected function getAmmountBycityName($city)
    {   try {
            $wsdl='';
            if ($this->getConfigData('sandbox_mode')==0) {
                $wsdl=$this->getConfigData('production_webservices_url');
            }else{
                $wsdl=$this->getConfigData('sandbox_webservices_url');
            }
            $soapclient = new \SoapClient($wsdl);

        //Use the functions of the client, the params of the function are in 
        //the associative array
        $params = array('login' => $this->getConfigData('account'),
             'password' => $this->getConfigData('password'),'contractID'=>$this->getConfigData('contract_id'));
        $flag=0;
        $response = $soapclient->getDeliveryCities($params);
        foreach ($response->GetDeliveryCitiesReply->Cities->string as $key => $value) {
            if ($value==$city) {
                $flag=1;
            }
        }
        if ($flag==0) {
            return false;
        }
        $params['cityName']=$city;
        $params['type']='Самовывоз';
        $variants = $soapclient->GetDeliveryVariantList($params);
        
        $variant_id= $variants->GetDeliveryVariantReply->Items->DeliveryVariant->Id;

        unset($params['cityName']);
        unset($params['type']);
        $params['deliveryVariantID']=$variant_id;
        $params['weight']=111;
        $amm= $soapclient->CalculateTariff($params);
        return $amm->CalculateTariffReply->Ammount;
    } catch (Exception $e) {
        return false;
    }
        return false;

    }

    /**
     * Get configured Store Shipping Origin
     *
     * @return array
     */
    protected function getShippingOrigin()
    {
        return [
            'country_id' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_COUNTRY_ID,
                ScopeInterface::SCOPE_STORE,
                $this->getStore()),
            'region_id' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_REGION_ID,
                ScopeInterface::SCOPE_STORE,
                $this->getStore()),
            'postcode' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_POSTCODE,
                ScopeInterface::SCOPE_STORE,
                $this->getStore()),
            'city' => $this->_scopeConfig->getValue(
                Config::XML_PATH_ORIGIN_CITY,
                ScopeInterface::SCOPE_STORE,
                $this->getStore())
        ];
    }
}
