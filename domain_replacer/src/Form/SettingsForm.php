<?php

namespace Drupal\domain_replacer\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configuration form for replaceable domains.
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'domain_replacer_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return ['domain_replacer.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('domain_replacer.settings');
    $current_domain = \Drupal::request()->getHost();

    $form['info'] = [
      '#type' => 'markup',
      '#markup' => '<div class="messages messages--status">' .
      '<p>' . $this->t('Current domain: <strong>@domain</strong></p>', ['@domain' => $current_domain]) .
      '<p>' . $this->t('This module replaces domains in link fields with the current domain. <strong>Only domains listed below will be replaced.</strong> All other external domains will remain unchanged.') . '</p>' .
      '</div>',
    ];

    $form['enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Enable domain replacement'),
      '#description' => $this->t('Uncheck to disable automatic domain replacement.'),
      '#default_value' => $config->get('enabled'),
    ];

    $form['replaceable_domains'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Domains to Replace'),
      '#description' => $this->t('Enter one domain per line. <strong>Only these domains will be replaced</strong> with the current domain (e.g., old-site.com, www.example.com). Do not include http:// or paths.'),
      '#default_value' => implode("\n", $config->get('replaceable_domains') ?: []),
      '#rows' => 8,
      '#placeholder' => "www.example.com\nstaging.example.com",
    ];

    $form['example'] = [
      '#type' => 'details',
      '#title' => $this->t('Examples'),
      '#open' => TRUE,
    ];

    $form['example']['text'] = [
      '#type' => 'markup',
      '#markup' => '<p><strong>Example 1:</strong><br>' .
      'Domain to replace: <code>old-site.com</code><br>' .
      'Original URL: <code>https://old-site.com/blog/post</code><br>' .
      'Current domain: <code>mysite.com</code><br>' .
      '<strong>Result: <code>https://mysite.com/blog/post</code></strong></p>' .
      '<p><strong>Example 2:</strong><br>' .
      'Domain to replace: <code>old-site.com</code><br>' .
      'Original URL: <code>https://google.com/search?q=drupal</code><br>' .
      'Google is NOT in the replaceable list, so <strong>URL remains unchanged</strong>.</p>' .
      '<p><strong>Example 3:</strong><br>' .
      'Original URL: <code>/node/123</code> (internal link)<br>' .
      '<strong>Result unchanged</strong> (internal links are never replaced)</p>',
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $domains = explode("\n", $form_state->getValue('replaceable_domains'));

    foreach ($domains as $domain) {
      $domain = trim($domain);
      if (!empty($domain)) {
        // Basic domain validation.
        if (!preg_match('/^([a-z0-9\-]+\.)*[a-z0-9\-]+(\.[a-z]{2,})?$/i', $domain)) {
          $form_state->setErrorByName('replaceable_domains',
            $this->t('Invalid domain format: @domain. Use format like example.com or www.example.com', ['@domain' => $domain])
          );
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $domains = explode("\n", $form_state->getValue('replaceable_domains'));
    $domains = array_map('trim', $domains);
    // Remove empty lines.
    $domains = array_filter($domains);

    $this->config('domain_replacer.settings')
      ->set('enabled', $form_state->getValue('enabled'))
      ->set('replaceable_domains', array_values($domains))
      ->save();

    parent::submitForm($form, $form_state);

    \Drupal::messenger()->addMessage($this->t('Domain Replacer settings saved. Only listed domains will be replaced.'));
  }

}
