<?php


namespace stitchua\paymento\base;


use stitchua\paymento\models\PaymentoTransaction;

/**
 * Interface do dodatkowych czynności które ma wykonać objekt zamówienia
 * po swej opłacie.
 *
 * @package stitchua\paymento\base
 */
interface PaymentoResponseDataInterface
{
    /**
     * Informuje objek zamówienia o dokonanej płatności.
     *
     * @param \stitchua\paymento\models\PaymentoTransaction $transaction Odpowiedź z systemu Paymento
     * @return mixed
     */
    public function paymentoOrderInvoiced(PaymentoTransaction $transaction);
}