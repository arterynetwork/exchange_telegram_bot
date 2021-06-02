<?php


namespace App\Classes;

use App\Classes\Responses\AddressResponse;
use App\Classes\Responses\ProfileResponse;

trait ArtrProfileTrait
{
    /**
     * Find profile data in blockchain by user's SDK address
     * @param $address string bech32 SDK address for profile to find
     * @return object Profile data from blockchain
     */
    public static function getProfile($address)
    {
        $profile = self::queryRPC('profile/profile', ['address' => $address]);

        if (!$profile['error']) {
            return (object)(['profile' => new ProfileResponse(['result' => $profile['result']['profile']])]);
        }

        return (object)[];
    }

    /**
     * Find SDK address for requested Nickname
     * @param $nick string Nickname
     * @return AddressResponse blockchain response
     */
    public static function resolveNick(string $nick): AddressResponse
    {
        $response = self::queryRPC('profile/query_account_address_by_nickname', ['nickname' => $nick]);
        return new AddressResponse($response);
    }

    /**
     * Find SDK address for requested card number
     * @param $cardNumber string ARTR-XXXX-XXXX-XXXX address
     * @return AddressResponse blockchain response
     */
    public static function resolveCardNumber(string $cardNumber): AddressResponse
    {
        $cardNumber = intval(preg_replace('/[^0-9]/', '', $cardNumber));
        $response = self::queryRPC('profile/query_account_address_by_card_number', ['card_number' => strval($cardNumber)]);
        return new AddressResponse($response);
    }
}
