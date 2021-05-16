<?php


namespace stitchua\paymento\base;

/**
 * Interface zwracający dane użytkownika dokonującego płatność
 * @package stitchua\paymento\base
 */
interface PaymentoCustomerInterface
{
    public function getCustomerFirstName(): string;
    public function getCustomerLastName(): string;
    public function getCustomerEmail(): string;
}