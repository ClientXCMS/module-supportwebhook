<?php

namespace App\Supportwebhook;

use App\Admin\Database\SettingTable;
use App\Admin\Settings\SettingsInterface;
use ClientX\Renderer\RendererInterface;
use ClientX\Validator;

class SupportWebhookSettings implements SettingsInterface
{

    const VARIABLES_NAMES = [
        "%subject%", "%departmentName%",
        "%priority%", "%created_at%",
        "%action%", "%url%", "%content%", '%relatedUrl%', '%related%',
        "%username%",  "%userid%", '%email%'
    ];
    const OPTIONS = [
        'support.replay' => 'On Reply',
        'support.submit' => 'On Submit',
        'support.close' => 'On Close'
    ];
    private array $webhooks = [];
    private int $i = 0;
    public function __construct(SettingTable $table)
    {
        $enabled = $table->findSetting("support.webhook", false);
        $actions = json_decode($table->findSetting("support_webhook_action", "[]"), true);
        $messages = json_decode($table->findSetting("support_webhook_message", "[]"), true);
        $urls = json_decode($table->findSetting("support_webhook_url", "[]"), true);
        foreach ($actions as $k => $action) {
            if (!empty($urls[$k])) {
                $this->webhooks[$this->i] = [
                    'enabled' => $enabled,
                    'url' => $urls[$k],
                    'message' => $messages[$k],
                    'type' => 'discord',
                    'action' => $actions[$k],
                ];
                $this->i++;
            }
        }
    }

    public function name(): string
    {
        return "supportwebhook";
    }

    public function title(): string
    {
        return "Support Webhook";
    }

    public function icon(): string
    {
        return "fas fa-paperclip";
    }

    public function render(RendererInterface $renderer): string
    {
        $context = ['variablesNames' => self::VARIABLES_NAMES, 'options' => self::OPTIONS, 'webhooks' => $this->webhooks, 'i' => $this->i,];
        return $renderer->render('@supportwebhook_admin/settings', $context);
    }

    public function validate(array $params): Validator
    {
        $validator = new Validator($params);
        return $validator;
        foreach ($params as $key => $value) {
            if (str_starts_with("support_webhook_url", $key)) {
                $validator->isUrl($key);
            }
            if (str_starts_with("support_webhook_message", $key)) {
                $validator->notEmpty($key);
            }
        }
        return $validator;
    }
}
