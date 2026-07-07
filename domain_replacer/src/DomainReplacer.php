<?php

namespace Drupal\domain_replacer;

use Drupal\Core\Entity\EntityInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class DomainReplacer - Service to replace domains in link fields of entities.
 */
class DomainReplacer {

  /**
   * The entity field manager service.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * The logger service.
   *
   * @var \Psr\Log\LoggerInterface
   */
  private LoggerInterface $logger;

  /**
   * The current domain.
   *
   * @var \Drupal\domain\DomainInterface
   */
  private DomainInterface $currentDomain;

  /**
   * The configuration object.
   *
   * @var \Drupal\Core\Config\ConfigInterface
   */
  private ConfigInterface $config;

  public function __construct(
    EntityFieldManagerInterface $entity_field_manager,
    LoggerChannelFactoryInterface $logger_factory,
    RequestStack $request_stack,
    ConfigFactoryInterface $config_factory,
  ) {
    $this->entityFieldManager = $entity_field_manager;
    $this->logger = $logger_factory->get('domain_replacer');
    $this->currentDomain = $request_stack->getCurrentRequest()->getHost();
    $this->config = $config_factory->get('domain_replacer.settings');
  }

  /**
   * Processes an entity to replace domains in link fields.
   */
  public function processEntity(EntityInterface $entity) {
    // Check if module is enabled.
    if (!$this->config->get('enabled')) {
      return FALSE;
    }

    // Only process specific entity types that are known to have link fields.
    $allowed_types = [
      'node',
      'taxonomy_term',
      'media',
      'paragraph',
      'block_content',
      'user',
    ];

    if (!in_array($entity->getEntityTypeId(), $allowed_types)) {
      return FALSE;
    }

    $changed = FALSE;

    try {
      // Check if entity has bundle method (some entities might not)
      if (!method_exists($entity, 'bundle')) {
        return FALSE;
      }

      $link_fields = $this->getLinkFields($entity);

      foreach ($link_fields as $field_name) {
        if ($this->processField($entity, $field_name)) {
          $changed = TRUE;
        }
      }
    }
    catch (\Exception $e) {
      $this->logger->warning('Error processing entity @type:@id: @error', [
        '@type' => $entity->getEntityTypeId(),
        '@id' => $entity->id(),
        '@error' => $e->getMessage(),
      ]);
    }

    return $changed;
  }

  /**
   * Processes a specific field to replace domains in link values.
   */
  public function processField(EntityInterface $entity, $field_name) {
    if (!$entity->hasField($field_name)) {
      return FALSE;
    }

    $field = $entity->get($field_name);
    if (!$field || $field->isEmpty()) {
      return FALSE;
    }

    $changed = FALSE;

    foreach ($field as $delta => $item) {
      if (!empty($item->uri)) {
        $new_uri = $this->replaceDomain($item->uri);

        if ($new_uri && $new_uri !== $item->uri) {
          $field->set($delta, ['uri' => $new_uri]);
          $changed = TRUE;

          $this->logger->notice('Replaced domain in @type:@id field @field: @old -> @new', [
            '@type' => $entity->getEntityTypeId(),
            '@id' => $entity->id(),
            '@field' => $field_name,
            '@old' => $item->uri,
            '@new' => $new_uri,
          ]);
        }
      }
    }

    return $changed;
  }

  /**
   * Gets all link fields for a given entity.
   */
  public function getLinkFields(EntityInterface $entity) {
    $link_fields = [];

    try {
      // Skip if no bundle method.
      if (!method_exists($entity, 'bundle')) {
        return $link_fields;
      }

      $definitions = $this->entityFieldManager
        ->getFieldDefinitions($entity->getEntityTypeId(), $entity->bundle());

      foreach ($definitions as $name => $definition) {
        if ($definition->getType() === 'link') {
          $link_fields[] = $name;
        }
      }
    }
    catch (\Exception $e) {
      // Field definitions might not be available for some entity types.
      // Just return empty array.
      $this->logger->debug('Could not get field definitions for @type: @error', [
        '@type' => $entity->getEntityTypeId(),
        '@error' => $e->getMessage(),
      ]);
    }

    return $link_fields;
  }

  /**
   * Replaces the domain in a given URI.
   */
  public function replaceDomain($uri) {
    // Skip empty URIs.
    if (empty($uri)) {
      return FALSE;
    }

    // Skip internal links.
    if ($this->isInternalLink($uri)) {
      return FALSE;
    }

    // Only process http/https URLs.
    if (!preg_match('/^https?:\/\//', $uri)) {
      return FALSE;
    }

    $parsed = parse_url($uri);
    if (empty($parsed['host'])) {
      return FALSE;
    }

    // Get domains that should be replaced.
    $replaceable_domains = $this->config->get('replaceable_domains') ?: [];

    // IMPORTANT: Only replace if domain is in the replaceable list.
    if (!in_array($parsed['host'], $replaceable_domains)) {
      return FALSE;
    }

    // Don't replace if it's the same domain (avoid unnecessary changes).
    if ($parsed['host'] === $this->currentDomain) {
      return FALSE;
    }

    // Rebuild URL with current domain.
    return $this->rebuildUrl($parsed, $this->currentDomain);
  }

  /**
   * Checks if a URI is an internal link.
   */
  private function isInternalLink($uri) {
    $internal = ['internal:', 'entity:', 'route:', 'base:'];
    foreach ($internal as $prefix) {
      if (strpos($uri, $prefix) === 0) {
        return TRUE;
      }
    }

    return (strlen($uri) > 0 && $uri[0] === '/');
  }

  /**
   * Rebuilds a URL with a new domain.
   */
  private function rebuildUrl($parsed, $new_domain) {
    $url = $parsed['scheme'] . '://' . $new_domain;

    if (isset($parsed['port'])) {
      $url .= ':' . $parsed['port'];
    }
    if (isset($parsed['path'])) {
      $url .= $parsed['path'];
    }
    if (isset($parsed['query'])) {
      $url .= '?' . $parsed['query'];
    }
    if (isset($parsed['fragment'])) {
      $url .= '#' . $parsed['fragment'];
    }

    return $url;
  }

}
