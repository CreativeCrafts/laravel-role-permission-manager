# Changelog

All notable changes to `laravel-role-permission-manager` will be documented in this file.

# Laravel Role Permission Manager v1.0.0 - 2025-01-17

Initial release of the Laravel Role Permission Manager package, providing a robust and flexible role-based access control (RBAC) system for Laravel applications.

## Features

- 🔒 Role and Permission Management System
- 🔑 Middleware Support for Role and Permission verification
- 👤 Automatic User Model Registration
- ⚡ Easy-to-use Trait Integration
- 🧪 Comprehensive Test Coverage including Mutation Testing

### Added

- Initial package setup and core functionality
- Role and Permission management system with database migrations
- User model registration method in service provider for seamless integration
- Custom middleware implementation:
    - Permission middleware for granular access control
    - Role middleware for role-based access control
- Traits for extending user model capabilities
- Comprehensive test suite:
    - Model tests
    - Policy tests
    - Trait tests
    - Mutation tests for middleware reliability
- GitHub issue templates for better community contribution
- Detailed documentation and README with:
    - Installation guide
    - Usage instructions
    - Feature overview
    - Configuration options

### Technical Details

- Full test coverage with Pest PHP
- Mutation testing implementation for enhanced code reliability
- PSR-4 compliant code structure
- Laravel service provider integration

This release provides a solid foundation for managing roles and permissions in Laravel applications with a focus on reliability, ease of use, and thorough testing.