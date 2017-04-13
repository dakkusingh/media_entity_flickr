<?php

namespace Drupal\media_entity_flickr\Plugin\Field\FieldFormatter;

use Drupal\Core\Field\FieldItemListInterface;
use Drupal\Core\Field\FormatterBase;
use Drupal\media_entity\EmbedCodeValueTrait;

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
    foreach ($items as $delta => $item) {
      $element[$delta] = [
        '#type' => 'markup',
        '#markup' => $this->getEmbedCode($item),
        '#allowed_tags' => ['img', 'a', 'script'],
      ];
    }
    return $element;
  }

}
