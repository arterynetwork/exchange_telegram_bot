## About this toolkit

This toolkit is a start point for building exchange bots for 
the ARTR coin. It's written in Laravel, using [irazasyed/telegram-bot-sdk](https://github.com/irazasyed/telegram-bot-sdk) 
and also includes classes and traits, necessary to start communication with 
blockchain nodes REST ([Cosmos SDK REST](https://v1.cosmos.network/rpc/v0.39.2)) 

To launch your own bot, you need to do few steps:

1. Register a new bot using Telegram's [@botfather](https://telegram.me/botfather)
1. Create a new blockchain account using the CLI or the registration form on [our site](https://artery-network.io)
1. Configure necessary options in the `.env` file (look at the `.env.example` and `config/artr.php` files)
1. Run all migrations
1. Setup a webhook using an appropriate `telegram-bot-sdk` artisan command

That's it! You're online!

You can find more info in bot artisan command descriptions and PHP docs of `ArtrNode` class 

Special thanks to the [telegram-bot-sdk](https://github.com/irazasyed/telegram-bot-sdk) team for nice SDK and 
[Bit-Wasp](https://github.com/Bit-Wasp/bitcoin-php) for implementation of necessary BIP's in php.

Warning: Due to used libraries restrictions, this toolkit does not support 32-bit installation of PHP.
