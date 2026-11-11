<?php

namespace App\Enums;

enum MessageChannel: string
{
    case Mail = 'mail';
    case Push = 'push';
    case Sms = 'sms';
    case WhatsApp = 'whatsapp';
}
