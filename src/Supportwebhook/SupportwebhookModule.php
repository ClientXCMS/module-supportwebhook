<?php
namespace App\Supportwebhook;

use App\Supportwebhook\Event\WebhookManager;
use ClientX\Module;
use ClientX\Renderer\RendererInterface;

class SupportwebhookModule extends Module
{
    const TRANSLATIONS =  [
        "fr_FR" => __DIR__ ."/trans/fr.php",
        "en_GB" => __DIR__ ."/trans/en.php",
        "es_ES" => __DIR__ ."/trans/es.php"
    ];
    const DEFINITIONS = __DIR__ . '/config.php';
    public function __construct(WebhookManager  $manager, RendererInterface $renderer)
    {
        $renderer->addPath('supportwebhook_admin', __DIR__ . '/Views');
        $requiredClass = 'App\Support\SupportModule';
        if (!class_exists($requiredClass)) {
            throw new \Exception('The support module is required');
        }
    }
}
