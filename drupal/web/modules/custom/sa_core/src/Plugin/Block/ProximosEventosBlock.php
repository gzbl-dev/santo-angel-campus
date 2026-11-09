<?php

declare(strict_types=1);

namespace Drupal\sa_core\Plugin\Block;

use Drupal\Core\Block\Attribute\Block;
use Drupal\Core\Block\BlockBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\sa_core\WidgetBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Próximos eventos del campus, con número de elementos configurable.
 */
#[Block(
  id: 'sa_proximos_eventos',
  admin_label: new TranslatableMarkup('Próximos eventos'),
  category: new TranslatableMarkup('Universidad Santo Ángel'),
)]
final class ProximosEventosBlock extends BlockBase implements ContainerFactoryPluginInterface {

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
  public function defaultConfiguration(): array {
    return ['limit' => 3];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state): array {
    $form['limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Número de eventos'),
      '#min' => 1,
      '#max' => 12,
      '#default_value' => $this->configuration['limit'],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state): void {
    $this->configuration['limit'] = (int) $form_state->getValue('limit');
  }

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    return $this->widgets->eventos((int) $this->configuration['limit'], with_more_link: TRUE);
  }

}
