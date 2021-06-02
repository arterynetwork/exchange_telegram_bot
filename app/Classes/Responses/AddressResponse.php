<?php


namespace App\Classes\Responses;


class AddressResponse extends BaseResponse
{
    /**
     * @var string found address in bech32 or empty string
     */
    public $address = '';
}
