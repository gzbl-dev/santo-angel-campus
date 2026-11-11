<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Hook;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Hook\Attribute\Hook;
use Drupal\Core\Routing\AdminContext;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Url;
use Drupal\sa_assistant\Llm\LlmClientInterface;

/**
 * Hooks del asistente, como clase con atributos (Drupal 11.1+).
 */
final class AssistantHooks {

  public function __construct(
    private readonly ConfigFactoryInterface $configFactory,
    private readonly AdminContext $adminContext,
    private readonly AccountInterface $currentUser,
    private readonly LlmClientInterface $llm,
  ) {}

  /**
   * Implements hook_theme().
   */
  #[Hook('theme')]
  public function theme(): array {
    return [
      'sa_asistente' => [
        'variables' => ['settings' => []],
      ],
    ];
  }

  /**
   * Implements hook_page_bottom().
   *
   * Añade el punto de montaje del widget en todas las páginas públicas.
   */
  #[Hook('page_bottom')]
  public function pageBottom(array &$page_bottom): void {
    if ($this->adminContext->isAdminRoute()) {
      return;
    }

    $config = $this->configFactory->get('sa_assistant.settings');
    $page_bottom['sa_assistant'] = [
      '#theme' => 'sa_asistente',
      '#settings' => [
        'endpoint' => Url::fromRoute('sa_assistant.message')->toString(),
        'confirmEndpoint' => Url::fromRoute('sa_assistant.confirm')->toString(),
        'tokenEndpoint' => Url::fromRoute('system.csrftoken')->toString(),
        'welcome' => $config->get('welcome'),
        'suggestions' => $config->get('suggestions') ?? [],
        'simulated' => $this->llm->isSimulated(),
      ],
      '#attached' => ['library' => ['sa_core/asistente']],
      '#access' => $config->get('enabled') && $this->currentUser->hasPermission('use sa assistant'),
      '#cache' => [
        'contexts' => ['user.permissions', 'route'],
        'tags' => $config->getCacheTags(),
      ],
    ];
  }

}
