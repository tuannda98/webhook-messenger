<?php

declare(strict_types=1);

namespace App\Service;

use App\Config\Config;
use App\Exception\MessengerApiException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use Psr\Http\Message\ResponseInterface;
use Psr\Log\LoggerInterface;

/**
 * Wraps the Facebook Messenger Send API.
 *
 * Attachment types accepted by $type params: 'image' | 'audio' | 'video' | 'file'
 * messaging_type values: 'RESPONSE' | 'UPDATE' | 'MESSAGE_TAG'
 *
 * Message Tags (dùng kèm messaging_type=MESSAGE_TAG để gửi sau 24h):
 *   TAG_CONFIRMED_EVENT_UPDATE — nhắc/cập nhật sự kiện user đã đăng ký
 *   TAG_POST_PURCHASE_UPDATE   — cập nhật đơn hàng, vận chuyển, hoàn tiền
 *   TAG_ACCOUNT_UPDATE         — thay đổi tài khoản, bảo mật, xác thực
 */
class MessengerService
{
    public const TAG_CONFIRMED_EVENT_UPDATE = 'CONFIRMED_EVENT_UPDATE';
    public const TAG_POST_PURCHASE_UPDATE   = 'POST_PURCHASE_UPDATE';
    public const TAG_ACCOUNT_UPDATE         = 'ACCOUNT_UPDATE';

    // FB error codes that indicate rate limiting — warrant retry with backoff
    private const RATE_LIMIT_CODES = [4, 17, 32, 80006, 613];
    private const MAX_RETRIES      = 3;
    private const RETRY_BASE_MS    = 5_000; // doubles each attempt: 5s → 10s → 20s

    // Log a warning when this percentage of quota is consumed
    private const RATE_LIMIT_WARN_PCT = 80;

    private Client $http;
    private string $baseUrl;

    public function __construct(private readonly LoggerInterface $logger)
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

    /**
     * Gửi text sau 24h bằng Message Tag.
     * $tag: dùng các hằng TAG_* của class này.
     */
    public function sendTaggedText(string $recipientId, string $text, string $tag): array
    {
        return $this->sendMessage($recipientId, ['text' => $text], 'MESSAGE_TAG', $tag);
    }

    /**
     * Gửi template sau 24h bằng Message Tag.
     * $tag: dùng các hằng TAG_* của class này.
     */
    public function sendTaggedTemplate(string $recipientId, array $templatePayload, string $tag): array
    {
        return $this->sendMessage($recipientId, [
            'attachment' => ['type' => 'template', 'payload' => $templatePayload],
        ], 'MESSAGE_TAG', $tag);
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
            throw new MessengerApiException("Failed to upload attachment: {$e->getMessage()}", null, $e);
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
    // Persistent Menu
    // -------------------------------------------------------------------------

    /**
     * Lấy persistent menu hiện tại từ FB.
     * Trả về mảng call_to_actions hoặc [] nếu chưa thiết lập.
     */
    public function getPersistentMenu(): array
    {
        try {
            $response = $this->http->get("{$this->baseUrl}/me/messenger_profile", [
                'query' => [
                    'fields'       => 'persistent_menu',
                    'access_token' => Config::fbPageToken(),
                ],
            ]);
            $data = json_decode($response->getBody()->getContents(), true);
            return $data['data'][0]['persistent_menu'][0]['call_to_actions'] ?? [];
        } catch (GuzzleException) {
            return [];
        }
    }

    /**
     * Cập nhật persistent menu.
     * $items: [['title' => '...', 'payload' => '...'], ...]  (tối đa 5 mục)
     * Trả về raw response từ FB để caller có thể log/debug.
     */
    public function setPersistentMenu(array $items): array
    {
        $actions = array_map(fn($item) => [
            'type'    => 'postback',
            'title'   => $item['title'],
            'payload' => $item['payload'],
        ], $items);

        try {
            $response = $this->http->post("{$this->baseUrl}/me/messenger_profile", [
                'query' => ['access_token' => Config::fbPageToken()],
                'json'  => [
                    'persistent_menu' => [[
                        'locale'                  => 'default',
                        'composer_input_disabled' => false,
                        'call_to_actions'         => $actions,
                    ]],
                ],
            ]);
            return json_decode($response->getBody()->getContents(), true) ?? [];
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Failed to set persistent menu: {$e->getMessage()}", null, $e);
        }
    }

    /**
     * Xoá persistent menu.
     */
    public function deletePersistentMenu(): void
    {
        try {
            $this->http->delete("{$this->baseUrl}/me/messenger_profile", [
                'query' => ['access_token' => Config::fbPageToken()],
                'json'  => ['fields' => ['persistent_menu']],
            ]);
        } catch (GuzzleException $e) {
            throw new MessengerApiException("Failed to delete persistent menu: {$e->getMessage()}", null, $e);
        }
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
            throw new MessengerApiException("Failed to get user profile: {$e->getMessage()}", null, $e);
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
        string  $recipientId,
        array   $message,
        string  $messagingType = 'RESPONSE',
        ?string $tag           = null
    ): array {
        $body = [
            'recipient'      => ['id' => $recipientId],
            'message'        => $message,
            'messaging_type' => $messagingType,
        ];

        if ($tag !== null) {
            $body['tag'] = $tag;
        }

        return $this->postGraphApi('me/messages', $body);
    }

    // Sender actions (mark_seen, typing_on/off) are pure UX — a failure must never
    // block message delivery. We log a warning and move on instead of throwing.
    private function sendAction(string $recipientId, string $action): void
    {
        try {
            $this->postGraphApi('me/messages', [
                'recipient'     => ['id' => $recipientId],
                'sender_action' => $action,
            ]);
        } catch (MessengerApiException $e) {
            $this->logger->warning('Sender action failed — continuing', [
                'action'    => $action,
                'recipient' => $recipientId,
                'fb_code'   => $e->getFbCode(),
                'error'     => $e->getMessage(),
            ]);
        }
    }

    // -------------------------------------------------------------------------
    // Graph API transport — POST with retry + rate-limit header inspection
    // -------------------------------------------------------------------------

    private function postGraphApi(string $endpoint, array $body): array
    {
        $url  = "{$this->baseUrl}/{$endpoint}";
        $opts = ['query' => ['access_token' => Config::fbPageToken()], 'json' => $body];

        for ($attempt = 0; $attempt <= self::MAX_RETRIES; $attempt++) {
            try {
                $response = $this->http->post($url, $opts);
                $this->inspectRateLimitHeaders($response, $endpoint);
                return json_decode($response->getBody()->getContents(), true);
            } catch (RequestException $e) {
                $fbCode = $this->extractFbErrorCode($e);
                if ($fbCode !== null
                    && in_array($fbCode, self::RATE_LIMIT_CODES, true)
                    && $attempt < self::MAX_RETRIES
                ) {
                    $waitMs = self::RETRY_BASE_MS * (2 ** $attempt);
                    $this->logger->warning('FB rate limit — retrying', [
                        'endpoint' => $endpoint,
                        'fb_code'  => $fbCode,
                        'attempt'  => $attempt + 1,
                        'wait_ms'  => $waitMs,
                    ]);
                    usleep($waitMs * 1_000);
                    continue;
                }
                throw new MessengerApiException("Messenger API error [{$endpoint}]: {$e->getMessage()}", $fbCode, $e);
            } catch (GuzzleException $e) {
                throw new MessengerApiException("Messenger API error [{$endpoint}]: {$e->getMessage()}", null, $e);
            }
        }

        // Unreachable — loop always throws or returns, but satisfies static analysis
        throw new MessengerApiException("Messenger API error [{$endpoint}]: max retries exceeded");
    }

    // -------------------------------------------------------------------------
    // Rate-limit header inspection
    // -------------------------------------------------------------------------

    private function inspectRateLimitHeaders(ResponseInterface $response, string $endpoint): void
    {
        $buc = $response->getHeaderLine('X-Business-Use-Case-Usage');
        if ($buc !== '') {
            $data = json_decode($buc, true) ?? [];
            foreach ($data as $objectId => $usages) {
                foreach ((array) $usages as $usage) {
                    $pct = (int) ($usage['call_count'] ?? 0);
                    if ($pct >= self::RATE_LIMIT_WARN_PCT) {
                        $this->logger->warning('FB BUC rate limit approaching', [
                            'endpoint'  => $endpoint,
                            'object_id' => $objectId,
                            'type'      => $usage['type'] ?? '?',
                            'call_count_pct' => $pct,
                            'eta_minutes'    => $usage['estimated_time_to_regain_access'] ?? 0,
                        ]);
                    }
                }
            }
        }

        $app = $response->getHeaderLine('X-App-Usage');
        if ($app !== '') {
            $usage = json_decode($app, true) ?? [];
            $pct   = (int) ($usage['call_count'] ?? 0);
            if ($pct >= self::RATE_LIMIT_WARN_PCT) {
                $this->logger->warning('FB App rate limit approaching', [
                    'endpoint'       => $endpoint,
                    'call_count_pct' => $pct,
                    'total_time_pct' => $usage['total_time'] ?? 0,
                    'total_cpu_pct'  => $usage['total_cputime'] ?? 0,
                ]);
            }
        }
    }

    // -------------------------------------------------------------------------
    // Extract FB error code from a failed Guzzle response
    // -------------------------------------------------------------------------

    private function extractFbErrorCode(RequestException $e): ?int
    {
        $response = $e->getResponse();
        if ($response === null) {
            return null;
        }

        $body = json_decode((string) $response->getBody(), true);
        $code = $body['error']['code'] ?? null;

        return is_int($code) ? $code : null;
    }
}
