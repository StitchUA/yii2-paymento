<?php


namespace stitchua\paymento\models;


use yii\db\ActiveRecord;
use stitchua\paymento\Paymento;

abstract class BasePaymentoModel extends ActiveRecord
{
    /** @var \stitchua\paymento\Paymento|null  */
    public $module;

    public function __construct(?Paymento $module = null, $config = [])
    {
        parent::__construct($config);
        $this->module = $module;
    }

    /**
     * @return array Zwraca dane kturę będą wysłane do Paymento i z których będzie generowana sygnatura
     */
    abstract public function getPaymentData():array;
}