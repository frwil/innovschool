<?php
// src/EventSubscriber/ModuleRouteSubscriber.php
namespace App\EventSubscriber;

use App\Service\ModuleManager;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ModuleRouteSubscriber implements EventSubscriberInterface
{
    private $moduleManager;

    public function __construct(ModuleManager $moduleManager)
    {
        $this->moduleManager = $moduleManager;
    }

    public function onKernelController(ControllerEvent $event)
    {
        $request = $event->getRequest();
        $module = $request->attributes->get('_module'); // Ajoutez _module à vos routes
        
        if ($module && !$this->moduleManager->isEnabled($module)) {
            throw new NotFoundHttpException('Module désactivé');
        }
    }

    public static function getSubscribedEvents()
    {
        return [
            'kernel.controller' => 'onKernelController',
        ];
    }
}