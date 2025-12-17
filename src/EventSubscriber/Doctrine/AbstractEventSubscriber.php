<?php

namespace App\EventSubscriber\Doctrine;

use Doctrine\Persistence\ObjectManager;

abstract class AbstractEventSubscriber {

  protected function getChangedProperties(ObjectManager $objectManager, $entity): array {
    $changedProperties = $objectManager->getUnitOfWork()->getEntityChangeSet($entity);
    unset($changedProperties["createdAt"]);
    unset($changedProperties["updatedAt"]);
    return $changedProperties;
  }

  protected function isPropertyChanged(ObjectManager $objectManager, $entity, $property): bool {
    return array_key_exists((string) $property, $this->getChangedProperties($objectManager, $entity));
  }

  protected function hasChangedProperties(ObjectManager $objectManager, $entity, array $properties): bool {
    $changedProps = $this->getChangedProperties($objectManager, $entity);
    return array_any(array_keys($changedProps), fn($changedProp) => in_array($changedProp, $properties));
  }

  /**
   * Force to have $properties and only them changed at the same time (all must be present and changed)
   * Maybe a rework with a more gentle method could be wanted
   *
   * @param mixed $item
   * @param array $properties
   * @return bool
   * @see hasOnlyWhitelistedChangedProperties for a more gentle method
   *
   */
  protected function hasOnlyChangedProperties(ObjectManager $objectManager, $entity, array $properties): bool {
    if (count($this->getChangedProperties($objectManager, $entity)) !== count($properties)) return false;
    return array_all($properties, fn($property) => $this->isPropertyChanged($objectManager, $entity, $property));
  }

  /**
   * The changed elements must be part of the allowedProperties, not all elements must be present
   *
   * @param mixed $item
   * @param array $allowedProperties
   * @return bool
   */
  protected function hasOnlyWhitelistedChangedProperties(ObjectManager $objectManager, $entity, array $allowedProperties): bool {
    $changedProps = $this->getChangedProperties($objectManager, $entity);
    return array_all(array_keys($changedProps), fn($changedProp) => in_array($changedProp, $allowedProperties));
  }
}
