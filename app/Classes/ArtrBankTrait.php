<?php


namespace App\Classes;

use BitWasp\Bitcoin\Crypto\EcAdapter\Key\PrivateKeyInterface;
use Illuminate\Support\Facades\Cache;

/**
 * Trait ArtrBankTrait
 *
 * Contains requests related to financial data
 *
 * @package App\Classes
 */
trait ArtrBankTrait
{
    /**
     * Request account account data from blockchain
     * @param $address string bech32 address
     * @return array Account data. Contains error => true, if some error has a place to be
     */
    public static function queryAccount($address)
    {
        return self::queryRPC('acc/account', ['Address' => $address]);
    }

    /**
     * @param $from string Sender address
     * @param $to string Recipient address
     * @param $amount int Uartrs to send
     * @param $key PrivateKeyInterface Key to sign transaction
     * @param $memo string Comment for payment
     * @param null $sequence Account Sequence
     * @param int $gas Gas Wanted
     * @return object node response
     */
    public static function sendMoney($from, $to, $amount, $key, $memo, $sequence, $gas = 0)
    {

        if ($gas == 0) {
            $txSimulate = [
                "base_req" => [
                    "from" => $from,
                    "memo" => $memo,
                    "chain_id" => config('artr.chain_id'),
                    "account_number" => strval(config('artr.bot_account_number')),
                    "sequence" => strval($sequence),
                    "gas" => "0",
                    "gas_adjustment" => "1.2",
                    "fees" => [],
                    "simulate" => true
                ],
                "amount" => [
                    [
                        "denom" => "uartr",
                        "amount" => strval($amount)
                    ]
                ]];

            $response = self::request('bank/accounts/' . $to . '/transfers', $txSimulate, 'POST');

            $gas = $response['gas_estimate'];
        }

        $tx = [
            "type" => "cosmos-sdk/StdTx",
            "value" => [
                "msg" => [
                    [
                        "type" => "cosmos-sdk/MsgSend",
                        "value" => [
                            "from_address" => $from,
                            "to_address" => $to,
                            "amount" => [[
                                "denom" => "uartr",
                                "amount" => strval($amount),
                            ]]
                        ]
                    ]
                ],
                "fee" => [
                    "amount" => [],
                    "gas" => strval($gas),
                ],
                "signatures" => null,
                "memo" => $memo
            ]
        ];

        $signTx = self::getSign($key, $tx, [
            'account_number' => config('artr.bot_account_number'),
            'chain_id' => config('artr.chain_id'),
            'sequence' => strval($sequence)
        ]);

        $response = self::request('txs', ['tx' => $signTx, 'mode' => 'sync']);
        return $response;
    }

    /**
     * Get prices for subscription / storage / VPN and ARTR course
     *
     * @return object prices response from blockchain
     */
    public
    static function getPrices()
    {
        return Cache::remember('blockchain_prices', 90, function () {

            $c = self::queryRPC('subscription/prices', []);

            // Try again, if node to busy to response
            if ($c['error']) {
                $c = self::queryRPC('subscription/prices', []);
            }

            return (object)($c['result']);
        });
    }

    /**
     * @return int Price of 1 USD cent in uartrs from blockchain
     */
    public
    static function getCourse()
    {
        return self::getPrices()->course;
    }

    /**
     * Get central bank course for 1 ARTR using rbc.ru as a source
     *
     * @return array prices for 1 ARTR in USDT / RUB / KZT / BYN / UAH
     */
    public
    static function getStaticCourse()
    {
        return Cache::remember('course_and_price_', 60,
            function () {
                $chainCourse = self::getCourse();

                $courses = Cache::remember('cb_courses_denom', 900, function () {
                    try {
                        $courses = file_get_contents(
                            'http://cbrates.rbc.ru/tsv/840/'
                            . now()->addHours(3)->format('Y/m/d') . '.tsv');
                        $courseUSD = (float)trim(explode("\t", $courses)[1]);
                        $courses = file_get_contents(
                            'http://cbrates.rbc.ru/tsv/933/'
                            . now()->addHours(3)->format('Y/m/d') . '.tsv');
                        $courseBYR = (float)trim(explode("\t", $courses)[1]);
                        $courses = file_get_contents(
                            'http://cbrates.rbc.ru/tsv/980/'
                            . now()->addHours(3)->format('Y/m/d') . '.tsv');
                        $courseUAH = (float)trim(explode("\t", $courses)[1]) / 10;
                        $courses = file_get_contents(
                            'http://cbrates.rbc.ru/tsv/398/'
                            . now()->addHours(3)->format('Y/m/d') . '.tsv');
                        $courseKZT = (float)trim(explode("\t", $courses)[1]) / 100;

                        return [
                            'usd' => $courseUSD,
                            'byr' => $courseBYR,
                            'uah' => $courseUAH,
                            'kzt' => $courseKZT
                        ];
                    } catch (\Throwable $ex) {
                        \Log::error('COURSE ERROR');
                        \Log::error($ex);
                    }
                });


                return [
                    'usd' => (int)($chainCourse * 100),
                    'rub' => round($chainCourse / $courses['usd'] * 100),
                    'usd-rub' => round($courses['usd'], 6),
                    'byr-rub' => round($courses['byr'] * 1.035, 6),
                    'uah-rub' => round($courses['uah'] * 1.035, 6),
                    'kzt-rub' => round($courses['kzt'] * 1.035, 6)
                ];
            });
    }

    /**
     * Get price as a string for orders (based on ARTR)
     * @param $artr int amount of ARTRS
     * @param false $noBraces don't put braces on the string
     * @return string formatted value
     */
    public
    static function getMultiCourseStringByArtr($artr, $noBraces = false)
    {
        $course = self::getStaticCourse();

        if ($noBraces) {
            return (round($artr / $course['usd'], 2)) . "$, "
                . (round($artr / $course['rub'], 2)) . "₽, "
                . (round($artr / $course['rub'] / $course['byr-rub'], 2)) . "Br, "
                . (round($artr / $course['rub'] / $course['uah-rub'], 2)) . "₴, "
                . (round($artr / $course['rub'] / $course['kzt-rub'], 0)) . "₸";
        }

        return (round($artr / $course['usd'], 2)) . "$ ("
            . (round($artr / $course['rub'], 2)) . "₽, "
            . (round($artr / $course['rub'] / $course['byr-rub'], 2)) . "Br, "
            . (round($artr / $course['rub'] / $course['uah-rub'], 2)) . "₴, "
            . (round($artr / $course['rub'] / $course['kzt-rub'], 0)) . "₸)";
    }

    /**
     * Get price as a string for orders (based on RUB)
     * @param $rub int amount in RUB
     * @param false $noBraces don't put braces on the string
     * @return string formatted value
     */
    public
    static function getMultiCourseStringByRub($rub, $noBraces = false)
    {
        $course = self::getStaticCourse();

        if ($noBraces) {
            return (round($rub / $course['usd-rub'], 2)) . "$, "
                . (round($rub, 2)) . "₽, "
                . (round($rub / $course['byr-rub'], 2)) . "Br, "
                . (round($rub / $course['uah-rub'], 2)) . "₴, "
                . (round($rub / $course['kzt-rub'], 0)) . "₸";
        }

        return (round($rub / $course['usd-rub'], 2)) . "$ ("
            . (round($rub, 2)) . "₽, "
            . (round($rub / $course['byr-rub'], 2)) . "Br, "
            . (round($rub / $course['uah-rub'], 2)) . "₴, "
            . (round($rub / $course['kzt-rub'], 0)) . "₸)";
    }

    /**
     * Format string to display amount in different currencies
     * @param $rub float
     * @param $usd float
     * @param $byn float
     * @param $uah float
     * @param $kzt float
     * @return string formated string
     */
    public
    static function getMultiCourseString($rub, $usd, $byn, $uah, $kzt)
    {
        return (round($usd, 2)) . "$ ("
            . (round($rub, 2)) . "₽, "
            . (round($byn, 2)) . "Br, "
            . (round($uah, 2)) . "₴, "
            . (round($kzt, 0)) . "₸)";
    }
}
