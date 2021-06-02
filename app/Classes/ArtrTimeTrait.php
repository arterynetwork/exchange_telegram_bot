<?php


namespace App\Classes;

trait ArtrTimeTrait
{
    public static function getDayLength()
    {
        return 2880;
    }

    public static function getMonthLength()
    {
        return self::getDayLength() * 30;
    }
}
