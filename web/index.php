<?php

define('BOT_TOKEN', getenv('BOT_TOKEN'));
define("BOT_URL", getenv('BOT_URL'));
define("BOT_ID", getenv('BOT_ID'));

define("ENC_CIPHER", "aes-256-gcm");
define("ENC_PASSPHRASE", getenv('ENC_PASSPHRASE')); // openssl rand -hex 32


// Get the incoming message from Telegram
$update = file_get_contents('php://input');
$update = json_decode($update, true);

// Process the incoming message
if (isset($update['message'])) {
    $rspMessage = processMessage($update['message']);

    // Send the reply message to the user
    if ($rspMessage !== '') {
        sendMessage($update['message']['chat']['id'], $rspMessage, $update['message']['message_id']);
    }
}


function processMessage(array $message): string
{
    // Start message
    if ($message['text'] == '/start') {
        return getMessage('welcome');
    }

    // Create a link for the user
    if ($message['text'] == '/link') {
        $encryptedUserId = encrypt($message['from']['id']);
        $url = BOT_URL . '?text=/msg' . $encryptedUserId;
        return "Your url is: <code>$url</code>";
    }

    // Check if the message is a command to send a message to a user
    if (strpos($message['text'], '/msg') === 0) {
        $encryptedUserId = str_replace('/msg', '', $message['text']);

        // check if the user id is valid
        $plainUserId = decrypt($encryptedUserId);

        if (!$plainUserId) {
            return getMessage('invalid_url');
        }

        // send the message to user, with encrypted user id inside it
        $metadata = createEncryptedMetadata($plainUserId);
        return getMessage('send', [':metadata' => $metadata]);
    }

    // check if the message is a reply to another message from the bot
    if (isset($message['reply_to_message']) && $message['reply_to_message']['from']['id'] === (int) BOT_ID) {
        $replyMessage = $message['reply_to_message'];

        // chek if text is set
        if (!isset($replyMessage["text"])) {
            return '';
        }

        // Sending new message
        if (stripos($replyMessage["text"], "#send") !== false) {
            $encryptedUserId = explode(BOT_URL . '/', $replyMessage["entities"][2]['url'])[1];

            $metadata = createEncryptedMetadata($message['chat']['id'], $message['message_id']);

            $targetPlainUserId = decrypt($encryptedUserId);
            $msgId = sendMessage($targetPlainUserId, getMessage('new_message', [':metadata' => $metadata]));
            $isDelivered = copyMessage($targetPlainUserId, $message['chat']['id'], $message['message_id'], $msgId);

            if ($isDelivered) {
                reactToMessage($message['chat']['id'], $message['message_id'], '👍');
            } else {
                return getMessage('not_delivered');
            }
        }

        // Replying to a message
        else if (stripos($replyMessage["text"], "#new") !== false) {
            $encryptedUserId = explode('/', explode(BOT_URL . '/', $replyMessage["entities"][1]['url'])[1])[0];
            $messageIdToReply = explode($encryptedUserId . '/', $replyMessage["entities"][1]['url'])[1];

            $metadata = createEncryptedMetadata($message['chat']['id'], $message['message_id']);

            $targetPlainUserId = decrypt($encryptedUserId);
            $msgId = sendMessage($targetPlainUserId, getMessage('new_reply', [':metadata' => $metadata]), $messageIdToReply);
            $isDelivered = copyMessage($targetPlainUserId, $message['chat']['id'], $message['message_id'], $msgId);

            if ($isDelivered) {
                reactToMessage($message['chat']['id'], $message['message_id'], '👍');
            } else {
                return getMessage('not_delivered');
            }
        }
    }

    return '';
}

// Encrypt and create encrypt metadata for the message
function createEncryptedMetadata($plainUserId, $messageId = null): string
{
    $dummyUrl = BOT_URL . '/' . encrypt($plainUserId);

    if ($messageId) {
        $dummyUrl .= '/' . $messageId;
    }

    return $dummyUrl;
}


//                          >ENCRYPTION FUNCTIONS
function encrypt($plaintext)
{
    $secret_key = hex2bin(ENC_PASSPHRASE);
    $iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length(ENC_CIPHER));
    $tag = '';
    $ciphertext = openssl_encrypt($plaintext, ENC_CIPHER, $secret_key, 0, $iv, $tag);

    return base64url_encode($iv . $tag . $ciphertext);
}

function decrypt($ciphertext)
{
    $secret_key = hex2bin(ENC_PASSPHRASE);
    $ciphertext = base64url_decode($ciphertext);
    $ivlen = openssl_cipher_iv_length(ENC_CIPHER);
    $iv = substr($ciphertext, 0, $ivlen);
    $tag = substr($ciphertext, $ivlen, 16);
    $ciphertext = substr($ciphertext, $ivlen + 16);

    return openssl_decrypt($ciphertext, ENC_CIPHER, $secret_key, 0, $iv, $tag);
}


//                          >TELEGRAM API FUNCTIONS
function sendMessage($chatId, $message, $replyToMessageId = null)
{
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
        'link_preview_options' => [
            'is_disabled' => true,
        ]
    ];

    if ($replyToMessageId) {
        $data['reply_to_message_id'] = $replyToMessageId;
        $data['allow_sending_without_reply'] = true;
    }

    $result = sendRequest($data, 'sendMessage');

    $rsp = json_decode($result, true);
    if ($rsp['ok'] ?? false) {
        return $rsp['result']['message_id'];
    }

    return null;
}

// forward ananymously all type of messages and files to the user
function copyMessage($chatId, $fromChatId, $messageId, $replyTo = null): bool
{
    $data = [
        'chat_id' => $chatId,
        'from_chat_id' => $fromChatId,
        'message_id' => $messageId,
    ];

    if ($replyTo) {
        $data['reply_parameters'] = [
            'message_id' => $replyTo,
            'allow_sending_without_reply' => true,
        ];
    }

    $result = sendRequest($data, 'copyMessage');

    $rsp = json_decode($result, true);

    return $rsp['ok'] ?? false;
}

function reactToMessage($chatId, $messageId, $emoji)
{
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'reaction' => [
            [
                'type' => 'emoji',
                'emoji' => $emoji,
            ]
        ],
        'is_big' => true,
    ];

    return sendRequest($data, 'setMessageReaction');
}

function sendRequest($data, $method)
{
    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/{$method}");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

//                         >>OTHER UTILITIY FUNCTIONS

// https://github.com/firebase/php-jwt/blob/e9690f56c0bf9cd670655add889b4e243e3ac576/src/JWT.php#L450C17-L450C82
function base64url_encode($string)
{
    return str_replace('=', '', strtr(base64_encode($string), '+/', '-_'));
}

// https://github.com/firebase/php-jwt/blob/e9690f56c0bf9cd670655add889b4e243e3ac576/src/JWT.php#L418
function base64url_decode($string)
{
    $remainder = strlen($string) % 4;
    if ($remainder) {
        $padlen = 4 - $remainder;
        $string .= str_repeat('=', $padlen);
    }
    return base64_decode(strtr($string, '-_', '+/'));
}


//                  >> messages section


function getMessage(string $key, array $params = []): string
{
    $messages = [
        'welcome' => "👋 Welcome! To generate a link, simply type the /link command.\n\n🔄 If you've arrived here via a link and it's your first time starting the bot, please click the link once more and send the written message. 📩",
        'send' => "📝 To message the user, please <b>reply directly to THIS message</b> with your text/file/sticker/gif. Your message will be sent anonymously! 🕵️‍♂️💬\n\n#send<a href=':metadata'>⁪</a>",
        'new_message' => "📬 You've received a #new message!<a href=':metadata'>⁪</a> To reply, just <b>reply to THIS message</b> with your text/file/sticker/gif.",
        'new_reply' => "🔔 #New reply received!<a href=':metadata'>⁪</a> To continue the conversation, just <b>reply to THIS message</b> with your text/file/sticker/gif.",
        'invalid_url' => '⚠️ The URL you entered is invalid. Please check and try again!',
        'not_delivered' => "❗ Your message couldn't be delivered. There might be a temporary problem, or the user may have blocked the bot. Please try again later.",
    ];

    return strtr($messages[$key], $params);
}