<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Config;
use App\Exception\MessengerApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;

/**
 * Wraps the Facebook Messenger Send API.
 *
 * Attachment types accepted by $type params: 'image' | 'audio' | 'video' | 'file'
 * messaging_type values: 'RESPONSE' | 'UPDATE' | 'MESSAGE_TAG'
 */
class MessengerService
{
    private Client $http;
    private string $baseUrl;

    public function __construct()
    {
        $this->http    = new Client(['timeout' => 30]);
        $this->baseUrl = sprintf('%s/%s', rtrim(Config::fbApiUrl(), '/'), Config::fbApiVersion());
    }

    // -------------------------------------------------------------------------
    // Sender actions
    // -------------------------------------------------------------------------

    public function markSeen(string $recipientId): void
    {
        $this->sendAction($recipientId, 'mark_seen');
    }

    public function typingOn(string $recipientId): void
    {
        $this->sendAction($recipientId, 'typing_on');
    }

    public function typingOff(string $recipientId): void
    {
        $this->sendAction($recipientId, 'typing_off');
    }

    // -------------------------------------------------------------------------
    // Text
    // -------------------------------------------------------------------------

    public function sendText(string $recipientId, string $text, string $messagingType = 'RESPONSE'): array
    {
        return $this->sendMessage($recipientId, ['text' => $text], $messagingType);
    }

    // -------------------------------------------------------------------------
    // Quick replies
    // Each item shape:
    //   text:     ['content_type' => 'text',     'title' => '...', 'payload' => '...', 'image_url' => '...']
    //   phone:    ['content_type' => 'user_phone_number']
    //   email:    ['content_type' => 'user_email']
    //   location: ['content_type' => 'location']
    // -------------------------------------------------------------------------

    public function sendQuickReplies(string $recipientId, string $text, array $quickReplies): array
    {
        return $this->sendMessage($recipientId, [
            'text'          => $text,
            'quick_replies' => $quickReplies,
        ]);
    }

    public function sendQuickRepliesWithAttachment(
        string $recipientId,
        array  $attachment,
        array  $quickReplies
    ): array {
        return $this->sendMessage($recipientId, [
            'attachment'    => $attachment,
            'quick_replies' => $quickReplies,
        ]);
    }

    // -------------------------------------------------------------------------
    // Attachments — by URL
    // -------------------------------------------------------------------------

    /**
     * Forward an attachment received from another user (type + url).
     */
    public function sendAttachment(string $recipientId, string $type, string $url): array
    {
        return $this->sendAttachmentByUrl($recipientId, $type, $url, false);
    }

    public function sendImage(string $recipientId, string $imageUrl, bool $reusable = false): array
    {
        return $this->sendAttachmentByUrl($recipientId, 'image', $imageUrl, $reusable);
    }

    public function sendAudio(string $recipientId, string $audioUrl, bool $reusable = false): array
    {
        return $this->sendAttachmentByUrl($recipientId, 'audio', $audioUrl, $reusable);
    }

    public function sendVideo(string $recipientId, string $videoUrl, bool $reusable = false): array
    {
        return $this->sendAttachmentByUrl($recipientId, 'video', $videoUrl, $reusable);
    }

    public function sendFile(string $recipientId, string $fileUrl, bool $reusable = false): array
    {
        return $this->sendAttachmentByUrl($recipientId, 'file', $fileUrl, $reusable);
    }

    // -------------------------------------------------------------------------
    // Attachments — by reusable attachment_id (avoids re-uploading)
    // -------------------------------------------------------------------------

    public function sendImageById(string $recipientId, string $attachmentId): array
    {
        return $this->sendAttachmentById($recipientId, 'image', $attachmentId);
    }

    public function sendAudioById(string $recipientId, string $attachmentId): array
    {
        return $this->sendAttachmentById($recipientId, 'audio', $attachmentId);
    }

    public function sendVideoById(string $recipientId, string $attachmentId): array
    {
        return $this->sendAttachmentById($recipientId, 'video', $attachmentId);
    }

    public function sendFileById(string $recipientId, string $attachmentId): array
    {
        return $this->sendAttachmentById($recipientId, 'file', $attachmentId);
    }

    // -------------------------------------------------------------------------
    // Sticker / Like button
    // Facebook built-in sticker IDs:
    //   Like (thumbs up) = 369239263222822
    // -------------------------------------------------------------------------

    public function sendSticker(string $recipientId, int $stickerId): array
    {
        return $this->sendMessage($recipientId, ['sticker_id' => $stickerId]);
    }

    public function sendLike(string $recipientId): array
    {
        return $this->sendSticker($recipientId, 369239263222822);
    }

    // -------------------------------------------------------------------------
    // Templates
    // -------------------------------------------------------------------------

    /**
     * Button template — text + up to 3 buttons.
     * Button shapes:
     *   postback: ['type' => 'postback', 'title' => '...', 'payload' => '...']
     *   url:      ['type' => 'web_url',  'title' => '...', 'url' => '...', 'webview_height_ratio' => 'full|tall|compact']
     *   call:     ['type' => 'phone_number', 'title' => '...', 'payload' => '+84...']
     *   login:    ['type' => 'account_link', 'url' => '...']
     *   logout:   ['type' => 'account_unlink']
     */
    public function sendButtonTemplate(string $recipientId, string $text, array $buttons): array
    {
        return $this->sendTemplate($recipientId, [
            'template_type' => 'button',
            'text'          => $text,
            'buttons'       => $buttons,
        ]);
    }

    /**
     * Generic template — horizontal scrollable carousel, up to 10 cards.
     * Each element shape:
     * [
     *   'title'     => '...',
     *   'subtitle'  => '...',           // optional
     *   'image_url' => 'https://...',   // optional
     *   'default_action' => [...],      // optional web_url action
     *   'buttons'   => [...],           // optional, up to 3
     * ]
     */
    public function sendGenericTemplate(
        string $recipientId,
        array  $elements,
        string $imageAspectRatio = 'horizontal' // 'horizontal' | 'square'
    ): array {
        return $this->sendTemplate($recipientId, [
            'template_type'     => 'generic',
            'elements'          => $elements,
            'image_aspect_ratio' => $imageAspectRatio,
        ]);
    }

    /**
     * Media template — share an image or video with optional buttons.
     * $mediaType: 'image' | 'video'
     * Provide either $url or $attachmentId, not both.
     */
    public function sendMediaTemplate(
        string  $recipientId,
        string  $mediaType,
        ?string $url          = null,
        ?string $attachmentId = null,
        array   $buttons      = []
    ): array {
        $element = ['media_type' => $mediaType];

        if ($attachmentId !== null) {
            $element['attachment_id'] = $attachmentId;
        } else {
            $element['url'] = $url;
        }

        if ($buttons) {
            $element['buttons'] = $buttons;
        }

        return $this->sendTemplate($recipientId, [
            'template_type' => 'media',
            'elements'      => [$element],
        ]);
    }

    /**
     * Receipt template — order confirmation.
     * See https://developers.facebook.com/docs/messenger-platform/send-messages/template/receipt
     */
    public function sendReceiptTemplate(
        string $recipientId,
        string $recipientName,
        string $orderNumber,
        string $currency,
        string $paymentMethod,
        string $orderUrl,
        array  $elements,   // purchased items
        array  $summary,    // ['subtotal'=>..., 'shipping_cost'=>..., 'total_tax'=>..., 'total_cost'=>...]
        array  $address     = [],
        array  $adjustments = [],
        ?int   $timestamp   = null
    ): array {
        $payload = [
            'template_type'  => 'receipt',
            'recipient_name' => $recipientName,
            'order_number'   => $orderNumber,
            'currency'       => $currency,
            'payment_method' => $paymentMethod,
            'order_url'      => $orderUrl,
            'elements'       => $elements,
            'summary'        => $summary,
        ];

        if ($address)      { $payload['address']     = $address;     }
        if ($adjustments)  { $payload['adjustments']  = $adjustments; }
        if ($timestamp)    { $payload['timestamp']    = $timestamp;   }

        return $this->sendTemplate($recipientId, $payload);
    }

    /**
     * One-Time Notification (OTN) request template.
     * Lets the page ask the user for permission to send one follow-up message
     * outside the 24-hour window.
     */
    public function sendOneTimeNotificationTemplate(
        string $recipientId,
        string $title,
        string $payload
    ): array {
        return $this->sendTemplate($recipientId, [
            'template_type' => 'one_time_notif_req',
            'title'         => $title,
            'payload'       => $payload,
        ]);
    }

    /**
     * Product template — display Commerce catalog products.
     * Each element: ['id' => '<product_id>']
     */
    public function sendProductTemplate(string $recipientId, array $elements): array
    {
        return $this->sendTemplate($recipientId, [
            'template_type' => 'product',
            'elements'      => $elements,
        ]);
    }

    /**
     * Customer feedback template.
     * $feedbackScreens shape per Facebook docs.
     */
    public function sendCustomerFeedbackTemplate(
        string $recipientId,
        string $title,
        string $subtitle,
        int    $expiresInDays,
        array  $feedbackScreens
    ): array {
        return $this->sendTemplate($recipientId, [
            'template_type'    => 'customer_feedback',
            'title'            => $title,
            'subtitle'         => $subtitle,
            'button_title'     => 'Start Survey',
            'feedback_screens' => $feedbackScreens,
            'expires_in_days'  => $expiresInDays,
        ]);
    }

    // -------------------------------------------------------------------------
    // Attachment upload (returns attachment_id for reuse)
    // -------------------------------------------------------------------------

    /**
     * Upload a local file or a remote URL as a reusable attachment.
     * Returns ['attachment_id' => '...'].
     */
    public function uploadAttachmentByUrl(string $type, string $url): array
    {
        try {
            $response = $this->http->post("{$this->baseUrl}/me/message_attachments", [
                'query' => ['access_token' => Config::fbPageToken()],
                'json'  => [
                    'message' => [
                        'attachment' => [
                            'type'    => $type,
                            'payload' => ['url' => $url, 'is_reusable' => true],
                        ],
                    ],
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Failed to upload attachment: {$e->getMessage()}", 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Thread control (Handover Protocol)
    // -------------------------------------------------------------------------

    /**
     * Pass thread control to another app (e.g. inbox / human agent).
     * $targetAppId: the app ID to pass control to.
     *   Inbox = 263902037430900
     */
    public function passThreadControl(string $recipientId, string $targetAppId, string $metadata = ''): array
    {
        return $this->postGraphApi('me/pass_thread_control', [
            'recipient'     => ['id' => $recipientId],
            'target_app_id' => $targetAppId,
            'metadata'      => $metadata,
        ]);
    }

    public function takeThreadControl(string $recipientId, string $metadata = ''): array
    {
        return $this->postGraphApi('me/take_thread_control', [
            'recipient' => ['id' => $recipientId],
            'metadata'  => $metadata,
        ]);
    }

    public function requestThreadControl(string $recipientId, string $metadata = ''): array
    {
        return $this->postGraphApi('me/request_thread_control', [
            'recipient' => ['id' => $recipientId],
            'metadata'  => $metadata,
        ]);
    }

    // -------------------------------------------------------------------------
    // User profile
    // -------------------------------------------------------------------------

    public function getUserProfile(
        string $userId,
        array  $fields = ['name', 'first_name', 'last_name', 'picture']
    ): array {
        try {
            $response = $this->http->get("{$this->baseUrl}/{$userId}", [
                'query' => [
                    'fields'       => implode(',', $fields),
                    'access_token' => Config::fbPageToken(),
                ],
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Failed to get user profile: {$e->getMessage()}", 0, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function sendAttachmentByUrl(
        string $recipientId,
        string $type,
        string $url,
        bool   $reusable
    ): array {
        return $this->sendMessage($recipientId, [
            'attachment' => [
                'type'    => $type,
                'payload' => ['url' => $url, 'is_reusable' => $reusable],
            ],
        ]);
    }

    private function sendAttachmentById(string $recipientId, string $type, string $attachmentId): array
    {
        return $this->sendMessage($recipientId, [
            'attachment' => [
                'type'    => $type,
                'payload' => ['attachment_id' => $attachmentId],
            ],
        ]);
    }

    private function sendTemplate(string $recipientId, array $templatePayload): array
    {
        return $this->sendMessage($recipientId, [
            'attachment' => [
                'type'    => 'template',
                'payload' => $templatePayload,
            ],
        ]);
    }

    private function sendMessage(
        string $recipientId,
        array  $message,
        string $messagingType = 'RESPONSE'
    ): array {
        return $this->postGraphApi('me/messages', [
            'recipient'      => ['id' => $recipientId],
            'message'        => $message,
            'messaging_type' => $messagingType,
        ]);
    }

    private function sendAction(string $recipientId, string $action): void
    {
        $this->postGraphApi('me/messages', [
            'recipient'     => ['id' => $recipientId],
            'sender_action' => $action,
        ]);
    }

    private function postGraphApi(string $endpoint, array $body): array
    {
        try {
            $response = $this->http->post("{$this->baseUrl}/{$endpoint}", [
                'query' => ['access_token' => Config::fbPageToken()],
                'json'  => $body,
            ]);

            return json_decode($response->getBody()->getContents(), true);
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Messenger API error [{$endpoint}]: {$e->getMessage()}", 0, $e);
        }
    }
}
