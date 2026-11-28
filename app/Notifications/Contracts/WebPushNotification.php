<?php

namespace App\Notifications\Contracts;

interface WebPushNotification
{
    /** @return array{title:string,body:string,url:string,tag?:string} */
    public function toWebPush(object $notifiable): array;

    public function webPushPermission(): string;
}
