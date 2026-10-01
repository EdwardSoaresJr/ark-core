<?php

namespace App\Ark\Communications\Provisioning;

enum EndpointProvisionBuilder: string
{
    case Poly = 'poly';
    case Yealink = 'yealink';
    case Fanvil = 'fanvil';
    case Cisco = 'cisco';
    case Softphone = 'softphone';
    case Mobile = 'mobile';
    case PagingSpeaker = 'paging_speaker';
    case DoorStation = 'door_station';
}
