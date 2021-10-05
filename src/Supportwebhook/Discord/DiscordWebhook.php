<?php
namespace App\Supportwebhook\Discord;

use App\SupportWebhook\WebhookInterface;
use ClientX\Discord\DiscordMessage;
use GuzzleHttp\Client;
use InvalidArgumentException;
use function ClientX\d;

class DiscordWebhook implements WebhookInterface
{

    protected ?string $content = null;

    protected array $config = [];

    public function __construct(string $content = null)
    {
        $this->content($content);
    }

    public function content(?string $content = null): DiscordWebhook
    {
        if (strlen($content) > 2000) {
            throw new InvalidArgumentException('Embed content is limited to 2000 characters');
        }
        if (empty($content) && $content != null) {
            throw new InvalidArgumentException('Embed content cannot be empty');
        }

        $this->content = $content;

        return $this;
    }

    public function context(?string $content = null, array $context = []): DiscordWebhook
    {
        $context = str_replace(array_keys($context), array_values($context), $content);
        return $this->content($context);
    }

    public function send(string $uri)
    {
        $client = new Client();
        try {
            $client->post($uri, [
                'form_params' =>  [
                    'content' => d($this->content),
                    'username' => 'ClientXCMS - Discord Webhook'
                ]
            ]);
        } catch (\Exception $e) {
        }
    }

    public function name(): string
    {
        return "discord";
    }

    public function config(?array $config = null): array
    {
        if (!$config && $this->config) {
            return $this->config;
        }
        $this->config = $config;
        return $config;
    }
}
