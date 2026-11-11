<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Agent;

use Drupal\Core\KeyValueStore\KeyValueExpirableFactoryInterface;
use Drupal\Core\KeyValueStore\KeyValueStoreExpirableInterface;

/**
 * Acciones pendientes en el almacén clave-valor con caducidad de Drupal.
 */
final class KeyValuePendingActionStore implements PendingActionStoreInterface {

  /**
   * Segundos que el usuario tiene para confirmar.
   */
  private const TTL = 900;

  /**
   * Colección `sa_assistant.pending` del almacén con caducidad.
   */
  private readonly KeyValueStoreExpirableInterface $store;

  public function __construct(KeyValueExpirableFactoryInterface $factory) {
    $this->store = $factory->get('sa_assistant.pending');
  }

  /**
   * {@inheritdoc}
   */
  public function create(string $tool, array $input): string {
    // 32 bytes aleatorios: imposible de adivinar, así que el token basta
    // para demostrar que quien confirma es quien recibió la propuesta.
    $token = bin2hex(random_bytes(32));
    $this->store->setWithExpire($token, ['tool' => $tool, 'input' => $input], self::TTL);
    return $token;
  }

  /**
   * {@inheritdoc}
   */
  public function take(string $token): ?array {
    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
      return NULL;
    }
    $action = $this->store->get($token);
    $this->store->delete($token);
    return $action;
  }

}
