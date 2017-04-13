<?php

namespace Drupal\media_entity_flickr\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\media_entity\EmbedCodeValueTrait;
use Drupal\media_entity_flickr\Plugin\MediaEntity\Type\Flickr;

/**
 * Plugin implementation of the 'flickr_embed' formatter.
 *
 * @FieldFormatter(
 *   id = "flickr_embed",
 *   label = @Translation("Flickr embed"),
 *   field_types = {
 *     "link", "string", "string_long"
 *   }
 * )
 */
class FlickrEmbedFormatter extends FormatterBase {

  use EmbedCodeValueTrait;

  /**
   * {@inheritdoc}
   */
  public function viewElements(FieldItemListInterface $items, $langcode) {
    $element = array();
    $settings = $this->getSettings();
    foreach ($items as $delta => $item) {
      $matches = [];
      foreach (Flickr::$validationRegexp as $pattern => $key) {
        if (preg_match($pattern, $this->getEmbedCode($item), $matches)) {
          break;
        }
      }

      if (!empty($matches['shortcode'])) {
        $element[$delta] = [
          '#type' => 'html_tag',
          '#tag' => 'iframe',
          '#attributes' => [
            'allowtransparency' => 'true',
            'frameborder' => 0,
            'position' => 'absolute',
            'scrolling' => 'no',
            'src' => '//flickr.com/p/' . $matches['shortcode'] . '/embed/',
            'width' => $settings['width'],
            'height' => $settings['height'],
          ],
        ];
      }
    }

    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public static function defaultSettings() {
    return array(
      'width' => '480',
      'height' => '640',
    ) + parent::defaultSettings();
  }

  /**
   * {@inheritdoc}
   */
  public function settingsForm(array $form, FormStateInterface $form_state) {
    $elements = parent::settingsForm($form, $form_state);

    $elements['width'] = array(
      '#type' => 'number',
      '#title' => $this->t('Width'),
      '#default_value' => $this->getSetting('width'),
      '#min' => 1,
      '#description' => $this->t('Width of flickr.'),
    );

    $elements['height'] = array(
      '#type' => 'number',
      '#title' => $this->t('Height'),
      '#default_value' => $this->getSetting('height'),
      '#min' => 1,
      '#description' => $this->t('Height of flickr.'),
    );

    return $elements;
  }

  /**
   * {@inheritdoc}
   */
  public function settingsSummary() {
    return [
      $this->t('Width: @width px', [
        '@width' => $this->getSetting('width'),
      ]),
      $this->t('Height: @height px', [
        '@height' => $this->getSetting('height'),
      ]),
    ];
  }

}
