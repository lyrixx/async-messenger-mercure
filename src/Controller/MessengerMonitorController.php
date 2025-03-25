<?php

namespace App\Controller;

use Symfony\Component\Routing\Attribute\Route;
use Zenstruck\Messenger\Monitor\Controller\MessengerMonitorController as BaseMessengerMonitorController;

#[Route('/admin/messenger')] // path prefix for the controllers
final class MessengerMonitorController extends BaseMessengerMonitorController
{
}
