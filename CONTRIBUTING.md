# Loading the fixture
Various commands are available:

- `composer reload-fixture`
  - Empty the database then load all the fixtures
- `composer reload-db`
  - Completely drop the database and recreate it from scratch, then load all fixtures

# Creating new entity
When creating a new entity, you should do the next steps :

1. Create the Entity (and his linked repository)
   - `bin/console make:entity`
2. Add the route in `config/packages/security.yaml`
3. Create the matching factory (useful for generating fake data)
   - `bin/console make:factory --test`
4. Optional, Create the matching story
    - `bin/console make:story --test`
5. Create the matching test under `tests/Entity`
6. Add the data generation inside `src/DataFixtures/AppFixtures`
7. Generate the doctrine migration script
    - `bin/console make:migration` 
    - Remove the alter fields for `user_member`, `user_security_code` and related migration for `messenger` the field is already migrated but the symfony scripts want to generate another one that will break the site...
