# 🤖 IncogChat: Database-free Telegram Anonymous Messaging Bot

This project is a proof of concept for creating an anonymous messaging bot on Telegram that does not store any data or use any database. The bot operates by storing metadata (only telegram user ID) with encryption inside Telegram messages.

## 🔑 Key Features
1. **No Data Storage**: The bot does not store any data, not even a single bit. This ensures maximum privacy and security for the users.
1. **Metadata Encryption**: The bot encrypts the metadata (user ID) within the Telegram messages themselves, ensuring that the data is secure and private.
1. **Unique Private Links**: Each time a user asks for a private link, the link will be different. This is due to the encryption method used, which incorporates an Initialization Vector (IV) and a tag, resulting in a unique encryption output each time.
1. **Message Support**: The bot supports replying to messages and sending various types of messages, such as music, gifs, files, stickers, etc.

## ⚠️ Limitations Due to No Database Usage
While the absence of a database ensures maximum privacy and security, it also imposes certain limitations on the features that the bot can support. Specifically, the following features are not supported:

1. **Block Feature**: Without a database, the bot cannot keep track of which users have been blocked by others. Therefore, the block feature is not supported.
1. **Read Receipts**: The bot cannot inform users when their messages have been seen by the recipient. because the bot sends messages directly to the recipient without storing any data.

## 🔒 Encryption
The bot uses **AES-256-GCM** for encryption, utilizing a *tag* and an *initialization vector (IV)* for added security. the key is stored as an environment variable on the server.

## 🔄 How It Works
1. While creating a private link, the bot generates a link that contains encrypted user id, because of usage of tag and IV, each time the same user asks for a link, the link will be different.
1. When a user sends a message to the bot, the bot encrypts the user's ID and embeds it within the message.
1. The bot then sends the message to the recipient.
1. When the recipient replies, the bot decrypts the sender's ID from the message and sends the reply back to the original sender.

This way, the bot can facilitate anonymous messaging without storing any data or using any database.

## 🛡️ Trust and Security
The only point of trust in this project is to trust the bot maintainer that they are using this same source as the robot backend. We have made every effort to ensure the security and privacy of the users, but it's important to note that the bot maintainer has the responsibility to maintain this trust, and if the maintainer want, they can even log the messages and user IDs for their own purposes.

**In the worst-case scenario**, if the encryption key is leaked, the key holder can only access the user ID of people from whom they receive messages. If a user has not interacted with those user IDs on Telegram before, they cannot view their profiles. Access to profiles is only possible if the user IDs are part of a leaked Telegram database.

## ❗ Disclaimer
This is a proof of concept project. While every effort has been made to ensure the security and privacy of the users, use this bot at your own risk. The developers will not be held responsible for any misuse or breach of data.

Additionally, as the bot developer, **I will not run** this bot on any server. This project is intended for educational purposes and to demonstrate the potential for anonymous messaging without data storage or database usage.

## 📄 License
This project is open source under the [MIT license](/LICENSE).