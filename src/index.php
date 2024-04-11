<?php
$env = parse_ini_file('.env');

define('BOT_TOKEN', $env['BOT_TOKEN']);
define("BOT_URL", $env['BOT_URL']);
define("BOT_ID", $env['BOT_ID']);

define("ENC_CIPHER", "aes-256-gcm");
define("ENC_PASSPHRASE", $env['KEY']);


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
    // if message is '/start' then ask for user nick name to create a url for them
    if ($message['text'] == '/start') {
        return "Hello, to get a link please use /link command. if you already here from a link and this is the first time you starting the bot, please open the link again and send the message.";
    }

    if ($message['text'] == '/link') {
        $encryptedUserId = encrypt($message['from']['id']);
        $url = BOT_URL . '?text=/msg' . $encryptedUserId;
        return "Your url is: <code>$url</code>";
    }

    // check if message is '/msg{EncryptedUserId}'}'
    if (strpos($message['text'], '/msg') === 0) {
        $encryptedUserId = str_replace('/msg', '', $message['text']);

        // send the message to user, with encrypted user id inside it, and tell them to send the message to the user they sould reply to this message
        $dummyUrl = BOT_URL . '/' . $encryptedUserId;
        return "Hello, to send a message to the user, please reply to this message with your message. \n\n#send <a href='{$dummyUrl}'>⁪</a>";
    }

    // check if the message is a reply to another message from the bot
    if (isset($message['reply_to_message']) && $message['reply_to_message']['from']['id'] === (int) BOT_ID) {
        $replyMessage = $message['reply_to_message'];

        // chek if text is set, if not, end with 200
        if (!isset($replyMessage["text"])) {
            return '';
        }

        // if reply message has #send{encryptedUserId} then send the message to the user with the nick name
        if (strpos($replyMessage["text"], "#send") !== false) {
            $encryptedUserId = explode(BOT_URL . '/', $replyMessage["entities"][1]['url'])[1];

            $fromEncryptedUserId = encrypt($message['chat']['id']);
            // get message id of the current chat message 
            $messageId = $message['message_id'];
            $dummyUrl = BOT_URL . '/' . $fromEncryptedUserId . '/' . $messageId;
            sendMessage(decrypt($encryptedUserId), "You have a new message #from <a href='{$dummyUrl}'>⁪</a>a user, to reply, simply reply to this message: \n\n" . $message['text']);

            return "Your Message Sent Successfully!";
        }

        if (strpos($replyMessage["text"], "#from") !== false) {
            $encryptedUserId = explode('/', explode(BOT_URL . '/', $replyMessage["entities"][1]['url'])[1])[0];
            $messageIdToReply = explode($encryptedUserId . '/', $replyMessage["entities"][1]['url'])[1];
            $fromEncryptedUserId = encrypt($message['chat']['id']);
            $messageId = $message['message_id'];
            $dummyUrl = BOT_URL . '/' . $fromEncryptedUserId . '/' . $messageId;
            sendMessage(decrypt($encryptedUserId), "New Reply to your message #from <a href='{$dummyUrl}'>⁪</a>a user, to reply, simply reply to this message: \n\n" . $message['text'], $messageIdToReply);

            return "Your Message Sent Successfully!";
        }
    }

    return '';
}

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


// Function to send a message using the Telegram bot API
function sendMessage($chatId, $message, $replyToMessageId = null)
{
    $data = [
        'chat_id' => $chatId,
        'text' => $message,
        'parse_mode' => 'HTML',
    ];

    if ($replyToMessageId) {
        $data['reply_to_message_id'] = $replyToMessageId;
    }

    $ch = curl_init("https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    $result = curl_exec($ch);
    curl_close($ch);

    return $result;
}

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