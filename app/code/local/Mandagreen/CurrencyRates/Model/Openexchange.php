<?php

class Mandagreen_CurrencyRates_Model_Openexchange extends Mage_Directory_Model_Currency_Import_Abstract 
{
    const KEY_API_ID = 'currency/openexchange/api_id';
    const KEY_TIMEOUT = 'currency/openexchange/timeout';

    protected $_url = 'http://openexchangerates.org/api/latest.json';
    protected $_appId;
    
    protected $_messages = array();

     /**
     * HTTP client
     *
     * @var Varien_Http_Client
     */
    protected $_httpClient;

    
    public function __construct() {
        $this->_httpClient = new Varien_Http_Client();
        $this->_appId = Mage::getStoreConfig(self::KEY_API_ID);
    }

    protected function _saveCache($response) {
        $id = 'openexchangerates_' . date('Ymd');
        Mage::app()->saveCache($response, $id, array(), 24 * 3600);
        
        return $this;
    }
    
    protected function _loadCache() {
        $id = 'openexchangerates_' . date('Ymd');
        return Mage::app()->loadCache($id);
    }
    
    protected function _convert($currencyFrom, $currencyTo, $retry = 0) {
        $response = $this->_loadCache();
        if (!$this->_appId) {
            return;
        }
        
        if (!$response) {
            $timeout = Mage::getStoreConfig(self::KEY_TIMEOUT);
            $response = $this->_httpClient
                ->setUri($this->_url)
                ->setParameterGet('app_id', $this->_appId)
                ->setConfig(array('timeout' => $timeout > 0 ? $timeout : 10))
                ->request('GET')
                ->getBody();
            
            $this->_saveCache($response);
        }
        
        $data = Zend_Json::decode($response);
        if (!isset($data['base'])) {
            return;
        }
        
        if ($data['base'] == $currencyFrom) {
            return isset($data['rates'][$currencyTo]) ? $data['rates'][$currencyTo] : null;
        }
        
        return isset($data['rates'][$currencyTo]) && isset($data['rates'][$currencyFrom]) ?
            $data['rates'][$currencyTo] / $data['rates'][$currencyFrom] :
            null;
    }

    protected function _getCurrencyCodes() {
        return explode(',', Mage::getStoreConfig('currency/options/allow'));
    }
    
    protected function _getDefaultCurrencyCodes() {
        return explode(',', Mage::getStoreConfig('currency/options/default'));
    }

    protected function _saveRates($rates) {
        $rs = Mage::getSingleton('core/resource');
        $write = $rs->getConnection('core_write');
        $table = $rs->getTableName('directory/currency_rate');
        
        $write->delete($table);
        foreach ($rates as $baseCurrencyCode => $currencyRates) {
            foreach ($currencyRates as $code => $value) {
                if (empty($value)) {
                    continue; 
                }
                
                $write->insert($table, array(
                    'currency_from' => $baseCurrencyCode,
                    'currency_to' => $code,
                    'rate' => $value
                ));
            }
        } 
    }
}