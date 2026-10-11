<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use Contao\CoreBundle\Monolog\ContaoContext;
use Doctrine\DBAL\Connection;
use Psr\Log\LoggerInterface;
use WEM\PressFsyncBotBundle\Model\DiscordEvent;
use WEM\PressFsyncBotBundle\Model\DiscordMessage;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\UtilsBundle\Classes\StringUtil;

class TaskService
{
    protected const BATCH_SIZE = 100;
    protected const MAX_ATTEMPTS = 5;
    // Seconds, kept under the 60s hardlock to leave room for a last request (15s timeout)
    protected const TIME_BUDGET = 40;

    protected bool $debug = false;
    protected array $arrResults = [];
    /** @var array<string, true>|null Keys of the tasks already in database, null until first needed */
    protected ?array $pendingKeys = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly LoggerInterface $logger,
        private readonly TwitchService $twitch,
        private readonly TwitchClient $twitchClient,
        private readonly DiscordClient $discord,
    ) {
    }

    public function __set(mixed $name, mixed $value): void
    {
        $this->{$name} = $value;
    }

    public function __get(mixed $name): mixed
    {
        return $this->{$name};
    }

    public function getResults(): array
    {
        return $this->arrResults;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = (bool) $debug;
    }

    public function cleanTasks(): void
    {
        $this->connection->executeStatement('TRUNCATE tl_pfs_task');
        $this->pendingKeys = null;
    }

    public function executeTasks(): void
    {
        $this->pendingKeys = null;
        $this->arrResults = [
            'success' => [],
            'errors' => [],
            'errors_details' => [],
            'debug' => [],
            'pending' => 0,
        ];

        // Hardlock the timeout
        set_time_limit(60);
        $fltStart = microtime(true);

        // Retrieve sync tasks to do (skip the ones that failed too many times) and execute them
        $objTasks = Task::findBy(
            ['attempts < ?'],
            [self::MAX_ATTEMPTS],
            ['limit' => self::BATCH_SIZE, 'order' => 'created_at ASC']
        );

        if (!$objTasks) {
            return;
        }

        $this->arrResults['pending'] = $objTasks->count();

        while ($objTasks->next()) {
            // Stop before the hardlock, the remaining tasks will be handled by the next run
            if (microtime(true) - $fltStart >= self::TIME_BUDGET) {
                break;
            }

            // If the task did well, delete from table
            if ($this->executeTask($objTasks->current())) {
                $this->arrResults['success'][] = $objTasks->current();

                if (!$this->isDebug()) {
                    $objTasks->delete();
                }

                // Only pause if Discord told us the rate limit bucket is exhausted
                $fltWait = $this->discord->consumeRateLimitWait();

                if ($fltWait > 0) {
                    usleep((int) ($fltWait * 1000000));
                }
            } else {
                $this->arrResults['errors'][] = $objTasks->current();

                $strError = $this->describeFailure($objTasks->current());
                $this->arrResults['errors_details'][] = $strError;

                // Count the failure, so a broken task will not block the queue forever (skip tasks deleted during execution)
                if (!$this->isDebug() && $objTasks->id) {
                    $objTasks->attempts = (int) $objTasks->attempts + 1;
                    $objTasks->save();

                    if ($objTasks->attempts >= self::MAX_ATTEMPTS) {
                        $this->logger->error(
                            \sprintf(
                                'Task %s (%s - %s - %s) failed %s times, it will not be retried. Last error: %s',
                                $objTasks->id,
                                $objTasks->type,
                                $objTasks->task,
                                $objTasks->endpoint,
                                self::MAX_ATTEMPTS,
                                $strError
                            ),
                            ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                        );
                    }
                }
            }

            $this->arrResults['pending']--;
        }
    }

    /**
     * One line description of a failed task, with the error returned by Discord when there is one.
     */
    protected function describeFailure(Task $objTask): string
    {
        $strError = 'discord' === $objTask->type ? $this->discord->getLastError() : null;

        return \sprintf(
            '[%s - %s] %s %s : %s',
            $objTask->type,
            $objTask->task,
            $objTask->method ?: 'GET',
            $objTask->endpoint,
            $strError ?? 'échec (aucune erreur renvoyée)'
        );
    }

    public function shouldBeCreated(string $strType, string $strTask, string $strEndpoint, array $arrData, string $strMethod): bool
    {
        // Load the pending tasks once, so we do not run a query per task to create
        if (null === $this->pendingKeys) {
            $this->pendingKeys = [];
            $objPending = Task::findAll();

            if ($objPending) {
                foreach ($objPending->getModels() as $objPendingTask) {
                    $this->pendingKeys[$this->getTaskKey(
                        (string) $objPendingTask->type,
                        (string) $objPendingTask->task,
                        (string) $objPendingTask->endpoint,
                        (string) $objPendingTask->data,
                        (string) $objPendingTask->method
                    )] = true;
                }
            }
        }

        return !isset($this->pendingKeys[$this->getTaskKey($strType, $strTask, $strEndpoint, serialize($arrData), $strMethod)]);
    }

    protected function getTaskKey(string $strType, string $strTask, string $strEndpoint, string $strData, string $strMethod): string
    {
        return md5(implode("\0", [$strType, $strTask, $strEndpoint, $strData, $strMethod]));
    }

    public function create(string $strType, string $strTask, string $strEndpoint, array $arrData, string $strMethod): void
    {
        // Add a way to skip the task system for debug purposes
        if ($this->isDebug()) {
            $this->arrResults['debug'][] = sprintf(
                'Add Task %s - %s - %s - %s - %s',
                $strType,
                $strTask,
                $strEndpoint,
                json_encode($arrData),
                $strMethod
            );

            return;
        }

        if ($this->shouldBeCreated($strType, $strTask, $strEndpoint, $arrData, $strMethod)) {
            $objTask = new Task();
            $objTask->created_at = microtime(true);
            $objTask->type = $strType;
            $objTask->task = $strTask;
            $objTask->endpoint = $strEndpoint;
            $objTask->data = serialize($arrData);
            $objTask->method = $strMethod;
            $objTask->save();

            $this->pendingKeys[$this->getTaskKey($strType, $strTask, $strEndpoint, $objTask->data, $strMethod)] = true;
        }
    }

    protected function executeTask(Task $objTask): bool
    {
        $data = $objTask->data ? StringUtil::deserialize($objTask->data) : [];
        if (!is_array($data)) {
            $data = [];
        }

        if (array_key_exists('title', $data)) {
            $data['title'] = stripcslashes($data['title']);
        }
        $method = $objTask->method ?: "GET";
        $blnSuccess = true;

        if ($this->isDebug()) {
            $this->arrResults['debug'][] = sprintf(
                'Execute Task %s - %s - %s - %s - %s',
                $objTask->type,
                $objTask->task,
                $objTask->endpoint,
                json_encode($data),
                $method
            );

            return true;
        }

        switch ($objTask->type) {
            case 'discord':
                switch ($objTask->task) {
                    case 'add_event':
                    case 'update_event':
                        $objEvent = TwitchEvent::findByPk($data['event']);

                        // If there is no event, log the thing and return true so it won't stuck the process
                        if (!$objEvent) {
                            $this->logger->error(
                                'Error with the event ID '.$data['event'],
                                ['contao' => new ContaoContext(__METHOD__, ContaoContext::ERROR)]
                            );
                            return true;
                        }

                        $intConfig = $data['config']['id'];

                        $data['title'] = mb_substr(stripslashes((string) $objEvent->title), 0, 100);
                        $data['url'] = $data['config']['url'];
                        $data['user'] = $data['config']['broadcaster_id'];
                        $data['start_time'] = date('c', (int) $objEvent->start_time);
                        $data['end_time'] = date('c', (int) $objEvent->end_time);
                        $data['category']['name'] = (string) $objEvent->category_name;

                        // Category key art, or the fallback picture of the config
                        $data['image'] = $this->twitch->getEventPicture(
                            (int) $objEvent->category_id,
                            $data['config']['default_picture'] ?? null
                        );

                        $data = $this->twitch->parseEvent($data);

                        $objResult = $this->discord->request(
                            $objTask->endpoint,
                            $data,
                            $method
                        );

                        // Network error or timeout, retry on the next run
                        if (null === $objResult) {
                            $blnSuccess = false;
                            break;
                        }

                        if (10070 === ($objResult['code'] ?? null)) {
                            break;
                        }

                        if (is_array($objResult['scheduled_end_time'] ?? null) && "Cannot schedule event in the past." === ($objResult['scheduled_end_time'][0] ?? null)) {
                            $objEvent->delete();
                            $objTask->delete();
                            $blnSuccess = false;
                            break;
                        }

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        if (empty($objResult['id'])) {
                            $blnSuccess = false;
                            break;
                        }

                        $objDiscordEvent = DiscordEvent::findOneBy('discord_event', $objResult['id']);

                        if (!$objDiscordEvent) {
                            $objDiscordEvent = new DiscordEvent();
                        }

                        $objDiscordEvent->tstamp = time();
                        $objDiscordEvent->discord_event = $objResult['id'];
                        $objDiscordEvent->twitch_event = $objEvent->id;
                        $objDiscordEvent->user = $intConfig;
                        $objDiscordEvent->discord_event_name = $data['name'];
                        $objDiscordEvent->discord_event_scheduled_start_time = $data['scheduled_start_time'];
                        $objDiscordEvent->discord_event_scheduled_end_time = $data['scheduled_end_time'];
                        $objDiscordEvent->discord_event_entity_metadata_location = $data['entity_metadata']['location'];
                        $objDiscordEvent->discord_event_category_name = $objEvent->category_name ?: '';
                        $objDiscordEvent->save();
                    break;

                    break;
                    case 'delete_event':
                        $objResult = $this->discord->request(
                            $objTask->endpoint,
                            $data,
                            $method
                        );

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        // We can have a 10070 code, which means the event was not found on the Discord server
                        // Not a biggie, just keep rollin', it should not be in our database either
                        if (null === $objResult) {
                            $blnSuccess = false;
                            break;
                        }

                        $eventId = explode("/", $objTask->endpoint);
                        $objEvent = DiscordEvent::findOneBy('discord_event', $eventId[3]);

                        if ($objEvent) {
                            $objEvent->delete();
                        }
                    break;
                    case 'add_message':
                    case 'update_message':
                        foreach (array_keys($data['embeds'] ?? []) as $k) {
                            $data['embeds'][$k]['title'] = html_entity_decode((string) ($data['embeds'][$k]['title'] ?? ''));
                            $data['embeds'][$k]['description'] = html_entity_decode((string) ($data['embeds'][$k]['description'] ?? ''));
                        }

                        $objResult = $this->discord->request(
                            $objTask->endpoint,
                            $data,
                            $method
                        );

                        // Network error or timeout, retry on the next run
                        if (null === $objResult) {
                            $blnSuccess = false;
                            break;
                        }

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        if (empty($objResult['id'])) {
                            $blnSuccess = false;
                            break;
                        }

                        $objMessage = DiscordMessage::findOneBy(['server='.$data['server'].' AND channel='.$objResult['channel_id'].' AND user='.$data['user'].' AND message='.$objResult['id']], null);

                        if (!$objMessage) {
                            $objMessage = new DiscordMessage();
                        }

                        $objMessage->tstamp = time();
                        $objMessage->channel = $objResult['channel_id'];
                        $objMessage->message = $objResult['id'];
                        $objMessage->user = $data['user'];
                        $objMessage->events = serialize($data['events']);
                        $objMessage->server = $data['server'];
                        $objMessage->save();
                    break;

                    case 'delete_message':

                    break;
                }

            break;
            case 'twitch':
                $objResult = $this->twitchClient->request($objTask->endpoint, $data, $method);

                if (null === $objResult) {
                    $blnSuccess = false;
                }
            break;
            default:
                throw new \Exception("Unkown task type");
        }

        return $blnSuccess;
    }
}
