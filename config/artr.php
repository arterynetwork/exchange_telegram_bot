<?php
return [
    // REST node url
    'rest_url' => env('ARTR_REST_URL', 'http://165.232.110.137:1317/'),
    // RPC node url
    'node_url' => env('ARTR_NODE_URL', 'http://165.232.110.137:26657/'),
    // Denom of main wallet
    'denom' => env('ARTR_COIN_DENOM', 'uartr'),
    // Fee collectors account (from Artery Blockchain)
    'fee_collector' => env('ARTR_FEE_COLLECTOR', 'artr17xpfvakm2amg962yls6f84z3kell8c5l25s8e7'),
    // Supported locales
    'locales' => ['ru'],
    // Bot wallet address in ARTR-XXXX-XXXX-XXXX format
    'bot_wallet' => env('BOT_ARTR_ADDRESS', ''),
    // Bot bech32 address
    'bot_address' => env('BOT_SDK_ADDRESS', ''),
    // Bot account number
    'bot_account_number' => env('BOT_ACCOUNT_NUMBER', 0),
    // Bot mnemonic - to crete key
    'bot_mnemonic' => env('BOT_MNEMONIC', ''),
    // Min price of ARTR (changes at runtime)
    'min_price' => env('ARTR_MIN_PRICE', 0),
    // Max price of ARTR (changes at runtime)
    'max_price' => env('ARTR_MAX_PRICE', 10),
    // Minimum markup relative to the blockchain rate
    'min_percent' => env('ARTR_MIN_PERCENT', 0),
    // Minimal markup relative to the blockchain rate
    'max_percent' => env('ARTR_MAX_PERCENT', 5),
    // Block to start transactions loading
    'start_block' => env('START_BLOCK', 1),
    // Current chain id
    'chain_id' => env("ARTR_CHAIN_ID", 'artery_network-5')
];
