<?php
# WIP: do not use it till I come with better encryption method to encrypt tg user id with less than 50 characters

$env = parse_ini_file('.env');

define('BOT_TOKEN', $env['BOT_TOKEN']);
define("KEY", $env['KEY']);
define("BOT_URL", $env['BOT_URL']);


// Get the incoming message from Telegram
$update = file_get_contents('php://input');
$update = json_decode($update, true);

// Check if the message is valid
if (isset($update['message'])) {

    // if message is '/start' then ask for user nick name to create a url for them
    $message = $update['message'];
    if ($message['text'] == '/start') {
        sendMessage($message['chat']['id'], "Hello, please reply to this message with your nickname to create a url for you. \n\n#setNickName");
    }

    // check if the message is a reply to another message
    if (isset($message['reply_to_message'])) {
        $replyMessage = $message['reply_to_message'];

        if (strpos($replyMessage['text'], '#setNickName') !== false) {
            $nickName = $message['text'];
            $encryptedUserId = encrypt($message['from']['id']);
            $urlEncodedMsg = base64url_encode($encryptedUserId . '|' . $nickName);
            $url = BOT_URL . '?start=msg' . $urlEncodedMsg;
            sendMessage($message['chat']['id'], "Hello, your url is: $url");
        }


        // if reply message has #send{encryptedUserId}_{nickName} then send the message to the user with the nick name
        if (strpos($replyMessage["text"], "#send") !== false) {
            // get everything after #send and before space
            $encryptedUserId = explode("_", explode("#send", $replyMessage["text"])[1])[0];
            $fromEncryptedUserId = encrypt($message['chat']['id']);
            // get message id of the current chat message 
            $messageId = $message['message_id'];
            sendMessage(decrypt($encryptedUserId), "New Message #from_$fromEncryptedUserId, ($messageId), to reply, simply reply to this message: \n\n" . $message['text']);
            sendMessage($message['chat']['id'], "Your Message Sent Successfully!");
        }

        if (strpos($replyMessage["text"], "#from_") !== false) {
            $encryptedUserId = explode(",", explode("#from_", $replyMessage["text"])[1])[0];
            $messageIdToReply = explode(")", explode("(", $replyMessage["text"])[1])[0];
            $fromEncryptedUserId = encrypt($message['chat']['id']);
            $messageId = $message['message_id'];
            sendMessage(decrypt($encryptedUserId), "New Reply to your message #from_$fromEncryptedUserId, ($messageId): \n\n" . $message['text'], $messageIdToReply);
            sendMessage($message['chat']['id'], "Your Message Sent Successfully!");
        }

    }

    // check if message is '/start msg{EncryptedUserId}'}'
    if (strpos($message['text'], '/start msg') === 0) {
        $text = base64url_decode(str_replace('/start msg', '', $update['message']['text']));

        // everything behind the last| is the encrypted user id and after it is nick name
        $encryptedUserId = explode('|', $text)[0];
        $nickName = explode('|', $text)[1];

        // send the message to user, with the nick name and encrypted user id inside it, and tell them to send the message to the {nickname} they sould reply to this message
        sendMessage($update['message']['chat']['id'], "Hello, to send a message to $nickName, please reply to this message with your message. \n\n#send{$encryptedUserId}_{$nickName}");
    }
}

// Function to encrypt the user ID
function decrypt($string)
{
    $result = '';
    $string = base64_decode($string);
    for ($i = 0; $i < strlen($string); $i++) {
        $char = substr($string, $i, 1);
        $keychar = substr(KEY, ($i % strlen(KEY)) - 1, 1);
        $char = chr(ord($char) - ord($keychar));
        $result .= $char;
    }
    return $result;
}

// TODO: use another encryption method
function encrypt($string)
{
    $result = '';
    for ($i = 0; $i < strlen($string); $i++) {
        $char = substr($string, $i, 1);
        $keychar = substr(KEY, ($i % strlen(KEY)) - 1, 1);
        $char = chr(ord($char) + ord($keychar));
        $result .= $char;
    }
    return base64_encode($result);
}
// Function to send a message using the Telegram bot API
function sendMessage($chatId, $message, $replyToMessageId = null)
{
    $url = "https://api.telegram.org/bot" . BOT_TOKEN . "/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $message
    ];

    if ($replyToMessageId) {
        $data['reply_to_message_id'] = $replyToMessageId;
    }

    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query($data)
        ]
    ];

    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);

    return $result;
}

function base64url_encode($data)
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function base64url_decode($data)
{
    return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
}