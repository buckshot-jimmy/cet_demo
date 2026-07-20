<?php

namespace App\Services;

use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class PushNotificationService
{
    public function __construct(private HubInterface $mercureHub) {}

    public function pushNotificationToMercure($event): string
    {
        try {
            $update = new Update($event);

            $published = $this->mercureHub->publish($update);
        } catch (\Exception $exception) {
            throw new \Exception("Successful operation but failed to publish notification", 2001,
                $exception);
        }

        return $published;
    }
}
