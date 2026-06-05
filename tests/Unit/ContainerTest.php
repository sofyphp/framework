<?php

declare(strict_types=1);

namespace Tests\Unit;

use Sofy\Core\Container;
use Tests\TestCase;

final class ContainerTest extends TestCase
{
    public function test_bind_resolves_fresh_each_time(): void
    {
        $c = new Container();
        $c->bind('x', fn() => new \stdClass());
        $this->assertNotSame($c->make('x'), $c->make('x'));
    }

    public function test_singleton_resolves_same_instance(): void
    {
        $c = new Container();
        $c->singleton('y', fn() => new \stdClass());
        $this->assertSame($c->make('y'), $c->make('y'));
    }

    public function test_instance_and_has(): void
    {
        $c = new Container();
        $obj = new \stdClass();
        $c->instance('z', $obj);
        $this->assertTrue($c->has('z'));
        $this->assertSame($obj, $c->make('z'));
        $this->assertFalse($c->has('missing'));
    }
}
