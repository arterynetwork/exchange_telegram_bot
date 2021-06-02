<?php


namespace App\Classes\Responses;


class BaseResponse
{
    public function __construct($response)
    {
        foreach ($response['result'] as $key => $value) {
            $this->{$key} = $value;
        }
    }
}
