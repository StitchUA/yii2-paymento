<?php
namespace stitchua\paymento\base;

interface PaymentoPayloadRequestDataInterface extends PaymentoCustomerInterface
{
    /**
     * @return int Kwota do zapłaty w groszach
     */
    public function getAmount(): int;
    /** @return int Wartość primary key objektu sprzedaży */
    public function getId(): int;

    /**
     * @return string Tytuł płatności
     */
    public function getTitle(): string;
}