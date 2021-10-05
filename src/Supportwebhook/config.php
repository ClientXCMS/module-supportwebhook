<?php
/**
 * Support Webhook | Configuration
 *
 * @author MartinDev
 * @version 1.0
 *
 *
 */

use App\Supportwebhook\Discord\DiscordWebhook;
use App\Supportwebhook\Event\WebhookManager;
use App\Supportwebhook\SupportWebhookSettings;

use function ClientX\setting;
use function DI\add;
use function DI\autowire;
use function DI\get;

return [
    'admin.settings' => add(get(SupportwebhookSettings::class)),
    'support.webhooks' => [new DiscordWebhook()],
    WebhookManager::class => autowire()->constructorParameter('webhooks', get('support.webhooks')),
    'support.webhook.config' => [
        'enabled' => setting('support.webhook', false),
        'url' => setting('support.webhook.url'),
        'message' => setting('support.webhook.message'),
        'type' => setting('support.webhook.type'),
        'action' => setting('support.webhook.action', 'support.submit'),
    ],
];