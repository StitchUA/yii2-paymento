<?php

namespace stitchua\paymento;

use stitchua\paymento\models\BasePaymentoModel;
use yii\base\InvalidConfigException;
use yii\base\Module;
use yii\helpers\ArrayHelper;

/**
 * Klasa do obsługi płatnosci systemu 'Paymento'
 *
 * Konfiguracja w pliku config/web.php sekcja 'modules'
 * ```
 * 'paymento' => [
 *      //ID klienta Identyfikator klienta w Paymento
 *      'merchantId' => '',
 *      'shops' => [
 *          // nazwa sklepu w crm
 *          's7health' => [
 *              'serviceId' => '',  // Identyfikator sklepu w Paymento
 *              'serviceKey' => ''  // Klucz sklepu w Paymento
 *          ]
 *      ]
 * ]
 *```
 *
 * Po dodaniu modułu do projektu należy wykonać polecenie w konsoli:
 *
 * ```
 * php yii migrate --migrationPath="@app/modules/paymento/migrations"
 * ```
 */
class Paymento extends Module
{
    private string $configShopServiceId = 'serviceId';
    private string $configShopServiceKey = 'serviceKey';

    /** @var string|null  ID klienta z panelu Paymento*/
    public $merchantId = null;
    /** @var array|null Sklepy do których bedą wysyłane płatności. ustwione w panelu administratora w Paymento */
    public $shops = null;

    /** @var string Nazwa sklepu ustawiana w config/web.php konfiguracji modułu */
    public const S7HEALTH_SHOP = 's7health';

    public const HASH_METHOD = 'sha256';

    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'app\modules\paymento\controllers';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if(empty($this->merchantId) || !is_array($this->shops) || empty($this->shops)){
            throw new InvalidConfigException('Zła konfiguracja modułu \'Paymento\'');
        }

        foreach ($this->shops as $shopName => $shop) {
            if(!array_key_exists($this->configShopServiceId, $shop)){
                throw new InvalidConfigException("Zła konfiguracja sklepu '$shopName'. Brak parametru {$this->configShopServiceId}");
            }
            if(!array_key_exists($this->configShopServiceKey, $shop)){
                throw new InvalidConfigException("Zła konfiguracja sklepu '$shopName'. Brak parametru {$this->configShopServiceKey}");
            }
        }
    }

    /**
     * Generuje syganturę z danych przekazywanych do Paymento.
     *
     * @param \app\modules\paymento\models\BasePaymentoModel $paymentoModel
     * @param string $shop Nazwa sklepu. Yii::$app->params['payments']['paymento']['shops][<nazwa sklepu>]
     * @return string
     * @throws \Exception
     */
    public function generateSignature(BasePaymentoModel $paymentoModel, string $shop): string
    {
        $data = $paymentoModel->getPaymentData();

        $signature = $this->createSignature($data, $this->shops[$shop][$this->configShopServiceKey], self::HASH_METHOD) . ';' . self::HASH_METHOD;
        \Yii::debug([
            'MSG' => 'Dane do wyliczenia sygnatury',
            '$data' => $data,
            'sygnatura' => $signature,
            'Sklep' => $shop
        ], 'paymento');

        return $signature;
    }

    private function createSignature(array $data, string $shopKey, string $hashMethod): string
    {
        $data = self::prepareData($data);

        return hash($hashMethod, $data . $shopKey);
    }

    /*
     * Zwraca nazwę sklepu po jego ID
     */
    public function getShopName(string $shopId)
    {
        foreach ($this->shops as $shopName => $shopData) {
            if($shopData[$this->configShopServiceId] === $shopId){
                return $shopName;
            }
        }
        throw new \Exception('Nieznany sklep');
    }

    /**
     * Zwraca prywatny klucz sklepu.
     *
     * @param string $shopId Identyfikator sklepu
     * @return mixed
     * @throws \Exception
     */
    public function getShopServiceKey(string $shopId): string
    {
        foreach ($this->shops as $shopData) {
            if($shopData[$this->configShopServiceId] === $shopId){
                return $shopData[$this->configShopServiceKey];
            }
        }
        throw new \Exception('Nieznany sklep');
    }

    public function getMerchantId()
    {
        return $this->merchantId;
    }

    public function getServiceId(string $shop)
    {
        return $this->shops[$shop][$this->configShopServiceId];
    }

    public  static function prepareData($data, $prefix = ''): string {
        ksort($data);
        $hashData = [];
        foreach($data as $key => $value) {
            if($prefix) {
                $key = $prefix . '[' . $key . ']';
            }
            if(is_array($value)) {
                $hashData[] = self::prepareData($value, $key);
            } else {
                $hashData[] = $key . '=' . $value;
            }
        }

        return implode('&', $hashData);
    }
}
