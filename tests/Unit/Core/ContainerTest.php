<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use App\Core\Container;
use PHPUnit\Framework\TestCase;
use RuntimeException;

interface GreeterContract
{
}

final class EnglishGreeter implements GreeterContract
{
}

final class NeedsGreeter
{
    public function __construct(public readonly GreeterContract $greeter, public readonly int $times = 2)
    {
    }
}

final class CircularA
{
    public function __construct(public readonly CircularB $b)
    {
    }
}

final class CircularB
{
    public function __construct(public readonly CircularA $a)
    {
    }
}

final class ContainerTest extends TestCase
{
    public function testAutowiresConstructorThroughInterfaceBinding(): void
    {
        $c = new Container();
        $c->bind(GreeterContract::class, EnglishGreeter::class);

        $service = $c->get(NeedsGreeter::class);

        $this->assertInstanceOf(EnglishGreeter::class, $service->greeter);
        $this->assertSame(2, $service->times);
        $this->assertSame($service, $c->get(NeedsGreeter::class), 'entries are shared');
    }

    public function testUnboundInterfaceFailsWithHelpfulMessage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('bind it');
        (new Container())->get(NeedsGreeter::class);
    }

    public function testDetectsCircularDependencies(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Circular');
        (new Container())->get(CircularA::class);
    }
}
