# Changelog

All notable changes to `laravel-role-permission-manager` will be documented in this file.

## 1.0.2 - 2025-03-03

### v1.0.2 -2025-03-03

- Add support for Laravel 12

## 1.0.1 - 2025-01-18

### v1.0.1 - 2025-01-18

Enhance test coverage and code improvements

- Add comprehensive tests for LaravelRolePermissionManagerServiceProvider
- Improve test coverage for LaravelRolePermissionManager
- Refactor environment checks to use Config::string
- Fix test assertions for collection comparisons
- Update RolePermissionController tests for better coverage
- Add @covers annotations for better test documentation
- Remove debug statements and clean up test code
- Fix user permission retrieval tests with proper mocking
- Convert arrays to collections for consistent testing
- Add test cases for cache clearing and permission management
- Add test cases for role hierarchy management
- Add test cases for wildcard permissions and environment-specific behavior

These changes significantly improve the test coverage and reliability of the package while making the codebase more maintainable and consistent.

## v1.0.0 - 2025-01-17

Initial release of the Laravel Role Permission Manager package, providing a robust and flexible role-based access control (RBAC) system for Laravel applications.

#### Features

- 🔒 Role and Permission Management System
- 🔑 Middleware Support for Role and Permission verification
- 👤 Automatic User Model Registration
- ⚡ Easy-to-use Trait Integration
- 🧪 Comprehensive Test Coverage including Mutation Testing

##### Added

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
  

##### Technical Details

- Full test coverage with Pest PHP
- Mutation testing implementation for enhanced code reliability
- PSR-4 compliant code structure
- Laravel service provider integration

This release provides a solid foundation for managing roles and permissions in Laravel applications with a focus on reliability, ease of use, and thorough testing.

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

## v1.0.1 - 2025-01-18

Enhance test coverage and code improvements

- Add comprehensive tests for LaravelRolePermissionManagerServiceProvider
- Improve test coverage for LaravelRolePermissionManager
- Refactor environment checks to use Config::string
- Fix test assertions for collection comparisons
- Update RolePermissionController tests for better coverage
- Add @covers annotations for better test documentation
- Remove debug statements and clean up test code
- Fix user permission retrieval tests with proper mocking
- Convert arrays to collections for consistent testing
- Add test cases for cache clearing and permission management
- Add test cases for role hierarchy management
- Add test cases for wildcard permissions and environment-specific behavior

These changes significantly improve the test coverage and reliability of the package while making the codebase more maintainable and consistent.

## v1.0.2 -2025-03-03

- Add support for Laravel 12
