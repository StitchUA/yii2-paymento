<?php

namespace stitchua\paymento\models;

use app\models\externalPayments\ExternalPaymentSettleInterface;
use app\models\Invoice;
use stitchua\paymento\Paymento;
use Yii;
use yii\behaviors\TimestampBehavior;
use yii\db\Expression;

/**
 * Klasa do obsługi odpowiedzi z Paymento
 *
 * @property int $fld_id
 * @property string $id identyfikator zamówienia przypisany przez system Paymento
 * @property string $type  w zależności od transakcji, wartość sale występuje przy sprzedaży, refun dla zwrotu
 * @property string $status dla płatności zaakceptowanej status brzmi settled, dla odrzuconej rejected
 * @property string $source dla tego parametru wartością może być `api` lub `web`
 * @property int|null $created
 * @property int|null $modified
 * @property string|null $notificationUrl
 * @property string $serviceId unikalny identyfikator sklepu w którym została wykonana transakcja
 * @property int $amount wartość zamówienia podana w najmniejszej jednostce pieniężnej waluty
 * @property string|null $currency
 * @property string $title tytuł płatności (przysłany ze sklepu)
 * @property string $orderId numer zamówienia (przysłany ze sklepu)
 * @property string|null $paymentMethod
 * @property string|null $paymentMethodCode
 * @property string|null $created_at
 *
 * @property \app\modules\paymento\models\Paywall $paywall Dane przekazane do Paymento
 * @property bool $isSale Tranzakcja jest trazakcją sprzedaży
 * @property bool $isSettled Tranzakcja zaakceptowana przez Paymento
 */
class PaymentoTransaction extends BasePaymentoModel implements ExternalPaymentSettleInterface
{
    public const HEADER_SIGNATURE_NAME = 'x-paymento-signature';

    /** @var string Tranzakcja zaakceptowana */
    public const STATUS_SETTLED = 'settled';
    /** @var string Tranzakcja odrzucona */
    public const STATUS_REJECTED = 'rejected';
    /** @var string Tranzakcja oczekuje na opłacanie klientem */
    public const STATUS_PENDING = 'pending';
    /** @var string Tranzakcja nowa */
    public const STATUS_NEW = 'new';

    /** @var string Występuję przy sprzedaży */
    public const TYPE_SALE = 'sale';
    /** @var string Występuję przy zwrocie */
    public const TYPE_REFUN = 'refun';

    public const SOURCE_API = 'api';
    public const SOURCE_WEB = 'web';

    /**
     * @var \yii\web\HeaderCollection|null Nagłówki requstu Yii::$app->request->headers
     */
    private $headers;
    /**
     * @var array|null Tablica z danymi przekazanymi w nagłówku self::HEADER_SIGNATURE_NAME
     * [
     *      merchantid => "identyfikator klienta w Paymento",
     *      serviceid => "identyfikator sklepu w Paymento",
     *      signature => "podpis notyfikacji",
     *      alg => "algorytm funkcji skrótu (możliwe wartości: sha256)."
     * ]
     */
    private $recievedSignature;

    private $orderClass = Invoice::class;
    /**
     * @var string Sygnatura wyliczona z body tranzakcji, powinna odpowiadać przekazanej w nagłówkach
     */
    private $transactionSignature;

    private $transactionFields = [
        'id',
        'type',
        'status',
        'source',
        'created',
        'modified',
        'notificationUrl',
        'serviceId',
        'amount',
        'currency',
        'title',
        'orderId',
        'paymentMethod',
        'paymentMethodCode'
    ];

    /**
     * {@inheritdoc}
     */
    public static function tableName()
    {
        return '{{%paymento_transaction}}';
    }

    /**
     * {@inheritdoc}
     */
    public function rules()
    {
        return [
            [['id', 'type', 'status', 'source', 'serviceId', 'amount', 'title', 'orderId'], 'required'],
            [['created', 'modified', 'amount'], 'integer'],
            [['created_at'], 'safe'],
            [['id', 'type', 'status', 'source', 'notificationUrl', 'serviceId', 'currency', 'title', 'orderId', 'paymentMethod', 'paymentMethodCode'], 'string', 'max' => 255],
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function attributeLabels()
    {
        return [
            'fld_id' => 'Fld ID',
            'id' => 'ID',
            'type' => 'Type',
            'status' => 'Status',
            'source' => 'Source',
            'created' => 'Created',
            'modified' => 'Modified',
            'notificationUrl' => 'Notification Url',
            'serviceId' => 'Service ID',
            'amount' => 'Amount',
            'currency' => 'Currency',
            'title' => 'Title',
            'orderId' => 'Order ID',
            'paymentMethod' => 'Payment Method',
            'paymentMethodCode' => 'Payment Method Code',
            'created_at' => 'Created At',
        ];
    }

    public function beforeSave($insert)
    {
        if(!parent::beforeSave($insert)){
            return false;
        }

        if($insert && !empty($this->headers)){
            $this->headerMerchantid = $this->headers['merchantid'];
            $this->headerServiceid = $this->headers['serviceid'];
            $this->headerSignature = $this->headers['signature'];
            $this->headerAlg = $this->headers['alg'];
        }

        return true;
    }

    public function behaviors()
    {
        return [
            [
                'class' => TimestampBehavior::class,
                'createdAtAttribute' => 'created_at',
                'updatedAtAttribute' => false,
                'value' => new Expression('NOW()'),
            ],
        ];
    }

    public function getPaywall()
    {
        return $this->hasOne(Paywall::class, ['orderId' => 'orderId']);
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Waliduje sygnaturę przekazaną z Paymento.
     *
     * @param \app\modules\paymento\Paymento $module
     * @return bool
     */
    public function validateSignature(Paymento $module)
    {
        if(!$this->headers){
            // Błąd brak ustawionych nagłówków
            Yii::error([
                'MSG' => 'Brak ustawionych nagłówków',
                'Przekazane nagłówki' => Yii::$app->request->headers->toArray(),
                'Tranzakcja' => $this->attributes
            ], 'paymento');
            return false;
        }
        if(!$this->orderId){
            // Błąd brak ID faktury
            Yii::error([
                'MSG' => 'Brak ustawienia wymaganego parametru \'orderId\'',
                'Tranzakcja' => $this->attributes
            ], 'paymento');
            return false;
        }
        $signature = $this->headers->get(self::HEADER_SIGNATURE_NAME);
        if(!$signature){
            // error Brak przekazanego nagłówku
            Yii::error([
                'MSG' => 'Brak przekazanej sygnatury płatności od Paymento',
                'Przekazane nagłówki' => $this->headers->toArray(),
                'Tranzakcja' => $this->attributes
            ], 'paymento');
            return false;
        }
        $this->parseHeaderSignature($signature);
        $this->generateSignature();
        /** @var \app\modules\paymento\models\Paywall $paywall */
        $paywall = $this->paywall;
        if(!$paywall){
            Yii::error([
                'MSG' => 'Nie odnaliziono dane płatności przekazane do Paymento',
                'Przekazana sygnatura z Paymento w nagłówku' => $this->recievedSignature,
                'Wyliczona sygnatura z body tranzakcji' => $this->transactionSignature,
                'PaymentoTransaction' => $this->attributes
            ], 'paymento');
            return false;
        }
        if ($this->transactionSignature !== $this->recievedSignature['signature']) {
            // Notyfikacja zweryfikowana negatywnie. Ignoruj notyfikację.
            Yii::error([
                'MSG' => 'Weryfikacja sygnatur się nie powiodła',
                'rawBody' => Yii::$app->request->rawBody,
                'Przekazana sygnatura z Paymento' => $this->recievedSignature,
                'Sygnatura wyliczona z tranzakcji' => $this->transactionSignature,
                'Sygnatura wyliczona z faktury' => $paywall->signature,
                'PaymentoTransaction' => $this->attributes,
                '$paywall->attributes' => $paywall->attributes
            ], 'paymento');

            return false;
        }

        return true;
    }

    public function getPaymentData(): array
    {
        $data = [];
        foreach ($this->attributes as $attrName => $attribute) {
            if(in_array($attrName, $this->transactionFields)) {
                if (!empty($this->$attrName)) {
                   $data[$attrName] = $attribute;
                }
            }
        }

        return $data;
    }

    public function generateSignature()
    {
        if(!$this->transactionSignature){
            $this->transactionSignature = hash(Paymento::HASH_METHOD, Yii::$app->request->rawBody.$this->module->getShopServiceKey($this->serviceId));
        }
        return $this->transactionSignature;
    }

    /**
     * Parsuje wartość przekazaną w nagłówku self::HEADER_SIGNATURE_NAME.
     *
     * @param string $headerSignature String przekazany w nagłówku self::HEADER_SIGNATURE_NAME
     * @return array Dane sygnatury
     * [
     *      merchantid => "identyfikator klienta w Paymento",
     *      serviceid => "identyfikator sklepu w Paymento",
     *      signature => "podpis notyfikacji",
     *      alg => "algorytm funkcji skrótu (możliwe wartości: sha256)."
     * ]
     */
    private function parseHeaderSignature(string $headerSignature)
    {
        $signatureAttributes = [];
        $signatureParts = explode(';', $headerSignature);
        foreach ($signatureParts as $part) {
            $tmp = explode('=', $part);
            $signatureAttributes[$tmp[0]] = $tmp[1];
        }
        $this->recievedSignature = $signatureAttributes;

        return $this->recievedSignature;
    }

    /**
     * @return bool Tranzakcja sprzedaży
     */
    public function getIsSale(){
        return $this->type === self::TYPE_SALE;
    }

    /**
     * @return bool Tranzakcja była zaakceptowana przez Paymento
     */
    public function getIsSettled(){
        return $this->status === self::STATUS_SETTLED;
    }

    public function getTransactionId(): string
    {
        return $this->id;
    }

    public function getOrderId(): int
    {
        return (int)$this->orderId;
    }

    public function getAmount(): float
    {
        return $this->amount / 100;
    }

    public function canBeSettled(): bool
    {
        return $this->isSale && $this->isSettled;
    }
}
