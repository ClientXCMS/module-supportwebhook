<?php

namespace App\Supportwebhook\Event;

use App\ClientX\Cache\LicenseCache;
use App\Support\Database\DepartmentTable;
use App\Support\Database\MessageTable;
use App\Auth\Database\UserTable;
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
    private UserTable $user;
    private MessageTable $message;
    public function __construct(EventManager $event, ContainerInterface $container, array $webhooks = [])
    {
        $this->container = $container;
        $this->webhooks = [new DiscordWebhook()];
        $this->table = $container->get(DepartmentTable::class);
	    $this->message = $container->get(MessageTable::class);
        $this->router = $container->get(Router::class);
        $this->user = $container->get(UserTable::class);
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
                    continue;
                }
                if (method_exists($target, 'getLastState')){
                    if ($config['action'] == 'support.replay' && $target->getLastState() == 'Reply Support'){
                        continue;
                    }
                }
                if ($config['message'] != null || $config['url'] != null) {
		    $ticketId = $target->getId();
                    $route = $this->router->generateURI('support.admin.tickets.edit', ['id' => $ticketId]);
		    $user = $this->user->find($target->getAccountId());
                    $context = [
                        '%subject%' => $target->subject,
                        '%url%' => RequestHelper::fromGlobal() . $route,
                        '%departmentName%' => $target->department->name,
                        '%priority%' => $target->getPriority(),
                        '%created_at%' => (new \DateTime())->format(\DateTimeInterface::ATOM),
                        '%action%' => ucfirst(explode('.', $event->getName())[1]),
                        '%content%' => $target->getContent(),
                        '%email%' => $user->email,
                        '%userId%' => $user->id,
                        '%username%' => $user->getName(),
                    ];
                    if ($target->getRelated() != null) {
                        $routeName = $target->getRelated()->getRouteName();
                        $id = $target->getRelated()->getId();
                        $context = array_merge($context, [
                            '%related%' => $target->getRelated()->getName(),
                            '%relatedUrl%' => $this->router->generateURI($routeName, compact('id'))]);
                    } else {
                        $context = array_merge($context, [
				            '%related%' => '',
                            '%relatedUrl%' => ''
			]);
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
		if ($this->container->has($keyName)){
            		$data = json_decode(json_encode(str_replace("'", '', $this->container->get($keyName))), true);
		} else {
			$webhook->config([]);
			return;
		}
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
