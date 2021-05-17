<?php

namespace stitchua\paymento;

use stitchua\paymento\models\BasePaymentoModel;
use yii\base\InvalidConfigException;
use yii\base\Module;

/**
 * Klasa do obsługi płatnosci systemu 'Paymento'
 *
 * Konfiguracja w pliku config/web.php sekcja 'modules'
 * ```
 * 'paymento' => [
 *      'class' => 'stitchua\paymento\Paymento',
 *      //ID klienta Identyfikator klienta w Paymento
 *      'merchantId' => '',
 *      'payloadModelClass' => 'app\models\Invoice', // Klasa zamówienia, która realizuje interface [[stitchua\paymento\base\PaymentoPayloadRequestDataInterface]]
 *      'successReturnUrl' => 'https://mysite.com/site/payment-landig-page?status=success',
 *      'failureReturnUrl' => 'https://mysite.com/site/payment-landig-page?status=error'
 *      'shops' => [
 *          // nazwa sklepu w crm
 *          'myShop1' => [
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
 * php yii migrate --migrationPath="@paymento/migrations"
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
    /**
     * @var string|null Bezwzgledna nazwa klasy która bedzie wykorzystywana jako źródło danych do płatności.
     *  Dana klasa ma implementować interface PaymentoPayloadRequestDataInteface
     */
    public $payloadModelClass = null;

    /** @var string Nazwa sklepu ustawiana w config/web.php konfiguracji modułu */
    public const MYSHOP1_SHOP = 'myShop1';

    public const HASH_METHOD = 'sha256';

    /**
     * {@inheritdoc}
     */
    public $controllerNamespace = 'stitchua\paymento\controllers';
    /**
     * @var string Bezwzględny url na który będzie przekierowany użytkownik po udano dokonanej płatności.
     * Np. \Yii::$app->urlManager->createAbsoluteUrl(['/site/paymentlandingpage', 'result' => 'success'], 'https')
     */
    public $successReturnUrl = '';
    /**
     * @var string Bezwzględny url na który będzie przekierowany użytkownik po nie udanej płatności.
     * Np. \Yii::$app->urlManager->createAbsoluteUrl(['/site/paymentlandingpage', 'result' => 'error'], 'https')
     */
    public $failureReturnUrl = '';

    /**
     * {@inheritdoc}
     */
    public function init()
    {
        parent::init();

        if(empty($this->merchantId) || !is_array($this->shops) || empty($this->shops) || empty($this->payloadModelClass)){
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
     * @param \stitchua\paymento\models\BasePaymentoModel $paymentoModel
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
