<?php

declare(strict_types=1);

namespace Drupal\sa_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sa_core\WidgetBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Bloque colocable con el buscador de titulaciones (widget Vue).
 */
#[Block(
  id: 'sa_buscador_titulaciones',
  admin_label: new TranslatableMarkup('Buscador de titulaciones'),
  category: new TranslatableMarkup('Universidad Santo Ángel'),
)]
final class BuscadorTitulacionesBlock extends BlockBase implements ContainerFactoryPluginInterface {

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    private readonly WidgetBuilder $widgets,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition): static {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get(WidgetBuilder::class),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->widgets->buscador(compact: TRUE);
  }

}
