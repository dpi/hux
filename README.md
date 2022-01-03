Hux

# About

There are a few projects out there that try to introduce an event subscriber
driven way of working. Hux is an in between solution, allowing the full benefits
of dependency injection and class driven logic.

Hux is a project specifically designed for developers, allowing hook
implementations without needing to define a .module file or any kind of proxy
class/service features.

You can also define multiple hook implementation per module!

Overriding original hook implementations is also possible using the 
`[#ReplaceOriginalHook]` annotation.

# Installation

 1. Install as normally.
 2. Patch core with in progress patch from
    https://www.drupal.org/project/drupal/issues/2616814

# Usage

Add an entry to your modules' services.yml file. The entry simply needs to be a
public service, with a class and the 'hooks' tag.

Once a hook class has been added as a service, just clear the site cache. 

Tip: You do not need to clear the site cache to add more hook implementations!

```yaml
services:
  my_module.hooks:
    class: Drupal\my_module\MyModuleHooks
    tags:
      - { name: hooks }
```

And in the class file:

```php
declare(strict_types=1);

namespace Drupal\my_module;

use Drupal\hux\Attribute\Hook;
use Drupal\hux\Attribute\ReplaceOriginalHook;

/**
 * Examples of 'entity_access' hooks.
 */
final class MyModuleHooks {

  #[Hook('entity_access')]
  public function myEntityAccess($entity, $operation, $account): AccessResult {
    // A barebones implementation.
    return AccessResult::neutral();
  }

  #[Hook('entity_access', priority: 100)]
  public function myEntityAccess2($entity, $operation, $account): AccessResult {
    // You can set priority if you have multiple of the same hook!
    return AccessResult::neutral();
  }

  #[Hook('entity_access', moduleName: 'a_different_module', priority: 200)]
  public function myEntityAccess3($entity, $operation, $account): AccessResult {
    // You can masquerade as a different module!
    return AccessResult::neutral();
  }

  #[ReplaceOriginalHook(hook: 'entity_access', moduleName: 'media')]
  public function myEntityAccess4(EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    // You can override hooks for other modules! E.g \media_entity_access()
    return AccessResult::neutral();
  }

  #[ReplaceOriginalHook(hook: 'entity_access', moduleName: 'media', originalInvoker: TRUE)]
  public function myEntityAccess5(callable $originalInvoker, EntityInterface $entity, $operation, AccountInterface $account): AccessResult {
    // If you override a hook for another module, you can have the original
    // implementation passed to you as a callable!
    $originalResult = $originalInvoker($entity, $operation, $account);
    // Do something...
    return AccessResult::neutral();
  }

}
```

The project makes use of PHP annotations. As of this writing Drupal's code
sniffs don't work that great with PHP 8.0/8.1 features, you can use the patch
at https://www.drupal.org/project/coder/issues/3250346 to appease code sniffer.

Working examples of all Hux features can be found in included tests.

# License

This program is free software; you can redistribute it and/or modify it under the terms of the GNU General Public
License as published by the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied
warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with this program; if not, write to the Free
Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
