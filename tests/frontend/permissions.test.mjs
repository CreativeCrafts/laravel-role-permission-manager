import assert from 'node:assert/strict';
import test from 'node:test';

globalThis.window = {
  Laravel: {
    permissions: [],
  },
};

const permissions = await import('../../build/frontend/resources/js/permissions.js');

test('reads the current principal on every check', () => {
  window.Laravel.permissions = [{ slug: 'posts.edit', scope: null }];
  assert.equal(permissions.can('posts.edit'), true);

  window.Laravel.permissions = [];
  assert.equal(permissions.can('posts.edit'), false);
});

test('distinguishes omitted, empty, zero, and other scopes', () => {
  window.Laravel.permissions = [
    { slug: 'jobs.edit', scope: null },
    { slug: 'jobs.edit', scope: '' },
    { slug: 'jobs.edit', scope: '0' },
    { slug: 'jobs.edit', scope: '1' },
  ];

  assert.equal(permissions.can('jobs.edit'), true);
  assert.equal(permissions.can('jobs.edit', ''), true);
  assert.equal(permissions.can('jobs.edit', '0'), true);
  assert.equal(permissions.can('jobs.edit', 'missing'), false);
  assert.deepEqual(permissions.getAllScopes(), ['', '0', '1']);
});

test('logout and principal switching cannot retain a snapshot', () => {
  window.Laravel.permissions = [{ slug: 'admin.open', scope: null }];
  assert.equal(permissions.can('admin.open'), true);

  window.Laravel = { permissions: [] };
  assert.equal(permissions.can('admin.open'), false);

  delete window.Laravel;
  assert.equal(permissions.can('admin.open'), false);
});
