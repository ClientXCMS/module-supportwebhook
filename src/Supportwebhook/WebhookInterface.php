<?php
namespace App\SupportWebhook;

interface WebhookInterface
{

    public function send(string $uri);

    public function context(?string $content = null, array $context = []);

    public function config(?array $config = null):array;

    public function name():string;
}
