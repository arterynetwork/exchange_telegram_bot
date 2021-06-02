<?php


namespace App\Classes\Responses;


class ProfileResponse extends BaseResponse
{
    /**
     * @var string Users nickname
     */
    public $nickname;
    /**
     * @var string Users ARTR address
     */
    public $card_number;
}
