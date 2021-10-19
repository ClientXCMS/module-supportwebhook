<?php

namespace App\Supportwebhook\Event;

use App\ClientX\Cache\LicenseCache;
use App\Support\Database\DepartmentTable;
use App\Support\Entity\Ticket;
use App\Support\Events\AbstractSupportEvent;
use App\Supportwebhook\Discord\DiscordWebhook;
use App\SupportWebhook\WebhookInterface;
use ClientX\Database\NoRecordException;
use ClientX\Event\EventManager;
use ClientX\Helpers\RequestHelper;
use ClientX\Router;
use Psr\Container\ContainerInterface;
use function ClientX\d;

class WebhookManager
{
    private ContainerInterface $container;
    private array $webhooks;
    private Router $router;

    private DepartmentTable $table;

    public function __construct(EventManager $event, ContainerInterface $container, array $webhooks = [])
    {
        $this->container = $container;
        $this->webhooks = [new DiscordWebhook()];
        $this->table = $container->get(DepartmentTable::class);
        $this->router = $container->get(Router::class);
        $event->attach('support.submit', $this);
        $event->attach('support.close', $this);
        $event->attach('support.replay', $this);
        $this->setUp();
    }

    /**
     * @throws NoRecordException
     */
    public function __invoke(AbstractSupportEvent $event)
    {
        /** @var Ticket */
        $target = $event->getTarget();
        /** @var WebhookInterface[] */
        $webhooks = $this->webhooks;

        foreach ($webhooks as $webhook) {

            if ($this->container->get('support.webhook.config')['enabled'] === 'false') {
                return;
            }
            $configs = $webhook->config();

            foreach ($configs as $config) {
                if ($event->getName() != $config['action']) {
                    return;
                }
                if ($config['message'] != null || $config['url'] != null) {
                    $route = $this->router->generateURI('support.admin.ticket.edit', ['id' => $target->getId()]);
                    $context = [
                        '%subject%' => $target->subject,
                        '%url%' => RequestHelper::fromGlobal() . $route,
                        '%departmentName%' => $target->department->name,
                        '%priority%' => $target->getPriority(),
                        '%created_at%' => (new \DateTime())->format(\DateTimeInterface::ATOM),
                        '%action%' => ucfirst(explode('.', $event->getName())[1]),
                        '%content%' => $target->getContent(),
                    ];
                    if ($target->getRelated() != null) {
                        $routeName = $target->getRelated()->getRouteName();
                        $id = $target->getRelated()->getId();
                        $context = array_merge($context, [
                            '%related%' => $target->getRelated()->getName(),
                            '%relatedUrl%' => $this->router->generateURI($routeName, compact('id'))]);
                    } else {
                        $context = array_merge($context, ['%related%' => '',
                            '%relatedUrl%' => '']);
                    }
                    $webhook->context(d($config['message']), $context);

                    $webhook->send($config['url']);
                }
            }
        }
    }
    private function setUp()
    {
        foreach ($this->webhooks as $webhook) {
            $keyName = "support.webhook.config";
            $data = json_decode(json_encode(str_replace("'", '', $this->container->get($keyName))), true);
            $data = collect($data)->map('json_decode')->toArray();

            $actions = $data['action'] ?? [];
            $messages = $data['message'];
            $urls = $data['url'];
            $tmp = [];
            foreach ($actions as $k => $action) {
                if (!empty($urls[$k])) {
                    $tmp[] = [
                        'url' => $urls[$k],
                        'message' => $messages[$k],
                        'type' => 'discord',
                        'action' => $actions[$k],
                    ];
                }
            }
            $webhook->config($tmp);
        }
    }
}