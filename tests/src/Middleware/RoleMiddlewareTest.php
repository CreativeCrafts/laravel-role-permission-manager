<?php

declare(strict_types=1);

use CreativeCrafts\LaravelRolePermissionManager\Contracts\AuthenticatableWithRolesAndPermissions;
use CreativeCrafts\LaravelRolePermissionManager\Middleware\RoleMiddleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

covers(RoleMiddleware::class);

beforeEach(function () {
    $this->middleware = new RoleMiddleware();
    $this->request = mock(Request::class);
    $this->next = static fn () => response('OK');
});

it('returns unauthorized response if user is not authenticated', function () {
    Auth::shouldReceive('check')->once()->andReturn(false);
    $this->request->shouldReceive('expectsJson')->once()->andReturn(false);

    try {
        $this->middleware->handle($this->request, $this->next, 'some-role');
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
        $this->middleware->handle($this->request, $this->next, 'some-role');
    } catch (Symfony\Component\HttpKernel\Exception\HttpException $e) {
        expect($e->getMessage())->toBe('Unauthorized action.')
            ->and($e->getStatusCode())->toBe(403);
        return;
    }

    $this->fail('Expected HttpException was not thrown');
});

it('returns next response if user has the required role', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    $user = mock(AuthenticatableWithRolesAndPermissions::class);
    $this->request->shouldReceive('user')->once()->andReturn($user);
    $user->shouldReceive('hasRole')->with('admin')->once()->andReturn(true);

    $response = $this->middleware->handle($this->request, $this->next, 'admin');

    expect($response->getContent())->toBe('OK');
});

it('returns unauthorized response if user does not have any of the required roles', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    $user = mock(AuthenticatableWithRolesAndPermissions::class);
    $this->request->shouldReceive('user')->once()->andReturn($user);
    $this->request->shouldReceive('expectsJson')->once()->andReturn(false);
    $user->shouldReceive('hasRole')->with('admin')->once()->andReturn(false);
    $user->shouldReceive('hasRole')->with('manager')->once()->andReturn(false);

    try {
        $this->middleware->handle($this->request, $this->next, 'admin', 'manager');
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

    $response = $this->middleware->handle($this->request, $this->next, 'some-role');

    expect($response->getStatusCode())->toBe(403)
        ->and($response->getContent())->toBeJson()
        ->and(json_decode($response->getContent(), true))->toHaveKey('message', 'Unauthorized action.');
});

it('allows access if user has at least one of the required roles', function () {
    Auth::shouldReceive('check')->once()->andReturn(true);
    $user = mock(AuthenticatableWithRolesAndPermissions::class);
    $this->request->shouldReceive('user')->once()->andReturn($user);
    $user->shouldReceive('hasRole')->with('admin')->once()->andReturn(false);
    $user->shouldReceive('hasRole')->with('manager')->once()->andReturn(true);

    $response = $this->middleware->handle($this->request, $this->next, 'admin', 'manager');

    expect($response->getContent())->toBe('OK');
});
