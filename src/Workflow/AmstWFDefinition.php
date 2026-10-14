<?php

declare(strict_types=1);

namespace App\Workflow;

use App\Entity\Amst;
use Survos\WorkflowExtrasBundle\Attribute\Place;
use Survos\WorkflowExtrasBundle\Attribute\Transition;

#[\Survos\WorkflowExtrasBundle\Attribute\AsWorkflow(supports: [Amst::class], name: self::WORKFLOW_NAME)]
class AmstWFDefinition
{
	public const WORKFLOW_NAME = 'AmstWorkflow';

	#[Place(initial: true)]
	public const PLACE_NEW = 'new';

	#[Place]
	public const PLACE_DONE = 'done';

	#[Transition(from: [self::PLACE_NEW], to: self::PLACE_DONE)]
	public const TRANSITION_DO = 'do';
}
