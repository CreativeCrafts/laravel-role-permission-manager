## ADDED Requirements

### Requirement: Role definition management requires action-specific authority
Role create, update, and delete policy decisions SHALL require their respective explicit management permissions and SHALL NOT grant authority because the actor possesses the target role.

#### Scenario: Role administrator can update
- **GIVEN** an actor with `edit roles`
- **WHEN** the actor requests an update policy decision for a role
- **THEN** the policy grants the update

#### Scenario: Role member cannot update definition
- **GIVEN** an actor assigned the target role but lacking `edit roles`
- **WHEN** the actor requests the same update
- **THEN** the policy denies the update

### Requirement: Permission definition management requires action-specific authority
Permission create, update, and delete policy decisions SHALL require their respective explicit management permissions and SHALL NOT grant authority because the actor possesses the target permission.

#### Scenario: Permission administrator can delete
- **GIVEN** an actor with `delete permissions`
- **WHEN** the actor requests deletion of a permission definition
- **THEN** the policy grants deletion

#### Scenario: Permission holder cannot delete definition
- **GIVEN** an actor holding the target permission but lacking `delete permissions`
- **WHEN** the actor requests deletion
- **THEN** the policy denies deletion

### Requirement: View and list authority remain distinct from mutation
The policy SHALL evaluate view, list, create, update, and delete as separate actions and SHALL NOT use a view or possession grant as mutation authority.

#### Scenario: Catalog reader can list without editing
- **GIVEN** an actor with `view roles` and no role mutation permissions
- **WHEN** list and update decisions are evaluated
- **THEN** list is granted and update is denied

#### Scenario: Mutation authority does not imply unrelated catalog authority
- **GIVEN** an actor with one action-specific permission only
- **WHEN** an unrelated list or mutation action is evaluated
- **THEN** the unrelated action is denied
