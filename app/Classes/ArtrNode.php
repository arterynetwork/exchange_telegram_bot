<?php

namespace App\Classes;

use BitWasp\Bitcoin\Crypto\EcAdapter\Signature\CompactSignatureInterface;
use BitWasp\Bitcoin\Key\Factory\HierarchicalKeyFactory;
use BitWasp\Bitcoin\Mnemonic\Bip39\Bip39SeedGenerator;
use BitWasp\Buffertools\Buffer;
use function BitWasp\Bech32\convertBits;
use function BitWasp\Bech32\encode;

class ArtrNode
{
    use ArtrBlockTrait;
    use ArtrProfileTrait;
    use ArtrBankTrait;
    use ArtrTimeTrait;

    public static function getWallet($mnemonic)
    {
        $bip39 = new Bip39SeedGenerator();
        $seed = $bip39->getSeed($mnemonic);
        $factory = new HierarchicalKeyFactory();
        $root = $factory->fromEntropy($seed);
        $key = $root->derivePath("44'/546'/0'/0/0");
        $sha256 = hash('sha256', $key->getPublicKey()->getBinary(), true);
        $ripemd160 = hash('ripemd160', $sha256, true);
        $data = array_values(unpack('C*', $ripemd160));
        $data = convertBits($data, count($data), 8, 5, true);
        $data = encode('artr', $data);
        return ['privateKey' => $key->getPrivateKey(), 'publicKey' => $key->getPublicKey(), 'address' => $data];
    }

    public static function createSignMsg($tx, $meta)
    {
        return [
            'account_number' => strval($meta['account_number']),
            'chain_id' => strval($meta['chain_id']),
            'fee' => $tx['fee'],
            'memo' => $tx['memo'],
            'msgs' => $tx['msg'],
            'sequence' => strval($meta['sequence']),
        ];
    }

    public static function canonize($tx)
    {
        if (is_array($tx)) {
            ksort($tx);
            foreach ($tx as $key => $val) {
                $tx[$key] = self::canonize($val);
            }
        }

        return $tx;
    }

    public static function getSign($key, $tx, $meta)
    {
        $signMessage = self::createSignMsg($tx['value'], $meta);
        $signMessage = self::canonize($signMessage);
        $jsonString = json_encode($signMessage, JSON_UNESCAPED_SLASHES);
        $hashBuf = new Buffer(hash('sha256', $jsonString, true));

        /** @var CompactSignatureInterface $sign */
        $sign = $key->signCompact($hashBuf);
        $response = $tx['value'];
        $response['signatures'] = [[
            'signature' => base64_encode(substr($sign->getBuffer()->getBinary(), 1)),
            'pub_key' => [
                'type' => 'tendermint/PubKeySecp256k1',
                'value' => base64_encode($key->getPublicKey()->getBinary())
            ]
        ]];

        return $response;
    }

    public static function filterAddress($address)
    {
        return $address;
    }

    public static function request($url, $body, $method = 'POST', $base = '')
    {
        $client = new \GuzzleHttp\Client();

        if (!$base) {
            $base = config('artr.rest_url');
        }

        if ($method == 'POST') {
            $response = $client->post($base . $url, ['json' => $body]);
        } else {
            $response = $client->get($base . $url);
        }

        if ($response->getStatusCode() == 200) {
            return json_decode($response->getBody(), true);
        }

        return false;
    }

    public static function queryREST($url)
    {
        return self::request($url, null, 'GET', config('artr.rest_url'));
    }

    public static function queryNode($url)
    {
        return self::request($url, null, 'GET', config('artr.node_url'));
    }

    public static function queryRPC($path, $data, $host = null)
    {
        $params = [
            "method" => "abci_query",
            "jsonrpc" => "2.0",
            "id" => 0,
            "params" => [
                "path" => "custom/" . $path,
                "data" => bin2hex(json_encode($data)),
                "height" => "0",
                "prove" => false,
            ]
        ];
        $response = ArtrNode::request('', $params, 'POST', config('artr.node_url'));

        if (isset($response['result']['response']['value'])) {
            return [
                'error' => false,
                'height' => $response['result']['response']['height'],
                'result' => json_decode(base64_decode($response['result']['response']['value']), JSON_OBJECT_AS_ARRAY),
            ];
        }

        try {
            $error = $response['result']['response'];
            $error['value'] = base64_decode($error['value']);

            return [
                'error' => true,
                'height' => $response['result']['response']['height'],
                'response' => $error
            ];
        } catch (\Throwable $er) {
            return [
                'error' => true,
                'internal' => true,
                'response' => $er
            ];
        }
    }

    public static function getDenom($coins, $denom)
    {
        foreach ($coins as $coin) {
            if (((array)$coin)['denom'] == $denom) {
                return self::formatAmountEx(((array)$coin)['amount'] / 1000000);
            }
        }
    }

    public static function getCoins($account, $denom = false)
    {
        if (is_string($account)) {
            $acc = self::queryAccount($account);
        } else {
            $acc = $account;
        }

        $acc = $acc['result']['value']['coins'];

        if (!$denom) {
            $denom = config('artr.denom');
        }

        return self::getDenom($acc, $denom);
    }

    public static function formatArteryAddress($cardNumber)
    {
        return 'ARTR-' . implode('-', str_split($cardNumber . '', 4));
    }

    public static function formatAmount($amount)
    {
        return round($amount / 1000000, 6);
    }

    public static function formatAmountEx($amount)
    {
        return sprintf('%f', round(((float)$amount) / 1000000, 6));
    }

    public static function getInComission($amount)
    {
        if ($amount * 0.003 > 10000000) {
            return 10000000;
        }

        return round($amount / 1.003 * 0.003);
    }

    public static function getSequenceNumber($account)
    {
        return self::queryAccount($account)['result']['value']['sequence'] ?? '';
    }
}
