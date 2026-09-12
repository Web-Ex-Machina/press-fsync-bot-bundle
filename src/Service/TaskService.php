<?php

declare(strict_types=1);

namespace WEM\PressFsyncBotBundle\Service;

use WEM\PressFsyncBotBundle\Model\DiscordEvent;
use WEM\PressFsyncBotBundle\Model\Task;
use WEM\PressFsyncBotBundle\Model\TwitchEvent;
use WEM\UtilsBundle\Classes\StringUtil;

class TaskService
{
    protected bool $debug = false;

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function setDebug(bool $debug): void
    {
        $this->debug = (bool) $debug;
    }

    public function executeTasks(): void
    {
        // Hardlock the timeout
        set_time_limit(60);

        // Retrieve sync tasks to do and execute them
        $objTasks = Task::findAll(['limit' => 30, 'order' => 'created_at ASC']);

        if (!$objTasks) {
            return;
        }

        while ($objTasks->next()) {
            // If the task did well, delete from table
            if ($this->executeTask($objTasks->current())) {
                $objTasks->delete();

                // pause
                sleep(1);
            }
        }
    }

    public function shouldBeCreated(string $strType, string $strTask, string $strEndpoint, array $arrData, string $strMethod): bool
    {
        return 0 === Task::countBy(
            [
                \sprintf(
                    "type='%s' AND task='%s' AND endpoint='%s' AND data=? AND method='%s'", 
                    $strType, 
                    $strTask, 
                    $strEndpoint, 
                    $strMethod
                ),
            ], 
            serialize($arrData),
        );
    }

    public function create(string $strType, string $strTask, string $strEndpoint, array $arrData, string $strMethod): void
    {
        // Add a way to skip the task system for debug purposes
        if ($this->isDebug()) {
            echo sprintf(
                'Add Task %s - %s - %s - %s - %s',
                $strType,
                $strTask,
                $strEndpoint,
                implode(' | ', $arrData),
                $strMethod
            );
            echo '<hr />';

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
        }
    }

    protected function executeTask(Task $objTask): bool
    {
        $data = $objTask->data ? StringUtil::deserialize($objTask->data) : [];
        if (array_key_exists('title', $data)) {
            $data['title'] = stripcslashes($data['title']);
        }
        $method = $objTask->method ?: "GET";
        $blnSuccess = true;

        switch ($objTask->type) {
            case 'discord':
                switch ($objTask->task) {
                    case 'add_event':
                    case 'update_event':
                        $objEvent = TwitchEvent::findByPk($data['event']);

                        // If there is no event, log the thing and return true so it won't stuck the process
                        if (!$objEvent) {
                            \System::log("Error with the event ID".$data['event'], __METHOD__, 'TL_ERROR');
                            return true;
                        }

                        $intConfig = $data['config']['id'];

                        $data['title'] = stripslashes(substr($objEvent->title, 0, 100));
                        $data['url'] = $data['config']['url'];
                        $data['user'] = $data['config']['broadcaster_id'];
                        $data['start_time'] = date('c', $objEvent->start_time);
                        $data['end_time'] = date('c', $objEvent->end_time);
                        $data['category']['name'] = $objEvent->category_name;

                        if ($data['config']['default_picture']) {
                            $data['image'] = $data['config']['default_picture'];
                        }

                        $data = $this->twitch->parseEvent($data);

                        $objResult = $this->discord->request(
                            $objTask->endpoint,
                            $data,
                            $method
                        );

                        if (10070 === $objResult['code']) {
                            break;
                        }

                        if (is_array($objResult['scheduled_end_time']) && "Cannot schedule event in the past." === $objResult['scheduled_end_time'][0]) {
                            $objEvent->delete();
                            $objTask->delete();
                            $blnSuccess = false;
                            break;
                        }

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        if (!$objResult['id']) {
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
                        foreach ($data['embeds'] as &$e) {
                            $e['title'] = html_entity_decode($e['title']);
                            $e['description'] = html_entity_decode($e['description']);
                        }

                        $objResult = $this->discord->request(
                            $objTask->endpoint,
                            $data,
                            $method
                        );

                        // If we do not have an ID, it is because the request failed
                        // so we must break the switch
                        if (!$objResult['id']) {
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
                $objResult = $this->twitch->request($objTask->endpoint, $data, $method);
            break;
            default:
                throw new Exception("Unkown task type");
        }

        return $blnSuccess;
    }
}