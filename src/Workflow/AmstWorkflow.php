<?php

namespace App\Workflow;

use App\Entity\Amst;
use App\Workflow\AmstWFDefinition as WF;
use Symfony\Component\Workflow\Attribute\AsGuardListener;
use Symfony\Component\Workflow\Attribute\AsTransitionListener;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

class AmstWorkflow
{
	public const WORKFLOW_NAME = 'AmstWorkflow';

	public function __construct()
	{
	}


	public function getAmst(\Symfony\Component\Workflow\Event\Event $event): Amst
	{
		/** @var Amst */ return $event->getSubject();
	}


	#[AsGuardListener(WF::WORKFLOW_NAME)]
	public function onGuard(GuardEvent $event): void
	{
		$amst = $this->getAmst($event);

		switch ($event->getTransition()->getName()) {
		/*
		e.g.
		if ($event->getSubject()->cannotTransition()) {
		  $event->setBlocked(true, "reason");
		}
		App\Entity\Amst
		*/
		    case WF::TRANSITION_DO:
		        break;
		}
	}


	#[AsTransitionListener(WF::WORKFLOW_NAME, WF::TRANSITION_DO)]
	public function onDo(TransitionEvent $event): void
	{
		$amst = $this->getAmst($event);
	}
}
