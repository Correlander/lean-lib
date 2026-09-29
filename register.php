<?php

use Leantime\Core\Events\EventDispatcher;
use Leantime\Domain\Plugins\Services\Registration;
use Leantime\Plugins\LeantimeLib\Services\TodoTabRegistry;

$registration = app()->makeWith(Registration::class, ['pluginId' => 'LeantimeLib']);
$registration->addFooterJs(['library-settings.js']);
$registration->addCss(['library-settings.css']);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showticketmodal.ticketTabs',
    [TodoTabRegistry::class, 'renderTabHeaders']
);

EventDispatcher::add_event_listener(
    'leantime.domain.tickets.templates.showticketmodal.ticketTabsContent',
    [TodoTabRegistry::class, 'renderTabPanels']
);
