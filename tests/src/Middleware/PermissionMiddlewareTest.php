<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\PermissionMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

covers(PermissionMiddleware::class);

beforeEach(function () {
    $this->middleware = new PermissionMiddleware();
    $this->request = mock(Request::class);
    $this->next = fn () => response('OK');
});

it('returns unauthorized response if user is not authenticated', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);
    $this->request->shouldReceive('expectsJson')->once()->andReturn(false);

    try {
        $this->middleware->handle($this->request, $this->next, 'some-permission');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getMessage())->toBe('Unauthorized action.')
            ->and($e->getStatusCode())->toBe(403);
        return;
    }

    $this->fail('Expected HttpException was not thrown');
});

it('returns unauthorized response if user does not implement AuthenticatableWithRolesAndPermissions', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    $this->request->shouldReceive('user')->once()->andReturn(new stdClass());
    $this->request->shouldReceive('expectsJson')->once()->andReturn(false);

    try {
        $this->middleware->handle($this->request, $this->next, 'some-permission');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getMessage())->toBe('Unauthorized action.')
            ->and($e->getStatusCode())->toBe(403);
        return;
    }

    $this->fail('Expected HttpException was not thrown');
});

it('returns next response if user has the required permission', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    $user = mock(AuthenticatableWithRolesAndPermissions::class);
    $this->request->shouldReceive('user')->once()->andReturn($user);
    $user->shouldReceive('hasPermissionTo')->with('some-permission')->once()->andReturn(true);

    $response = $this->middleware->handle($this->request, $this->next, 'some-permission');

    expect($response->getContent())->toBe('OK');
});

it('returns unauthorized response if user does not have any of the required permissions', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    $user = mock(AuthenticatableWithRolesAndPermissions::class);
    $this->request->shouldReceive('user')->once()->andReturn($user);
    $this->request->shouldReceive('expectsJson')->once()->andReturn(false);
    $user->shouldReceive('hasPermissionTo')->with('permission1')->once()->andReturn(false);
    $user->shouldReceive('hasPermissionTo')->with('permission2')->once()->andReturn(false);

    try {
        $this->middleware->handle($this->request, $this->next, 'permission1', 'permission2');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getMessage())->toBe('Unauthorized action.')
            ->and($e->getStatusCode())->toBe(403);
        return;
    }

    $this->fail('Expected HttpException was not thrown');
});

it('returns json response for unauthorized action if request expects json', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);
    $this->request->shouldReceive('expectsJson')->once()->andReturn(true);

    $response = $this->middleware->handle($this->request, $this->next, 'some-permission');

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toBeJson()
        ->and(json_decode($response->getContent(), true))->toHaveKey('message', 'Unauthorized action.');
});
