<?php

namespace stitchua\paymento\models;

use app\models\Account;
use app\models\Invoice;
use stitchua\paymento\Paymento;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * Klasa do obsługi bramki płatności Paymento.
 *
 * @property int $fld_id
 * @property int $orderId
 * @property int $amount
 * @property string|null $currency
 * @property string|null $customerFirstName
 * @property string|null $customerLastName
 * @property string|null $customerEmail
 * @property string|null $customerCid
 * @property string|null $customerIsPep
 * @property string|null $customerPhone
 * @property string|null $customerCompany
 * @property string|null $title
 * @property string|null $activeTo
 * @property string|null $returnUrl
 * @property string|null $failureReturnUrl
 * @property string|null $successReturnUrl
 * @property string|null $visibleMethod
 * @property string|null $signature
 * @property string|null $created_at
 * @property string|null $updated_at
 *
 * @package app\modules\paymento\models
 *
 * @version 1.0.3 2020-12-21
 * @see https://paymentopaywall.docs.apiary.io/#/introduction/wprowadzenie/wersja-dokumentu
 */
class Paywall extends BasePaymentoModel
{
    const METHOD_CARD = 'card';
    const METHOD_PBL = 'pbl';
    const DEFAULT_CURRENCY = 'PLN';

    public $apiUri = 'https://paywall.paymento.pl/pl/payment';

    public $shop;

    public $sendedAttributes = [
        'amount',
        'currency',
        'orderId',
        'customer',
        'customerFirstName',
        'customerLastName',
        'customerEmail',
        'customerCid',
        'customerIsPep',
        'customerPhone',
        'customerCompany',
        'title',
        'activeTo',
        'returnUrl',
        'failureReturnUrl',
        'successReturnUrl',
        'visibleMethod',
    ];

    public function __construct(?Paymento $module = null, $config = [])
    {
        parent::__construct($module, $config);

        $this->shop = Paymento::S7HEALTH_SHOP;
    }

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%paymento_paywall}}';
    }

    public function rules()
    {
        return [
            ['currency', 'default', 'value' => self::DEFAULT_CURRENCY],
            [['amount', 'currency', 'orderId', 'customerFirstName','customerLastName', 'customerEmail'], 'required'],
            [['orderId', 'amount'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['currency', 'customerFirstName', 'customerLastName', 'customerEmail', 'customerCid', 'customerIsPep',
                'customerPhone', 'customerCompany', 'title', 'activeTo', 'returnUrl', 'failureReturnUrl',
                'successReturnUrl', 'visibleMethod', 'signature'], 'string', 'max' => 255],
            ['customerEmail', 'email'],
            [['failureReturnUrl', 'successReturnUrl'], 'url'],
            ['customerPhone', 'match', 'pattern' => '/^[-+0-9]{6,20}$/i', 'skipOnEmpty' => true]
        ];
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => 'updated_at',
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function beforeSave($insert)
    {
        if(!parent::beforeSave($insert)){
            return false;
        }
        if($insert){
            $this->generateSignature();
        }

        return true;
    }

    /**
     * @param string $shopKey Ustawia identyfikator sklepu.
     * Identyfikatory sklepów są w Yii::$app->params['payments']['paymento']['shops']
     */
    public function setShop(string $shopKey){
        $this->shop = $shopKey;
    }

    public function getPaymentData(): array
    {
        $data = [];
        $data['merchantId'] = $this->module->getMerchantId();
        $data['serviceId'] = $this->module->getServiceId($this->shop);

        foreach ($this->attributes as $attrName => $attribute) {
            if(in_array($attrName, $this->sendedAttributes)) {
                if (!empty($this->$attrName)) {
                    if (preg_match('/^customer/', $attrName, $matches)) {
                        if ($attrName == 'customer') {
                            $data['customer'] = [];
                        } else {
                            $customerAttribute = lcfirst(ltrim($attrName, 'customer'));
                            $data['customer'][$customerAttribute] = $attribute;
                        }

                    } else {
                        $data[$attrName] = $attribute;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * @param \app\models\Invoice $invoice
     * @param \app\models\Account $account
     * @param $amount float Kwota w złotówkach z groszikami 23.54 zł
     */
    public function setData(Invoice $invoice, Account $account, $amount)
    {
        $this->amount = ($amount * 100);
        $this->orderId = $invoice->fld_id;
        $this->title = $invoice->fld_number;
        $this->customerFirstName = $account->fld_first_name;
        $this->customerLastName = $account->fld_last_name;
        $this->customerEmail = $account->fld_email;
    }

    public function generateSignature()
    {
        if(!$this->signature){
            $this->signature = $this->module->generateSignature($this, $this->shop);
        }
        return $this->signature;
    }

    /**
     * Opłata kartą.
     */
    public function payByCard()
    {
        $this->visibleMethod = self::METHOD_CARD;
    }

    /**
     * Opłata przez wybór banku.
     */
    public function payByBank()
    {
        $this->visibleMethod = self::METHOD_PBL;
    }
}