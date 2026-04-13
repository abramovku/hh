<?php

namespace App\Enums;

enum EstaffEvent: string
{
    case ManualConversation = 'event_type_32';
    case Call = 'event_type_47';
    case Sms = 'event_type_44';
    case ColdConversation = 'event_type_48';
    case SmsSent = 'event_type_51';
    case VoiceWebhook = 'event_type_35';
}
