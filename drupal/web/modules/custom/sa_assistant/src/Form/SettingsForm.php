<?php

declare(strict_types=1);

namespace Drupal\sa_assistant\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;

/**
 * Ajustes del asistente en /admin/config/services/asistente.
 */
final class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'sa_assistant_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['sa_assistant.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config('sa_assistant.settings');

    $form['provider'] = [
      '#type' => 'item',
      '#title' => $this->t('Proveedor del modelo'),
      '#markup' => $this->t('Simulado (modo demo): las herramientas y la búsqueda son reales, pero las respuestas se redactan con reglas, sin llamar a ningún modelo de lenguaje.'),
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Mostrar el asistente en el portal'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['welcome'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Mensaje de bienvenida'),
      '#default_value' => $config->get('welcome'),
      '#rows' => 2,
      '#required' => TRUE,
    ];

    $form['suggestions'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Preguntas sugeridas'),
      '#description' => $this->t('Una por línea. Aparecen como botones al abrir el asistente.'),
      '#default_value' => implode("\n", $config->get('suggestions') ?? []),
      '#rows' => 4,
    ];

    $form['rate_limit'] = [
      '#type' => 'number',
      '#title' => $this->t('Mensajes por hora y por IP'),
      '#default_value' => $config->get('rate_limit'),
      '#min' => 1,
      '#max' => 1000,
      '#required' => TRUE,
    ];

    $form['typing_delay_ms'] = [
      '#type' => 'number',
      '#title' => $this->t('Pausa entre trozos de texto (ms)'),
      '#description' => $this->t('Ritmo al que aparece la respuesta. 0 la envía de golpe.'),
      '#default_value' => $config->get('typing_delay_ms'),
      '#min' => 0,
      '#max' => 200,
      '#required' => TRUE,
    ];

    $form['incidencias'] = [
      '#type' => 'item',
      '#title' => $this->t('Incidencias'),
      '#markup' => $this->t('Las incidencias que se abren desde el asistente están en <a href=":url">Contenido</a>, filtrando por el tipo «Incidencia».', [
        ':url' => Url::fromRoute('system.admin_content', [], ['query' => ['type' => 'incidencia']])->toString(),
      ]),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $suggestions = array_values(array_filter(array_map(
      'trim',
      explode("\n", (string) $form_state->getValue('suggestions')),
    )));

    $this->config('sa_assistant.settings')
      ->set('enabled', (bool) $form_state->getValue('enabled'))
      ->set('welcome', trim((string) $form_state->getValue('welcome')))
      ->set('suggestions', $suggestions)
      ->set('rate_limit', (int) $form_state->getValue('rate_limit'))
      ->set('typing_delay_ms', (int) $form_state->getValue('typing_delay_ms'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
