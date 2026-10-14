<?php

declare(strict_types=1);

namespace App\Tests;

use App\Entity\Amst;
use App\Workflow\AmstWFDefinition;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Workflow\Registry;

final class NativeWorkflowTest extends KernelTestCase
{
    public function testNativeWorkflowAppliesToExistingMarking(): void
    {
        self::bootKernel();
        $subject = new Amst();
        $workflow = self::getContainer()->get(Registry::class)->get($subject, AmstWFDefinition::WORKFLOW_NAME);
        self::assertSame('new', $subject->marking);
        $workflow->apply($subject, 'do');
        self::assertSame('done', $subject->marking);
    }
}
